<?php
session_start();
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/functions.php';

function redirect_back($msg = null, $err = false){
  if ($msg) {
    $_SESSION[$err ? 'flash_error' : 'flash_success'] = $msg;
  }
  header('Location: request_course.php');
  exit();
}

// CSRF check
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
  redirect_back('Invalid session. Please try again.', true);
}

// Require logged-in user
if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_id'])) {
  $_SESSION['flash_error'] = 'Please log in to submit a course request.';
  header('Location: login.php');
  exit();
}

// Fetch current user info
$user_id = (int)$_SESSION['user_id'];
$user = getUserById($user_id);
if (!$user) {
  redirect_back('Could not load your account. Please re-login.', true);
}
$name = trim((string)($user['name'] ?? ''));
$email = trim((string)($user['email'] ?? ''));
$phone = trim((string)($user['phone'] ?? ''));

$dailyLimit = 3;
// Check today's request count for this user (00:00 to 23:59)
try {
  $stmtLimit = $pdo->prepare("SELECT COUNT(*) FROM course_requests WHERE user_id = ? AND created_at >= CURDATE() AND created_at < (CURDATE() + INTERVAL 1 DAY)");
  $stmtLimit->execute([$user_id]);
  $todaysCount = (int)$stmtLimit->fetchColumn();
  if ($todaysCount >= $dailyLimit) {
    redirect_back('Daily limit reached. You can only submit up to 3 course requests per day.', true);
  }
} catch (Throwable $e) {
  // If limit check fails for any reason, be safe and block to protect server
  redirect_back('Unable to process request at the moment. Please try again later.', true);
}

// Per-IP daily limit to reduce spam/abuse
$perIpDailyLimit = 10;
try {
  $ipCheck = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
  if (strpos($ipCheck, ',') !== false) { $ipCheck = trim(explode(',', $ipCheck)[0]); }
  $ipCheck = substr((string)$ipCheck, 0, 45);
  if ($ipCheck !== '') {
    $stmtIp = $pdo->prepare("SELECT COUNT(*) FROM course_requests WHERE ip_address = ? AND created_at >= CURDATE() AND created_at < (CURDATE() + INTERVAL 1 DAY)");
    $stmtIp->execute([$ipCheck]);
    $ipCount = (int)$stmtIp->fetchColumn();
    if ($ipCount >= $perIpDailyLimit) {
      redirect_back('Too many requests from your network today. Please try again tomorrow.', true);
    }
  }
} catch (Throwable $e) {
  // Silent fail; do not block if IP check fails
}

$course_name = trim((string)($_POST['course_name'] ?? ''));
$course_link = trim((string)($_POST['course_link'] ?? ''));
$category = trim((string)($_POST['category'] ?? ''));
$details = trim((string)($_POST['details'] ?? ''));

if ($course_name === '') {
  redirect_back('Please enter the course name.', true);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_back('Your account email is missing or invalid. Please update your profile.', true);
}
if ($course_link !== '' && !filter_var($course_link, FILTER_VALIDATE_URL)) {
  redirect_back('course link must be a valid URL.', true);
}

