<?php
// Secure session cookie flags (best effort)
if (PHP_SAPI !== 'cli') {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
        @ini_set('session.cookie_secure', '1');
    }
}
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

// Optional redirect back (keep it relative for safety)
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
if ($redirect !== '' && (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://'))) {
    $redirect = '';
}

// If already logged in, bounce
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true && ($_SESSION['role'] ?? '') === 'user') {
    header('Location: ' . ($redirect !== '' ? $redirect : 'user_dashboard.php'));
    exit();
}

$error = '';
$success = '';
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token check
    if (empty($_SESSION['csrf_register']) || !hash_equals((string)$_SESSION['csrf_register'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Invalid request. Please refresh and try again.';
    }
    // Honeypot check
    $hp = trim((string)($_POST['website'] ?? ''));
    if ($error === '' && $hp !== '') { $error = 'Invalid request.'; }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '') { $email = function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email); }
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirmation'] ?? '');

    // Simple rate limiting: 60s per IP and per email
    if ($error === '') {
        $_SESSION['reg_rl'] = $_SESSION['reg_rl'] ?? ['ip' => [], 'email' => []];
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();
        $lastIp = $_SESSION['reg_rl']['ip'][$ip] ?? 0;
        $lastEmail = $_SESSION['reg_rl']['email'][$email] ?? 0;
        if (($now - (int)$lastIp) < 60 || ($now - (int)$lastEmail) < 60) {
            $error = 'Please wait a moment before trying again.';
        } else {
            $_SESSION['reg_rl']['ip'][$ip] = $now;
            if ($email !== '') { $_SESSION['reg_rl']['email'][$email] = $now; }
        }
    }

    if ($error === '' && ($name === '' || $email === '' || $phone === '' || $password === '')) {
        $error = 'All fields are required.';
    } elseif ($error === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($error === '' && $password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($error === '' && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($error === '') {
        // Clean and validate phone number
        if (function_exists('cleanPhoneNumber')) {
            $phone = cleanPhoneNumber($phone);
        }
        if (function_exists('validatePhoneNumber')) {
            $valid = validatePhoneNumber($phone);
            if ($valid !== true) {
                $error = is_string($valid) ? $valid : 'Please enter a valid phone number.';
            }
        }
    } else {
        try {
            // Ensure users table has verification columns
            $checkCols = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->fetch();
            if (!$checkCols) {
                $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0");
            }
            // Ensure phone column exists
            $checkPhone = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
            if (!$checkPhone) {
                $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL");
            }
            $checkToken = $pdo->query("SHOW COLUMNS FROM users LIKE 'verification_token'")->fetch();
            if (!$checkToken) {
                $pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) NULL");
            }
            $checkExp = $pdo->query("SHOW COLUMNS FROM users LIKE 'verification_expires'")->fetch();
            if (!$checkExp) {
                $pdo->exec("ALTER TABLE users ADD COLUMN verification_expires DATETIME NULL");
            }

            // Ensure email is unique
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = 'An account with this email already exists. Please login instead.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // Skip email verification: mark verified on create
                $ins = $pdo->prepare('INSERT INTO users (name, email, phone, password, is_verified, verification_token, verification_expires, created_at) VALUES (?, ?, ?, ?, 1, NULL, NULL, NOW())');
                $insertOk = false;
                try {
                    $ins->execute([$name, $email, $phone, $hash]);
                    $insertOk = true;
                } catch (PDOException $e) {
                    // Handle race conditions when unique index exists
                    if ($e->getCode() === '23000') {
                        $error = 'An account with this email already exists. Please login instead.';
                        $insertOk = false; // stay on page and show error
                    } else {
                        throw $e;
                    }
                }
                if ($insertOk) {
                    // Auto-login user immediately and bypass email verification
                    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                    $uid = (int)$pdo->lastInsertId();
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['role'] = 'user';
                    $_SESSION['user_id'] = $uid;
                    $_SESSION['username'] = $name;
                    header('Location: ' . ($redirect !== '' ? $redirect : 'user_dashboard.php'));
                    exit();
                }
            }
        } catch (Throwable $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}

$title = 'Create Account - AK23 App';
// Generate CSRF token for register form
if (empty($_SESSION['csrf_register'])) {
    $_SESSION['csrf_register'] = bin2hex(random_bytes(16));
}
include 'includes/header.php';
?>
<style>
  .btn-ak-primary { background-color:#FFA500; border-color:#FFA500; color:#111; font-weight:600; }
  .btn-ak-primary:hover { background-color:#e59400; border-color:#e59400; color:#111; }
  .link-ak { color:#e59400; }
  .link-ak:hover { color:#c87f00; }
  .ak-card { border-top: 4px solid #FFA500; }
  .ak-logo { height:48px; }
  .google-btn img { width:18px; height:18px; margin-right:8px; vertical-align:text-bottom; }
</style>
<div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
  <div class="card p-4 shadow ak-card" style="max-width: 420px; width: 100%;">
    <div class="text-center mb-2">
      <img src="assets/images/ak.png" alt="AK Logo" class="ak-logo">
    </div>
    <h1 class="mb-3 text-center">Create Account</h1>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_register']) ?>">
      <!-- Honeypot -->
      <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required autocomplete="name">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
      </div>
      <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" required autocomplete="tel">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required autocomplete="new-password">
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-ak-primary w-100">Create Account</button>
    </form>
    <div class="mt-3 text-center">
      <a class="link-ak" href="login.php<?= $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '' ?>">Already have an account? Login</a>
      <div class="mt-3">
        <a class="btn btn-outline-dark w-100 d-flex align-items-center justify-content-center google-btn" href="login_google.php<?= $redirect !== '' ? ('?redirect=' . urlencode($redirect)) : '' ?>">
          <img src="assets/images/google.png" alt="Google"> Continue with Google
        </a>
      </div>
    </div>
  </div>
</div>
<script>
  (function(){
    try {
      var form = document.querySelector('form[method="POST"]');
      if (!form) return;
      var nameEl = form.querySelector('input[name="name"]');
      var emailEl = form.querySelector('input[name="email"]');
      var phoneEl = form.querySelector('input[name="phone"]');

      // Prefill from localStorage if fields are empty
      var lsName = localStorage.getItem('ak23_profile_name');
      var lsEmail = localStorage.getItem('ak23_profile_email');
      var lsPhone = localStorage.getItem('ak23_profile_phone');
      if (nameEl && !nameEl.value && lsName) nameEl.value = lsName;
      if (emailEl && !emailEl.value && lsEmail) emailEl.value = lsEmail;
      if (phoneEl && !phoneEl.value && lsPhone) phoneEl.value = lsPhone;

      // Save on submit for future prefill
      form.addEventListener('submit', function(){
        if (nameEl && nameEl.value) localStorage.setItem('ak23_profile_name', nameEl.value);
        if (emailEl && emailEl.value) localStorage.setItem('ak23_profile_email', emailEl.value);
        if (phoneEl && phoneEl.value) localStorage.setItem('ak23_profile_phone', phoneEl.value);
      });
    } catch (e) { /* no-op */ }
  })();
</script>
<?php include 'includes/footer.php'; ?>
