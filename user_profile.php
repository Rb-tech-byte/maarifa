<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    // Handle avatar upload
    $avatar_path = $user['avatar'] ?? '';
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/thumbnails/';
        $fileName = uniqid() . '_' . basename($_FILES['avatar']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
            $avatar_path = $targetPath;
        } else {
            $error = 'Failed to upload avatar.';
        }
    }
    if (!$error) {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, avatar = ?, address = ?, bio = ? WHERE id = ?");
        if ($stmt->execute([$username, $email, $avatar_path, $address, $bio, $user_id])) {
            $success = 'Profile updated successfully.';
            $_SESSION['username'] = $username;
        } else {
            $error = 'Failed to update profile.';
        }
    }
}
// Password change
$pw_success = '';
$pw_error = '';
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_new_password'];
    if (!$current || !$new || !$confirm) {
        $pw_error = 'All password fields are required.';
    } elseif (!password_verify($current, $user['password'])) {
        $pw_error = 'Current password is incorrect.';
    } elseif ($new !== $confirm) {
        $pw_error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hash, $user_id])) {
            $pw_success = 'Password changed successfully.';
        } else {
            $pw_error = 'Failed to change password.';
        }
    }
}
$title = 'My Profile - AK23 App';
include 'includes/header.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-4">My Profile</h1>
  <?php if ($success): ?><div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Avatar</label><br>
      <?php if (!empty($user['avatar'])): ?>
        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="img-thumbnail mb-2" style="max-width:100px;">
      <?php endif; ?>
      <input type="file" name="avatar" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Address</label>
      <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
    </div>
    <div class="col-12">
      <label class="form-label">Bio</label>
      <textarea name="bio" class="form-control" rows="2"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Update Profile</button>
    </div>
  </form>
  <hr>
  <h2 class="h5 mt-4">Change Password</h2>
  <?php if ($pw_success): ?><div class="alert alert-success"> <?= htmlspecialchars($pw_success) ?> </div><?php endif; ?>
  <?php if ($pw_error): ?><div class="alert alert-danger"> <?= htmlspecialchars($pw_error) ?> </div><?php endif; ?>
  <form method="POST" class="row g-3">
    <input type="hidden" name="change_password" value="1">
    <div class="col-md-4">
      <label class="form-label">Current Password</label>
      <input type="password" name="current_password" class="form-control" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">New Password</label>
      <input type="password" name="new_password" class="form-control" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Confirm New Password</label>
      <input type="password" name="confirm_new_password" class="form-control" required>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-warning">Change Password</button>
    </div>
  </form>
</div>
<?php include 'includes/footer.php'; ?> 