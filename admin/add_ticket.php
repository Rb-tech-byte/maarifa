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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $status = $_POST['status'] ?? 'open';

    if ($user_id && $subject && $message) {
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, message, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        if ($stmt->execute([$user_id, $subject, $message, $status])) {
            header('Location: tickets.php?success=1');
            exit();
        } else {
            $error = 'Failed to add ticket.';
        }
    } else {
        $error = 'User, subject, and message are required.';
    }
}

$users_stmt = $pdo->query("SELECT id, username, email FROM users WHERE is_admin = 0 ORDER BY username ASC");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Add Ticket - AK23 App';
include '../includes/admin_header.php';
?>
<style>
    .form-label, .form-control, .form-select, .form-check-label {
        color: #000 !important;
    }
</style>
<h1 class="h3 mb-3" style="color:#000;">Add Ticket</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3" autocomplete="off">
  <div class="col-md-6">
    <label for="user_id" class="form-label">User</label>
    <select id="user_id" name="user_id" class="form-select" required>
        <option value="" disabled selected>Select a user</option>
        <?php foreach ($users as $user): ?>
            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
        <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-6">
    <label for="subject" class="form-label">Subject</label>
    <input type="text" id="subject" name="subject" class="form-control" required>
  </div>
  <div class="col-12">
    <label for="message" class="form-label">Message</label>
    <textarea id="message" name="message" class="form-control rte" rows="5" required></textarea>
  </div>
  <div class="col-md-4">
    <label for="status" class="form-label">Status</label>
    <select id="status" name="status" class="form-select">
      <option value="open">Open</option>
      <option value="in_progress">In Progress</option>
      <option value="closed">Closed</option>
    </select>
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-primary">Add Ticket</button>
    <a href="tickets.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
<script>
  tinymce.init({
    selector: 'textarea.rte',
    plugins: 'lists link image table',
    toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image',
    menubar: false
  });
</script>
<?php include '../includes/admin_footer.php'; ?>