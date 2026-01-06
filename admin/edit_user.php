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

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}
$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: users.php');
    exit();
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    if ($name && $email) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
            $ok = $stmt->execute([$name, $email, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $ok = $stmt->execute([$name, $email, $id]);
        }
        if ($ok) {
            header('Location: users.php?success=1');
            exit();
        } else {
            $error = 'Failed to update user.';
        }
    } else {
        $error = 'Name and email are required.';
    }
}
$title = 'Edit User - AK23 App';
include '../includes/admin_header.php';
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<h1 class="h3 mb-3" style="color:#000;">Edit User</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3" autocomplete="off">
  <div class="col-md-6">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Password (leave blank to keep current)</label>
    <input type="password" name="password" class="form-control">
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-primary">Update User</button>
    <a href="users.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
<?php include '../includes/admin_footer.php'; ?> 