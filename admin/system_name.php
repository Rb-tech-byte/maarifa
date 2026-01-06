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
    try {
        $pairs = [];
        if (isset($_POST['system_name'])) {
            $pairs['system_name'] = trim((string)$_POST['system_name']);
        }
        if (isset($_POST['system_name_short'])) {
            $pairs['system_name_short'] = trim((string)$_POST['system_name_short']);
        }
        if ($pairs) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($pairs as $k => $v) { $stmt->execute([$k, $v]); }
            $success = 'Settings saved.';
        }
    } catch (Throwable $e) {
        $error = 'Failed to save settings.';
    }
}
if (isset($_GET['reset'])) {
    $key = $_GET['key'] ?? 'system_name';
    if (!in_array($key, ['system_name','system_name_short'], true)) { $key = 'system_name'; }
    $del = $pdo->prepare("DELETE FROM settings WHERE setting_key = ?");
    $del->execute([$key]);
    $success = ucfirst(str_replace('_',' ', $key)) . ' reset.';
}
function get_setting($key, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}
$title = 'System Name - AK23 App';
include '../includes/admin_header.php';
?>
<h1 class="h3 mb-3 text-dark"><i class="fas fa-font me-2"></i>System Name</h1>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3 mb-3">
  <div class="col-md-6">
    <label class="form-label text-secondary">System Name (Full)</label>
    <input type="text" name="system_name" class="form-control" value="<?= htmlspecialchars(get_setting('system_name', $pdo)) ?>" placeholder="e.g., AK23 App">
    <a href="system_name.php?reset=1&key=system_name" class="btn btn-sm btn-outline-danger mt-2" onclick="return confirm('Reset full system name?');">Reset Full Name</a>
  </div>
  <div class="col-md-6">
    <label class="form-label text-secondary">System Name (Short / Brand Text)</label>
    <input type="text" name="system_name_short" class="form-control" value="<?= htmlspecialchars(get_setting('system_name_short', $pdo)) ?>" placeholder="e.g., AK23DOWNLOADS">
    <a href="system_name.php?reset=1&key=system_name_short" class="btn btn-sm btn-outline-danger mt-2" onclick="return confirm('Reset short system name?');">Reset Short Name</a>
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-warning">Save</button>
  </div>
  <div class="col-12 text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Full name is used in the HTML title. Short name is used in the navbar brand text.
  </div>
</form>
<?php include '../includes/admin_footer.php'; ?> 