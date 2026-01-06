<?php
session_start();
$email = isset($_GET['email']) ? (string)$_GET['email'] : '';
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
if ($redirect !== '' && (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://'))) {
  $redirect = '';
}
$title = 'Verify Your Email - AK23 App';
include 'includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Verify Your Email</h1>
          <p>We sent a verification link to <strong><?= htmlspecialchars($email) ?></strong>. Please check your inbox and click the link to verify your account.</p>
          <p class="mb-0">Didn't receive the email?</p>
          <ul class="mb-3">
            <li>Check your spam folder.</li>
            <li>Make sure your mailbox isn't full.</li>
          </ul>
          <a class="btn btn-outline-primary" href="resend_verification.php?email=<?= urlencode($email) ?><?= $redirect !== '' ? ('&redirect=' . urlencode($redirect)) : '' ?>">Resend Verification Email</a>
          <a class="btn btn-link" href="login.php<?= $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '' ?>">Back to Login</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
