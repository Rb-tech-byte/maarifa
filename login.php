<?php
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

// Secure session cookie flags (best effort)
if (PHP_SAPI !== 'cli') {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
        @ini_set('session.cookie_secure', '1');
    }
}
session_start();
// Optional redirect back to a page after login (only allow relative paths)
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
if ($redirect !== '' && (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://'))) {
    $redirect = '';
}
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    $role = $_SESSION['role'] ?? 'user';
    if ($role === 'instructor') {
        header('Location: ' . ($redirect !== '' ? $redirect : 'instructor_dashboard.php'));
    } else {
        header('Location: ' . ($redirect !== '' ? $redirect : 'user_dashboard.php'));
    }
    exit();
}

$error = '';
// Generate CSRF token for login form
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
}

// Simple rate limiting: max 5 failures per 5 minutes per IP
$_SESSION['login_rl'] = $_SESSION['login_rl'] ?? [];
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$now = time();
$bucket = &$_SESSION['login_rl'][$ip];
if (!is_array($bucket)) { $bucket = ['fails' => 0, 'ts' => 0, 'lock_until' => 0]; }
if ($bucket['lock_until'] > $now) {
    $error = 'Too many attempts. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    // CSRF check
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf_login'], $csrf)) {
        $error = 'Invalid request. Please refresh and try again.';
    }
    // Honeypot (should be empty)
    $hp = trim((string)($_POST['website'] ?? ''));
    if ($error === '' && $hp !== '') {
        $error = 'Invalid request.';
    }

    $name = trim((string)($_POST['name'] ?? ''));
    // Normalize if it's an email
    if (filter_var($name, FILTER_VALIDATE_EMAIL)) {
        $name = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }
    $password = (string)($_POST['password'] ?? '');
    $user = ($error === '') ? authenticateUser($name, $password) : false;
    if ($user) {
        // Reset rate limit bucket on success
        $_SESSION['login_rl'][$ip] = ['fails' => 0, 'ts' => $now, 'lock_until' => 0];
        if (session_status() === PHP_SESSION_ACTIVE) { @session_regenerate_id(true); }
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['name'];
        $role = $user['role'] ?? 'user';
        $_SESSION['role'] = $role;
        // Skip email verification gating: allow login regardless of is_verified
        if ($role === 'instructor') {
            header('Location: ' . ($redirect !== '' ? $redirect : 'instructor_dashboard.php'));
        } else {
            header('Location: ' . ($redirect !== '' ? $redirect : 'user_dashboard.php'));
        }
        exit();
    } else {
        if ($error === '') {
            $error = 'Invalid name/email or password.';
        }
        // Update rate limit bucket on failure
        $fails = (int)$bucket['fails'] + 1;
        $bucket['fails'] = $fails;
        $bucket['ts'] = $now;
        if ($fails >= 5) {
            $bucket['lock_until'] = $now + 300; // 5 minutes
        }
    }
}
$title = 'User Login - AK23 App';
include 'includes/header.php';
?>
<style>
  /* AK brand accents derived from ak.png: light surfaces with orange accent */
  .btn-ak-primary {
    background-color: #FFA500; /* orange accent */
    border-color: #FFA500;
    color: #111;
    font-weight: 600;
  }
  .btn-ak-primary:hover { background-color: #e59400; border-color: #e59400; color:#111; }
  .link-ak { color: #e59400; }
  .link-ak:hover { color: #c87f00; }
  .google-btn img { width: 18px; height: 18px; margin-right: 8px; vertical-align: text-bottom; }
  .ak-card { border-top: 4px solid #FFA500; }
  .ak-logo { height: 48px; }
</style>
<div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="card p-4 shadow ak-card" style="max-width: 420px; width: 100%;">
        <div class="text-center mb-2">
          <img src="assets/images/ak.png" alt="AK Logo" class="ak-logo">
        </div>
        <h1 class="mb-3 text-center">User Login</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_login']) ?>">
            <!-- Honeypot -->
            <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
              <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <div class="mb-3">
                <input type="text" name="name" class="form-control" placeholder="Name or Email" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-ak-primary w-100">Login</button>
        </form>
        <div class="mt-3 text-center">
            <a href="forgot_password.php" class="link-ak">Forgot Password?</a>
            <div class="mt-2">
              <a class="link-ak" href="register.php<?= $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '' ?>">Create an account</a>
            </div>
            <div class="mt-3">
              <a class="btn btn-outline-dark w-100 d-flex align-items-center justify-content-center google-btn" href="login_google.php<?= $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '' ?>">
                <img src="assets/images/google.png" alt="Google"> Continue with Google
              </a>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>