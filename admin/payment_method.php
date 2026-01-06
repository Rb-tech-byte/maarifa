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

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) UNIQUE, setting_value TEXT)");

$success = '';
$error = '';
if (empty($_SESSION['csrf_payment'])) { $_SESSION['csrf_payment'] = bin2hex(random_bytes(16)); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf_payment'], $tok)) { $error = 'Invalid CSRF token.'; }
    else {
    $payment_gateway = trim($_POST['payment_gateway']);
    $payment_api_key = trim($_POST['payment_api_key']);
    $settings = [
        'payment_gateway' => $payment_gateway,
        'payment_api_key' => $payment_api_key
    ];
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }
    $success = 'Payment method settings saved.';
    }
}
if (isset($_GET['reset'])) {
    if (empty($_GET['csrf']) || !hash_equals($_SESSION['csrf_payment'], (string)$_GET['csrf'])) {
        $error = 'Invalid CSRF token.';
    } else {
    $pdo->exec("DELETE FROM settings WHERE setting_key LIKE 'payment_%'");
    $success = 'Payment method settings reset.';
    }
}
function get_setting($key, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}
$title = 'Payment Methods - AK23 App';
include '../includes/admin_header.php';
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<h1 class="h3 mb-3" style="color:#000;"><i class="fas fa-money-check-alt me-2"></i>Payment Methods</h1>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3 mb-3">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_payment']) ?>">
  <div class="col-md-6">
    <label class="form-label">Payment Gateway</label>
    <input type="text" name="payment_gateway" class="form-control" value="<?= htmlspecialchars(get_setting('payment_gateway', $pdo)) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">API Key</label>
    <input type="text" name="payment_api_key" class="form-control" value="<?= htmlspecialchars(get_setting('payment_api_key', $pdo)) ?>">
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-warning">Save</button>
    <a href="payment_method.php?reset=1&csrf=<?= urlencode($_SESSION['csrf_payment']) ?>" class="btn btn-outline-danger ms-2" onclick="return confirm('Reset all payment method settings?');">Reset</a>
  </div>
</form>
<?php include '../includes/admin_footer.php'; ?>