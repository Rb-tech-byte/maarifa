<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/sms_provider.php';
require_once '../includes/auth.php';

checkAdminAuth();

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) UNIQUE, setting_value TEXT)");

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('ak23_csrf_validate')) { require_once '../includes/security.php'; }
    if (!ak23_csrf_validate()) { $error = 'Invalid CSRF token.'; }
    else {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $twilio_sid = trim($_POST['twilio_sid']);
        $twilio_token = trim($_POST['twilio_token']);
        $twilio_from = trim($_POST['twilio_from']);
        $sms_api_url = trim($_POST['sms_api_url']);
        $twilio_sms_template = trim($_POST['twilio_sms_template']);
        $settings = [
            'twilio_sid' => $twilio_sid,
            'twilio_token' => $twilio_token,
            'twilio_from' => $twilio_from,
            'sms_api_url' => $sms_api_url,
            'twilio_sms_template' => $twilio_sms_template
        ];
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value]);
        }
        $success = 'Twilio SMS settings saved.';
    } elseif ($action === 'test_sms') {
        $to = trim((string)($_POST['test_phone'] ?? ''));
        $msg = trim((string)($_POST['test_message'] ?? 'Test SMS from AK23'));
        if ($to === '') { $error = 'Enter a test phone number.'; }
        else {
            if (function_exists('cleanPhoneNumber')) { $to = cleanPhoneNumber($to); }
            $ok = @ak23_sms_send($to, $msg);
            if ($ok) { $success = 'Test SMS sent to ' . htmlspecialchars($to) . '.'; }
            else { $error = 'Failed to send Test SMS. Check credentials and phone format.'; }
        }
    }
    }
}
if (isset($_GET['reset'])) {
    $pdo->exec("DELETE FROM settings WHERE setting_key LIKE 'twilio_%' OR setting_key = 'sms_api_url'");
    $success = 'Twilio SMS settings reset.';
}
function get_setting($key, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}
$title = 'SMS Provider (Twilio) - AK23 App';
include '../includes/admin_header.php';
?>
<style>
    .form-label, .form-control, .form-select, .form-check-label {
        color: #000 !important;
    }
</style>
<h1 class="h3 mb-3" style="color:#000;"><i class="fas fa-sms me-2"></i>SMS Provider (Twilio)</h1>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3 mb-3">
  <input type="hidden" name="action" value="save">
  <?= function_exists('ak23_csrf_input') ? ak23_csrf_input() : '' ?>
  <div class="col-md-6">
    <label class="form-label">Twilio Account SID</label>
    <input type="text" name="twilio_sid" class="form-control" value="<?= htmlspecialchars(get_setting('twilio_sid', $pdo)) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Twilio Auth Token</label>
    <input type="text" name="twilio_token" class="form-control" value="<?= htmlspecialchars(get_setting('twilio_token', $pdo)) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Twilio From Number</label>
    <input type="text" name="twilio_from" class="form-control" value="<?= htmlspecialchars(get_setting('twilio_from', $pdo)) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Twilio API URL</label>
    <input type="text" name="sms_api_url" class="form-control" value="<?= htmlspecialchars(get_setting('sms_api_url', $pdo)) ?>">
  </div>
  <div class="col-12">
    <label class="form-label">SMS Template</label>
    <textarea name="twilio_sms_template" class="form-control" rows="3" placeholder="e.g. Your code is {{code}}. Thank you!"><?= htmlspecialchars(get_setting('twilio_sms_template', $pdo)) ?></textarea>
    <div class="form-text">You can use placeholders like <code>{{code}}</code>, <code>{{name}}</code>, etc.</div>
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-warning">Save</button>
    <a href="sms_provider.php?reset=1" class="btn btn-outline-danger ms-2" onclick="return confirm('Reset all Twilio SMS settings?');">Reset</a>
  </div>
</form>

<!-- Send Test SMS -->
<div class="card bg-light border-0 mb-3">
  <div class="card-body">
    <h5 class="card-title" style="color:#000;">Send Test SMS</h5>
    <form method="POST" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="test_sms">
      <?= function_exists('ak23_csrf_input') ? ak23_csrf_input() : '' ?>
      <div class="col-sm-4">
        <label class="form-label">Phone</label>
        <input type="text" name="test_phone" class="form-control" placeholder="e.g. +2557xxxxxxx">
      </div>
      <div class="col-sm-6">
        <label class="form-label">Message</label>
        <input type="text" name="test_message" class="form-control" placeholder="Test message" value="Test SMS from AK23">
      </div>
      <div class="col-sm-2">
        <button type="submit" class="btn btn-success w-100">Send Test</button>
      </div>
    </form>
  </div>
  </div>
<?php include '../includes/admin_footer.php'; ?> 