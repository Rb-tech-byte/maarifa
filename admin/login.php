<?php
require_once '../includes/security.php'; // sets secure cookie flags, headers, starts session
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}
// Simple rate limit per IP (5 tries per 5 minutes)
$_SESSION['admin_login_rl'] = $_SESSION['admin_login_rl'] ?? [];
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$now = time();
$bucket = &$_SESSION['admin_login_rl'][$ip];
if (!is_array($bucket)) { $bucket = ['fails'=>0,'ts'=>0,'lock_until'=>0]; }
if ($bucket['lock_until'] > $now) { $error = 'Too many attempts. Please try again later.'; }

// CSRF token
if (empty($_SESSION['csrf_admin_login'])) { $_SESSION['csrf_admin_login'] = bin2hex(random_bytes(16)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!function_exists('ak23_csrf_validate')) { require_once '../includes/security.php'; }
    $tok = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf_admin_login'], $tok)) {
        $error = 'Invalid CSRF token.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $user = authenticateAdmin($username, $password);
        if ($user) {
            $_SESSION['admin_login_rl'][$ip] = ['fails'=>0,'ts'=>$now,'lock_until'=>0];
            if (session_status() === PHP_SESSION_ACTIVE) { @session_regenerate_id(true); }
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid credentials";
            $fails = (int)$bucket['fails'] + 1; $bucket['fails'] = $fails; $bucket['ts'] = $now; if ($fails >= 5) { $bucket['lock_until'] = $now + 300; }
        }
    }
}
$title = 'Admin Login - AK23 App';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="card p-4 shadow" style="max-width: 400px; width: 100%;">
        <h1 class="mb-3 text-center" style="color:#000;">Admin Login</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_admin_login']) ?>">
            <div class="mb-3">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>