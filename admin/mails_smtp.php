<?php
require_once '../includes/security.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

// Ensure settings table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) UNIQUE, setting_value TEXT)");

// Handle save
$success = '';
$error = '';
if (empty($_SESSION['csrf_smtp'])) { $_SESSION['csrf_smtp'] = bin2hex(random_bytes(16)); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf_smtp'], $tok)) { $error = 'Invalid CSRF token.'; }
    else {
    $smtp_host = trim($_POST['smtp_host']);
    $smtp_user = trim($_POST['smtp_user']);
    $smtp_pass = trim($_POST['smtp_pass']);
    $smtp_port = trim($_POST['smtp_port']);
    $smtp_secure = trim($_POST['smtp_secure']);
    $settings = [
        'smtp_host' => $smtp_host,
        'smtp_user' => $smtp_user,
        'smtp_pass' => $smtp_pass,
        'smtp_port' => $smtp_port,
        'smtp_secure' => $smtp_secure
    ];
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }
    $success = 'SMTP settings saved.';
    }
}
// Handle reset
if (isset($_GET['reset'])) {
    if (empty($_GET['csrf']) || !hash_equals($_SESSION['csrf_smtp'], (string)$_GET['csrf'])) {
        $error = 'Invalid CSRF token.';
    } else {
    $pdo->exec("DELETE FROM settings WHERE setting_key LIKE 'smtp_%'");
    $success = 'SMTP settings reset.';
    }
}
// Load current
function get_setting($key, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}
$title = 'Email SMTP - AK23 App';
include '../includes/admin_header.php';
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<h1 class="h3 mb-3" style="color:#000;"><i class="fas fa-envelope me-2"></i>Email SMTP</h1>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3 mb-3">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_smtp']) ?>">
  <div class="col-md-6">
    <label class="form-label">SMTP Host</label>
    <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_host', $pdo)) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">SMTP User</label>
    <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_user', $pdo)) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">SMTP Password</label>
    <input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_pass', $pdo)) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">SMTP Port</label>
    <input type="text" name="smtp_port" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_port', $pdo)) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">SMTP Secure</label>
    <input type="text" name="smtp_secure" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_secure', $pdo)) ?>">
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-warning">Save</button>
    <a href="mails_smtp.php?reset=1&csrf=<?= urlencode($_SESSION['csrf_smtp']) ?>" class="btn btn-outline-danger ms-2" onclick="return confirm('Reset all SMTP settings?');">Reset</a>
  </div>
</form>
<?php include '../includes/admin_footer.php'; ?>