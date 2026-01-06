<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// --- Token-based secure download endpoint (was in download.php) ---
if (isset($_GET['token'])) {
    require_once __DIR__ . '/download_helper.php';
    $token = $_GET['token'] ?? '';
    $info = verify_download_token($token);
    if (!$info) {
        http_response_code(400);
        exit('Invalid or expired token');
    }

    $order_id = (int)$info['order_id'];
    // Confirm order exists, belongs to a user, and is completed. Use medias table for file path.
    $stmt = $pdo->prepare("SELECT o.*, m.value AS file_url
                           FROM orders o
                           JOIN courses p ON o.course_id = p.id
                           LEFT JOIN medias m ON m.courses_id = o.course_id AND m.deleted_at IS NULL
                           WHERE o.id = ? AND o.status = 'completed'");
    $stmt->execute([$order_id]);
    $o = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$o) {
        http_response_code(403);
        exit('Order not found or not completed');
    }

    // Record download (no limit enforcement)
    $pdo->prepare("INSERT INTO downloads_log (order_id, downloaded_at) VALUES (?,NOW())")->execute([$order_id]);

    // Redirect to the actual file URL
    $file = $o['file_url'];
    header('Location: ' . $file);
    exit;
}

// --- Secure share token download endpoint ---
if (isset($_GET['share_token'])) {
    $share_token = $_GET['share_token'];
    $stmt = $pdo->prepare("SELECT t.*, m.value, m.file_type, m.type FROM media_share_tokens t JOIN medias m ON t.media_id = m.id WHERE t.token = ? AND m.deleted_at IS NULL");
    $stmt->execute([$share_token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        die('<h3 style="color:red;text-align:center;">Invalid or expired share link.</h3>');
    }
    if (strtotime($row['expires_at']) < time()) {
        die('<h3 style="color:red;text-align:center;">This share link has expired.</h3>');
    }
    // Log download (optional: implement download limits, IP logging, etc.)
    $pdo->prepare("INSERT INTO media_share_tokens_downloads (token, downloaded_at, ip_address, user_agent) VALUES (?, NOW(), ?, ?)")
        ->execute([$share_token, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    $file_path = $row['value'];
    $file_type = $row['file_type'];
    $is_local = ($row['type'] === 'upload' && file_exists($file_path));
    $filename = basename($file_path);
    // Serve file securely
    if ($is_local) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        header('Location: ' . $file_path);
        exit;
    }
}

if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Validate callback token early (before any output) to allow redirects
$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$callbackToken = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($ref === 'callback' && !empty($callbackToken)) {
    $tokenStmt = $pdo->prepare("SELECT id FROM orders WHERE callback_token = ? AND callback_used_at IS NULL AND user_id = ? LIMIT 1");
    $tokenStmt->execute([$callbackToken, $user_id]);
    $validToken = $tokenStmt->fetch(PDO::FETCH_ASSOC);
    if ($validToken) {
        // Mark token as used
        $pdo->prepare("UPDATE orders SET callback_used_at = NOW() WHERE id = ?")
             ->execute([$validToken['id']]);
    } else {
        // Invalid or already used token - redirect to prevent exploits
        header('Location: downloads.php');
        exit;
    }
}

$title = 'My Downloads - AK23 App';
include 'user_header.php';
?>
<?php include 'user_sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
  <?php
  // Get all courses the user has paid for from the new system
  // Use medias table to resolve actual downloadable file (value column)
  $stmt = $pdo->prepare("SELECT 
      o.id AS order_id,
      o.course_id,
      p.title AS course_name,
      m.value AS download_url,
      o.status,
      o.created_at,
      pay.status AS payment_status,
      pay.confirmation_code
    FROM orders o
    JOIN courses p ON o.course_id = p.id
    JOIN payments pay ON o.id = pay.order_id
    LEFT JOIN medias m ON m.courses_id = o.course_id AND m.deleted_at IS NULL
    WHERE o.user_id = ? 
    AND o.status = 'completed' 
    AND pay.status = 'completed'
    AND pay.confirmation_code IS NOT NULL
    ORDER BY o.created_at DESC");
  $stmt->execute([$user_id]);
  $downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <?php if ($ref !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <strong>Payment successful!</strong> Your download is ready.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <h2 class="h4 mb-4" style="color: black;">My Downloads</h2>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>course</th>
          <th>Download</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($downloads as $dl): ?>
          <tr>
            <td>
              <?= htmlspecialchars($dl['course_name']) ?>
            </td>
            <td>
              <?php
                $payload = [
                    'order_id' => $dl['order_id'],
                    'course_id' => $dl['course_id'],
                    'url' => $dl['download_url'],
                ];
                $token = function_exists('ak23_sign') ? ak23_sign($payload) : '';
              ?>
              <a href="downloads.php?token=<?= urlencode($token) ?>" class="btn btn-success btn-sm" target="_blank">Download</a>
            </td>
            <td>
              <span class="badge bg-success">
                <?= ucfirst(strtolower($dl['status'])) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($dl['created_at']) ?></td>
            <td>
              <a class="btn btn-outline-primary btn-sm" href="orders.php?order_id=<?= (int)$dl['order_id'] ?>">View Order</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include 'user_footer.php'; ?> 