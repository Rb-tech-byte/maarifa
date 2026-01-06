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
    $phone = trim($_POST['phone']);
    $contact = trim($_POST['contact']);
    if ($phone) {
        $stmt = $pdo->prepare("UPDATE users SET phone = ?, contact = ? WHERE id = ?");
        if ($stmt->execute([$phone, $contact, $user_id])) {
            $success = 'Contact info updated successfully.';
        } else {
            $error = 'Failed to update contact info.';
        }
    } else {
        $error = 'Phone number is required.';
    }
}
$title = 'Contact Info - AK23 App';
include 'includes/header.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-4">Contact Info</h1>
  <?php if ($success): ?><div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div><?php endif; ?>
  <form method="POST" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Phone</label>
      <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Contact Info</label>
      <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($user['contact'] ?? '') ?>">
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Update Contact</button>
    </div>
  </form>
</div>
<?php include 'includes/footer.php'; ?> 