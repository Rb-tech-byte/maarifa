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
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    if ($username && $email && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $email, $hash])) {
            header('Location: users.php?success=1');
            exit();
        } else {
            $error = 'Failed to add user.';
        }
    } else {
        $error = 'All fields are required.';
    }
}
 $title = 'Add User - AK23 App';
 include '../includes/admin_header.php';
 ?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<h1 class="h3 mb-3" style="color:#000;">Add User</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3" autocomplete="off">
  <div class="col-md-6">
    <label class="form-label">Username</label>
    <input type="text" name="username" class="form-control" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Password</label>
    <input type="password" name="password" class="form-control" required>
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-primary">Add User</button>
    <a href="users.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
<?php include '../includes/admin_footer.php'; ?> 