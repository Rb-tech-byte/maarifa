<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

function back($msg=null,$err=false){
  if ($msg) $_SESSION[$err?'flash_error':'flash_success']=$msg;
  header('Location: course_requests.php');
  exit();
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
  back('Invalid session. Please retry.', true);
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
$allowed = ['new','in_review','fulfilled','rejected'];
if ($id <= 0 || !in_array($status, $allowed, true)) { back('Invalid input.', true); }

try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS course_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(191) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(50) NULL,
    course_name VARCHAR(255) NOT NULL,
    course_link VARCHAR(512) NULL,
    category VARCHAR(191) NULL,
    details TEXT NULL,
    status ENUM('new','in_review','fulfilled','rejected') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // Fetch existing request for context
  $req = null;
  $stmtSel = $pdo->prepare('SELECT * FROM course_requests WHERE id = :id LIMIT 1');
  $stmtSel->execute([':id' => $id]);
  $req = $stmtSel->fetch(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare('UPDATE course_requests SET status = :s WHERE id = :id');
  $stmt->execute([':s'=>$status, ':id'=>$id]);

  // Build base URL
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
  $base = $scheme . '://' . $host;

  // Email requester about status change
  if ($req && !empty($req['email'])) {
    try {
      $to = (string)$req['email'];
      $name = (string)($req['name'] ?? '');
      $course_name = (string)($req['course_name'] ?? 'your requested course');
      $subject = 'Your request status updated - ' . $course_name;
      $html = '<p>Hi ' . htmlspecialchars($name) . ',</p>' .
              '<p>Your course request for <strong>' . htmlspecialchars($course_name) . '</strong> has been updated to status: <strong>' . htmlspecialchars($status) . '</strong>.</p>' .
              '<p>Thank you for using AK23 Studio Kits.</p>';
      if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
          $mail->isSMTP();
          $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
          $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 25;
          $secure = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : '';
          if ($secure === 'ssl') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; $mail->SMTPAutoTLS = false; }
          elseif ($secure === 'tls') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
          $mail->SMTPAuth = true;
          if (defined('SMTP_USERNAME')) $mail->Username = SMTP_USERNAME;
          if (defined('SMTP_PASSWORD')) $mail->Password = SMTP_PASSWORD;
          $mail->CharSet = 'UTF-8';
          if (defined('DISABLE_SSL_VERIFICATION') && DISABLE_SSL_VERIFICATION) {
            $mail->SMTPOptions = ['ssl' => ['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
          }
          $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@localhost';
          $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'AK23 Studio Kits';
          $mail->setFrom($fromEmail, $fromName);
          $mail->addAddress($to, $name);
          $mail->isHTML(true);
          $mail->Subject = $subject;
          $mail->Body = $html;
          $mail->send();
        } catch (Throwable $e) { /* ignore */ }
      } else {
        $headers = "MIME-Version: 1.0\r\n" . "Content-type:text/html;charset=UTF-8\r\n";
        $fromHdr = defined('SMTP_FROM_EMAIL') ? (SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>') : 'AK23 Studio Kits <no-reply@localhost>';
        $headers .= "From: $fromHdr\r\n";
        @mail($to, $subject, $html, $headers);
      }
    } catch (Throwable $e) { /* ignore */ }
  }

  // Notifications (user and admin)
  try {
    if ($req && !empty($req['user_id'])) {
      createNotification((int)$req['user_id'], 'system', 'Your request for ' . ($req['course_name'] ?? 'a course') . ' is now ' . $status . '.');
    }
    createNotification(1, 'system', 'Request #' . $id . ' (' . (($req['course_name'] ?? '') ?: 'course') . ') status updated to ' . $status . '.');
  } catch (Throwable $e) { /* ignore */ }

  back('Status updated.');
} catch (Throwable $e) {
  logError('Failed to update course request status', ['error'=>$e->getMessage(), 'id'=>$id]);
  back('Update failed. Try again.', true);
}
