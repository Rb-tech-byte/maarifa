<?php
require_once '../includes/security.php';
if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit(); }
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
checkAdminAuth();

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) UNIQUE, setting_value TEXT)");

$success = '';
$error = '';

function get_setting_val(PDO $pdo, string $key, string $default=''): string {
  $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
  $st->execute([$key]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ? (string)$row['setting_value'] : $default;
}

if (empty($_SESSION['csrf_chat_widget'])) { $_SESSION['csrf_chat_widget'] = bin2hex(random_bytes(16)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tok = (string)($_POST['csrf'] ?? '');
  if (!hash_equals($_SESSION['csrf_chat_widget'], $tok)) { $error = 'Invalid CSRF token.'; }
  else {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
      $enabled = isset($_POST['wa_enabled']) ? '1' : '0';
      $phone = preg_replace('/\D+/', '', (string)($_POST['wa_phone'] ?? ''));
      $label = trim((string)($_POST['wa_label'] ?? 'help'));
      $text  = trim((string)($_POST['wa_text'] ?? 'Hello! I need assistance on AK23DOWNLOADS.'));
      $pairs = [ 'wa_enabled' => $enabled, 'wa_phone' => $phone, 'wa_label' => $label, 'wa_text' => $text ];
      foreach ($pairs as $k=>$v) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$k, $v]);
      }
      $success = 'WhatsApp widget settings saved.';
    } elseif ($action === 'reset') {
      $pdo->exec("DELETE FROM settings WHERE setting_key IN ('wa_enabled','wa_phone','wa_label','wa_text')");
      $success = 'WhatsApp widget settings reset.';
    }
  }
}

$title = 'WhatsApp Chat Widget - AK23 Admin';
include '../includes/admin_header.php';
$currEnabled = get_setting_val($pdo,'wa_enabled','1') === '1';
$currPhone   = get_setting_val($pdo,'wa_phone','255763312251');
$currLabel   = get_setting_val($pdo,'wa_label','help');
$currText    = get_setting_val($pdo,'wa_text','Hello! I need assistance on AK23DOWNLOADS.');
?>
<div class="container-fluid p-3">
  <h1 class="h4 mb-3">WhatsApp Chat Widget</h1>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST" class="row g-3">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_chat_widget']) ?>">
    <div class="col-12">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="wa_enabled" name="wa_enabled" <?= $currEnabled ? 'checked' : '' ?>>
        <label class="form-check-label" for="wa_enabled">Enable WhatsApp floating button</label>
      </div>
    </div>
    <div class="col-md-6">
      <label class="form-label">WhatsApp Phone (international, digits only)</label>
      <input type="text" class="form-control" name="wa_phone" value="<?= htmlspecialchars($currPhone) ?>" placeholder="255763312251">
      <div class="form-text">Example: 255763312251 for +255 763 312 251</div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Badge Label (small text under WhatsApp)</label>
      <input type="text" class="form-control" name="wa_label" value="<?= htmlspecialchars($currLabel) ?>" placeholder="help">
    </div>
    <div class="col-12">
      <label class="form-label">Default Message</label>
      <input type="text" class="form-control" name="wa_text" value="<?= htmlspecialchars($currText) ?>">
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-warning">Save</button>
      <button type="submit" class="btn btn-outline-danger ms-2" name="action" value="reset" onclick="return confirm('Reset chat widget settings?');">Reset</button>
    </div>
  </form>
</div>
<?php include '../includes/admin_footer.php'; ?>
