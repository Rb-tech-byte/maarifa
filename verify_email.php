<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
if ($redirect !== '' && (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://'))) {
  $redirect = '';
}

$title = 'Email Verification - AK23 App';
include 'includes/header.php';

$status = 'invalid';
$message = 'Invalid or missing token.';

if ($token !== '') {
  try {
    $stmt = $pdo->prepare("SELECT id, email, verification_expires FROM users WHERE verification_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($user) {
      $now = time();
      $expTs = $user['verification_expires'] ? strtotime($user['verification_expires']) : 0;
      if ($expTs > $now) {
        $upd = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?");
        $upd->execute([(int)$user['id']]);
        // Auto-login user
        $_SESSION['user_logged_in'] = true;
        $_SESSION['role'] = 'user';
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $_SESSION['username'] ?? '';
        $status = 'success';
        $message = 'Your email has been verified successfully.';
      } else {
        $status = 'expired';
        $message = 'This verification link has expired. Please request a new one.';
      }
    }
  } catch (Throwable $e) {
    $status = 'error';
    $message = 'An error occurred while verifying your email. Please try again.';
  }
}
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Email Verification</h1>
          <div class="alert alert-<?= $status === 'success' ? 'success' : ($status === 'expired' ? 'warning' : 'danger') ?>">
            <?= htmlspecialchars($message) ?>
          </div>
          <?php if ($status === 'success'): ?>
            <a class="btn btn-primary" href="<?= $redirect !== '' ? htmlspecialchars($redirect) : 'user_dashboard.php' ?>">Continue</a>
          <?php elseif ($status === 'expired'): ?>
            <a class="btn btn-outline-primary" href="resend_verification.php?email=<?= isset($user['email']) ? urlencode($user['email']) : '' ?><?= $redirect !== '' ? ('&redirect=' . urlencode($redirect)) : '' ?>">Resend Verification Email</a>
            <a class="btn btn-link" href="login.php<?= $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '' ?>">Back to Login</a>
          <?php else: ?>
            <a class="btn btn-link" href="login.php">Back to Login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
