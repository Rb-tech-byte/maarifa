<?php
session_start();
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/functions.php';

$title = 'Forgot Password - AK23 App';
$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '') { $email = function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email); }
    // Always respond the same to avoid email enumeration
    $sent = true;
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Simple rate limiting: 60s per IP and per email
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();
        $_SESSION['fp_last'] = $_SESSION['fp_last'] ?? ['ip' => [], 'email' => []];
        $lastIp = $_SESSION['fp_last']['ip'][$ip] ?? 0;
        $lastEmail = $_SESSION['fp_last']['email'][$email] ?? 0;
        if (($now - (int)$lastIp) < 60 || ($now - (int)$lastEmail) < 60) {
            // Silently accept but do not send a new email
            return include __DIR__ . '/includes/header.php';
        }
        $_SESSION['fp_last']['ip'][$ip] = $now;
        $_SESSION['fp_last']['email'][$email] = $now;
        try {
            // Ensure columns exist
            $hasToken = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'")->fetch();
            if (!$hasToken) { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL"); }
            $hasExp = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token_expires'")->fetch();
            if (!$hasExp) { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL"); }

            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($user) {
                $token = bin2hex(random_bytes(24));
                $expires = date('Y-m-d H:i:s', time() + 60*60); // 60 minutes
                $upd = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
                $upd->execute([$token, $expires, (int)$user['id']]);
                if (function_exists('sendPasswordResetEmail')) {
                    @sendPasswordResetEmail($user['email'], $token);
                }
            }
        } catch (Throwable $e) {
            // Don't reveal details; log internally
            logError('Forgot password error', ['error' => $e->getMessage()]);
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container d-flex justify-content-center align-items-center" style="min-height: 70vh;">
  <div class="card p-4 shadow" style="max-width: 420px; width: 100%;">
    <h1 class="mb-3 text-center">Forgot Password</h1>
    <?php if ($sent): ?>
      <div class="alert alert-info">
        If an account with that email exists, we have sent a password reset link. Please check your inbox.
      </div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Send reset link</button>
      </form>
      <div class="mt-3 text-center">
        <a href="login.php">Back to Login</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
