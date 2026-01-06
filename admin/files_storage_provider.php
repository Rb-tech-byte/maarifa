<?php
session_start();
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storage_provider = trim($_POST['storage_provider']);
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['storage_provider', $storage_provider]);
    $success = 'Storage provider saved.';
}
if (isset($_GET['reset'])) {
    $pdo->exec("DELETE FROM settings WHERE setting_key = 'storage_provider'");
    $success = 'Storage provider reset.';
}
function get_setting($key, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}
$title = 'Storage Provider - AK23 App';
include '../includes/admin_header.php';
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<h1 class="h3 mb-3" style="color:#000;"><i class="fas fa-cloud-upload-alt me-2"></i>Storage Provider</h1>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3 mb-3">
  <div class="col-md-6">
    <label class="form-label">Storage Provider (e.g., Google Drive, AWS S3, Local)</label>
    <input type="text" name="storage_provider" class="form-control" value="<?= htmlspecialchars(get_setting('storage_provider', $pdo)) ?>">
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-warning">Save</button>
    <a href="files_storage_provider.php?reset=1" class="btn btn-outline-danger ms-2" onclick="return confirm('Reset storage provider?');">Reset</a>
  </div>
</form>
<?php include '../includes/admin_footer.php'; ?> 