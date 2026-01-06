<?php
session_start();
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/functions.php';
$title = 'Request a course';

// Require login before rendering the page
if (empty($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
  $_SESSION['flash_error'] = 'Please log in to request a course.';
  header('Location: login.php');
  exit();
}
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
require_once __DIR__ . '/includes/header.php';
// Calculate remaining requests for today
$dailyLimit = 3;
$remaining = $dailyLimit;
try {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_requests WHERE user_id = ? AND created_at >= CURDATE() AND created_at < (CURDATE() + INTERVAL 1 DAY)");
  $stmt->execute([$_SESSION['user_id']]);
  $countToday = (int)$stmt->fetchColumn();
  $remaining = max(0, $dailyLimit - $countToday);
} catch (Throwable $e) {
  $remaining = 0; // be safe
}
?>
<div class="container" style="max-width: 720px;">
  <h1 class="h3 mb-3">Request a course</h1>
  <div class="alert alert-info mb-3">
    You can submit up to <strong><?php echo (int)$dailyLimit; ?></strong> requests per day. Remaining today: <strong><?php echo (int)$remaining; ?></strong>.
  </div>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <form method="post" action="request_course_submit.php" class="needs-validation" novalidate <?php echo $remaining <= 0 ? 'onsubmit="return false;"' : ''; ?>>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="device_fingerprint" id="device_fingerprint" value="">
    <div class="mb-3">
      <label class="form-label">course Name</label>
      <input type="text" name="course_name" class="form-control" required <?php echo $remaining <= 0 ? 'disabled' : ''; ?>>
      <div class="invalid-feedback">Please enter the course name</div>
    </div>
    <div class="mb-3">
      <label class="form-label">course Link (optional)</label>
      <input type="url" name="course_link" class="form-control" placeholder="https://..." <?php echo $remaining <= 0 ? 'disabled' : ''; ?>>
    </div>
    <div class="mb-3">
      <label class="form-label">Category (optional)</label>
      <input type="text" name="category" class="form-control" placeholder="e.g., Plugins, Samples" <?php echo $remaining <= 0 ? 'disabled' : ''; ?>>
    </div>
    <div class="mb-3">
      <label class="form-label">Details</label>
      <textarea id="details" name="details" class="form-control" rows="8" placeholder="Describe what you need" <?php echo $remaining <= 0 ? 'disabled' : ''; ?>></textarea>
    </div>
    <button type="submit" class="btn btn-warning" <?php echo $remaining <= 0 ? 'disabled' : ''; ?>>Submit Request</button>
  </form>
</div>
<script>
(function(){
  var forms=document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function(form){
    form.addEventListener('submit',function(e){
      if(!form.checkValidity()){e.preventDefault();e.stopPropagation();}
      form.classList.add('was-validated');
    },false);
  });
})();
</script>
<!-- TinyMCE (Rich Text Editor) via CDN -->
<script src="https://cdn.tiny.cloud/1/4u6o2mz2yxh8t5wkmid20clr1747ciygh59dm09n7f03ibcj/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: '#details',
    menubar: false,
    statusbar: false,
    plugins: 'lists link autolink codesample table emoticons',
    toolbar: 'undo redo | bold italic underline | bullist numlist | link emoticons | removeformat',
    branding: false,
    height: 260
  });
  // Ensure TinyMCE content is validated (required if you make details required later)
</script>
<!-- FingerprintJS (device fingerprint) -->
<script src="https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@3/dist/fp.min.js"></script>
<script>
  (function(){
    if (window.FingerprintJS) {
      FingerprintJS.load().then(fp => fp.get()).then(result => {
        var v = (result && result.visitorId) ? result.visitorId : '';
        var el = document.getElementById('device_fingerprint');
        if (el) { el.value = v; }
      }).catch(function(){});
    }
  })();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