try {
  // Create table if not exists
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
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    device_fingerprint VARCHAR(64) NULL,
    status ENUM('new','in_review','fulfilled','rejected') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_ip_created (ip_address, created_at),
    INDEX idx_fpr_created (device_fingerprint, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // Ensure columns exist for older installations
  $hasIp = $pdo->query("SHOW COLUMNS FROM course_requests LIKE 'ip_address'")->fetch();
  if (!$hasIp) { $pdo->exec("ALTER TABLE course_requests ADD COLUMN ip_address VARCHAR(45) NULL AFTER details"); }
  $hasUa = $pdo->query("SHOW COLUMNS FROM course_requests LIKE 'user_agent'")->fetch();
  if (!$hasUa) { $pdo->exec("ALTER TABLE course_requests ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address"); }
  $hasFpr = $pdo->query("SHOW COLUMNS FROM course_requests LIKE 'device_fingerprint'")->fetch();
  if (!$hasFpr) { $pdo->exec("ALTER TABLE course_requests ADD COLUMN device_fingerprint VARCHAR(64) NULL AFTER user_agent"); }
  // Ensure composite index for limit query
  $hasIdx = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_requests' AND INDEX_NAME = 'idx_user_created' LIMIT 1")->fetch();
  if (!$hasIdx) { $pdo->exec("CREATE INDEX idx_user_created ON course_requests (user_id, created_at)"); }
  $hasIdxIp = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_requests' AND INDEX_NAME = 'idx_ip_created' LIMIT 1")->fetch();
  if (!$hasIdxIp) { $pdo->exec("CREATE INDEX idx_ip_created ON course_requests (ip_address, created_at)"); }
  $hasIdxFpr = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_requests' AND INDEX_NAME = 'idx_fpr_created' LIMIT 1")->fetch();
  if (!$hasIdxFpr) { $pdo->exec("CREATE INDEX idx_fpr_created ON course_requests (device_fingerprint, created_at)"); }

  // Capture requester IP and User-Agent
  $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
  if (strpos($ip, ',') !== false) { $ip = trim(explode(',', $ip)[0]); }
  $ip = substr((string)$ip, 0, 45);
  $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
  $fpr = substr((string)($_POST['device_fingerprint'] ?? ''), 0, 64);

  $stmt = $pdo->prepare("INSERT INTO course_requests
    (user_id, name, email, phone, course_name, course_link, category, details, ip_address, user_agent, device_fingerprint)
    VALUES (:user_id, :name, :email, :phone, :course_name, :course_link, :category, :details, :ip_address, :user_agent, :device_fingerprint)");
  $stmt->execute([
    ':user_id' => $user_id ?: null,
    ':name' => $name,
    ':email' => $email,
    ':phone' => $phone ?: null,
    ':course_name' => $course_name,
    ':course_link' => $course_link ?: null,
    ':category' => $category ?: null,
    ':details' => $details ?: null,
    ':ip_address' => $ip ?: null,
    ':user_agent' => $ua ?: null,
    ':device_fingerprint' => $fpr ?: null,
  ]);

  $reqId = (int)$pdo->lastInsertId();

  // Build base URL for links
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
  $base = $scheme . '://' . $host;

  // Send confirmation email to requester
  try {
    $to = $email;
    $subject = 'We received your course request - AK23 Studio Kits';
    $adminLink = $base . '/admin/course_requests.php';
    $html = '<p>Hi ' . htmlspecialchars($name) . ',</p>' .
            '<p>Thank you for your request for <strong>' . htmlspecialchars($course_name) . '</strong>.</p>' .
            ($course_link !== '' ? ('<p>Provided link: <a href="' . htmlspecialchars($course_link) . '">' . htmlspecialchars($course_link) . '</a></p>') : '') .
            '<p>We will review and get back to you shortly.</p>' .
            '<p>AK23 Studio Kits</p>';
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

  // Email admin about new request
  try {
    $toAdmin = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@localhost';
    $subjectA = 'New course request: ' . $course_name;
    $htmlA = '<p>New request submitted:</p>' .
             '<ul>' .
             '<li>Name: ' . htmlspecialchars($name) . '</li>' .
             '<li>Email: ' . htmlspecialchars($email) . '</li>' .
             ($phone !== '' ? ('<li>Phone: ' . htmlspecialchars($phone) . '</li>') : '') .
             '<li>course: ' . htmlspecialchars($course_name) . '</li>' .
             ($course_link !== '' ? ('<li>Link: <a href="' . htmlspecialchars($course_link) . '">' . htmlspecialchars($course_link) . '</a></li>') : '') .
             ($category !== '' ? ('<li>Category: ' . htmlspecialchars($category) . '</li>') : '') .
             '</ul>' .
             '<p><a href="' . htmlspecialchars($base . '/admin/course_requests.php') . '">Open admin panel</a></p>';
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
        $mail->addAddress($toAdmin);
        $mail->isHTML(true);
        $mail->Subject = $subjectA;
        $mail->Body = $htmlA;
        $mail->send();
      } catch (Throwable $e) { /* ignore */ }
    } else {
      $headersA = "MIME-Version: 1.0\r\n" . "Content-type:text/html;charset=UTF-8\r\n";
      $fromHdrA = defined('SMTP_FROM_EMAIL') ? (SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>') : 'AK23 Studio Kits <no-reply@localhost>';
      $headersA .= "From: $fromHdrA\r\n";
      @mail($toAdmin, $subjectA, $htmlA, $headersA);
    }
  } catch (Throwable $e) { /* ignore */ }

  // Notifications
  try {
    if (!empty($user_id)) { createNotification($user_id, 'system', 'We received your request for ' . $course_name . '.'); }
    createNotification(1, 'system', 'New course request: ' . $course_name . ' by ' . $email);
  } catch (Throwable $e) { /* ignore */ }

  redirect_back('Your request has been submitted. We will get back to you soon.');
} catch (Throwable $e) {
  logError('Failed to submit course request', ['error'=>$e->getMessage()]);
  redirect_back('Something went wrong. Please try again later.', true);
}
