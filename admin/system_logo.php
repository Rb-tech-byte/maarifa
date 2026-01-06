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
$logo_dir = '../uploads/';

function get_setting($key, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}
$validKeys = [
  'system_logo' => 'Default',
  'system_logo_light' => 'Light (for dark headers)',
  'system_logo_small' => 'Small / Favicon',
  'system_logo_large' => 'Large'
];

function save_uploaded_logo($key, $fileField, $logo_dir, $pdo, &$success, &$error) {
  if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
    $error = 'No file selected or upload error.';
    return;
  }
  // Validate MIME and size (max 2MB), allow SVG for logos
  $valErr = null;
  $v = ak23_validate_image_upload($_FILES[$fileField], 2*1024*1024, true, $valErr);
  if ($v === false) { $error = $valErr ?: 'Invalid file upload.'; return; }
  $ext = $v['ext'];
  $origName = basename((string)$_FILES[$fileField]['name']);
  $safeName = preg_replace('/[^a-zA-Z0-9._-]/','_', $origName);
  $name = $key . '_' . time() . '_' . $safeName;
  $target = rtrim($logo_dir,'/').'/'.$name;
  if (!is_dir($logo_dir)) @mkdir($logo_dir, 0775, true);
  if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $target)) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $name]);
    $success = ucfirst(str_replace('_',' ', $key)) . ' uploaded.';
  } else {
    $error = 'Failed to upload file.';
  }
}

// Handle uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF validation
  if (empty($_POST['csrf']) || !hash_equals((string)($_SESSION['csrf_logo'] ?? ''), (string)$_POST['csrf'])) {
    $error = 'Invalid CSRF token.';
  } else {
  foreach ($validKeys as $k => $label) {
    $submit = 'upload_' . $k;
    $field = $k; // use same name for file input
    if (isset($_POST[$submit])) {
      save_uploaded_logo($k, $field, $logo_dir, $pdo, $success, $error);
    }
  }
  }
}

// Handle resets
if (isset($_GET['reset'])) {
  // Require CSRF in query for reset actions
  if (empty($_GET['csrf']) || !hash_equals((string)($_SESSION['csrf_logo'] ?? ''), (string)$_GET['csrf'])) {
    $error = 'Invalid CSRF token.';
  } else {
  $k = $_GET['key'] ?? 'system_logo';
  if (!isset($validKeys[$k])) $k = 'system_logo';
  $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
  $stmt->execute([$k]);
  $row = $stmt->fetch();
  if ($row && file_exists($logo_dir . $row['setting_value'])) {
    @unlink($logo_dir . $row['setting_value']);
  }
  $del = $pdo->prepare("DELETE FROM settings WHERE setting_key = ?");
  $del->execute([$k]);
  $success = ucfirst(str_replace('_',' ', $k)) . ' reset.';
  }
}
$title = 'System Logo - AK23 App';
include '../includes/admin_header.php';
$csrf_logo = $_SESSION['csrf_logo'] = $_SESSION['csrf_logo'] ?? bin2hex(random_bytes(16));
$logoDefault = get_setting('system_logo', $pdo);
$logoLight = get_setting('system_logo_light', $pdo);
$logoSmall = get_setting('system_logo_small', $pdo);
$logoLarge = get_setting('system_logo_large', $pdo);
?>
<h1 class="h3 mb-3" style="color:#000;"><i class="fas fa-image me-2"></i>System Logo</h1>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="row g-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Default Logo</h5>
        <?php if ($logoDefault): ?><div class="mb-2"><img src="../uploads/<?= htmlspecialchars($logoDefault) ?>" alt="Default Logo" style="max-height:80px;"></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_logo) ?>">
          <input type="file" name="system_logo" class="form-control mb-2" required>
          <button type="submit" name="upload_system_logo" class="btn btn-warning">Upload</button>
          <a href="system_logo.php?reset=1&key=system_logo&csrf=<?= urlencode($csrf_logo) ?>" class="btn btn-outline-danger ms-2" onclick="return confirm('Reset default logo?');">Reset</a>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Light Logo (for dark headers)</h5>
        <?php if ($logoLight): ?><div class="mb-2"><img src="../uploads/<?= htmlspecialchars($logoLight) ?>" alt="Light Logo" style="max-height:80px;"></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_logo) ?>">
          <input type="file" name="system_logo_light" class="form-control mb-2" required>
          <button type="submit" name="upload_system_logo_light" class="btn btn-warning">Upload</button>
          <a href="system_logo.php?reset=1&key=system_logo_light&csrf=<?= urlencode($csrf_logo) ?>" class="btn btn-outline-danger ms-2" onclick="return confirm('Reset light logo?');">Reset</a>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Small Logo / Favicon</h5>
        <?php if ($logoSmall): ?><div class="mb-2"><img src="../uploads/<?= htmlspecialchars($logoSmall) ?>" alt="Small Logo" style="max-height:80px;"></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_logo) ?>">
          <input type="file" name="system_logo_small" class="form-control mb-2" required>
          <button type="submit" name="upload_system_logo_small" class="btn btn-warning">Upload</button>
          <a href="system_logo.php?reset=1&key=system_logo_small&csrf=<?= urlencode($csrf_logo) ?>" class="btn btn-outline-danger ms-2" onclick="return confirm('Reset small logo?');">Reset</a>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Large Logo</h5>
        <?php if ($logoLarge): ?><div class="mb-2"><img src="../uploads/<?= htmlspecialchars($logoLarge) ?>" alt="Large Logo" style="max-height:80px;"></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_logo) ?>">
          <input type="file" name="system_logo_large" class="form-control mb-2" required>
          <button type="submit" name="upload_system_logo_large" class="btn btn-warning">Upload</button>
          <a href="system_logo.php?reset=1&key=system_logo_large&csrf=<?= urlencode($csrf_logo) ?>" class="btn btn-outline-danger ms-2" onclick="return confirm('Reset large logo?');">Reset</a>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/admin_footer.php'; ?> 