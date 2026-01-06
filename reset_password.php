<?php
session_start();
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/functions.php';

$title = 'Reset Password - AK23 App';
$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$state = 'check'; // check | form | done | invalid | expired | error
$error = '';

$user = null;
if ($token !== '') {
    try {
        $stmt = $pdo->prepare('SELECT id, email, reset_token_expires FROM users WHERE reset_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($user) {
            $exp = $user['reset_token_expires'] ? strtotime($user['reset_token_expires']) : 0;
            if ($exp > time()) {
                $state = 'form';
            } else {
                $state = 'expired';
            }
        } else {
            $state = 'invalid';
        }
    } catch (Throwable $e) {
        $state = 'error';
        logError('Reset password lookup failed', ['error' => $e->getMessage()]);
    }
} else {
    $state = 'invalid';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['token']) ? trim((string)$_POST['token']) : '';
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirmation'] ?? '');

    if ($token === '') { $state = 'invalid'; }
    else {
        try {
            $stmt = $pdo->prepare('SELECT id, email, reset_token_expires FROM users WHERE reset_token = ? LIMIT 1');
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$user) {
                $state = 'invalid';
            } else {
                $exp = $user['reset_token_expires'] ? strtotime($user['reset_token_expires']) : 0;
                if ($exp <= time()) {
                    $state = 'expired';
                } else {
                    // Validate passwords
                    if ($password === '' || $confirm === '') {
                        $error = 'Please enter and confirm your new password.';
                        $state = 'form';
                    } elseif ($password !== $confirm) {
                        $error = 'Passwords do not match.';
                        $state = 'form';
                    } elseif (strlen($password) < 6) {
                        $error = 'Password must be at least 6 characters.';
                        $state = 'form';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
                        $upd->execute([$hash, (int)$user['id']]);
                        // Optionally log in the user
                        $_SESSION['user_logged_in'] = true;
                        $_SESSION['role'] = 'user';
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['username'] = $_SESSION['username'] ?? '';
                        $state = 'done';
                    }
                }
            }
        } catch (Throwable $e) {
            $state = 'error';
            logError('Reset password save failed', ['error' => $e->getMessage()]);
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container d-flex justify-content-center align-items-center" style="min-height: 70vh;">
  <div class="card p-4 shadow" style="max-width: 420px; width: 100%;">
    <h1 class="mb-3 text-center">Reset Password</h1>

    <?php if ($state === 'done'): ?>
      <div class="alert alert-success">Your password has been reset successfully.</div>
      <a class="btn btn-primary w-100" href="user_dashboard.php">Continue</a>
    <?php elseif ($state === 'invalid'): ?>
      <div class="alert alert-danger">Invalid or missing reset token.</div>
      <a class="btn btn-outline-secondary w-100" href="forgot_password.php">Request a new reset link</a>
    <?php elseif ($state === 'expired'): ?>
      <div class="alert alert-warning">This reset link has expired. Please request a new one.</div>
      <a class="btn btn-outline-secondary w-100" href="forgot_password.php">Request a new reset link</a>
    <?php elseif ($state === 'error'): ?>
      <div class="alert alert-danger">An error occurred. Please try again later.</div>
      <a class="btn btn-outline-secondary w-100" href="forgot_password.php">Back</a>
    <?php else: /* form */ ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success w-100">Set new password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
