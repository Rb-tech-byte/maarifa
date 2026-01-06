<?php
// Secure session flags
if (PHP_SAPI !== 'cli') {
  @ini_set('session.cookie_httponly', '1');
  @ini_set('session.cookie_samesite', 'Lax');
  if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
    @ini_set('session.cookie_secure', '1');
  }
}
session_start();
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
if (!isset($_SESSION['user_logged_in']) || (($_SESSION['role'] ?? '') !== 'user')) {
  header('Location: login.php');
  exit;
}

$title = 'My Profile - AK23 App';
include __DIR__ . '/user_header.php';
include __DIR__ . '/user_sidebar.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// CSRF token
if (empty($_SESSION['csrf_profile'])) {
  $_SESSION['csrf_profile'] = bin2hex(random_bytes(16));
}

$error = '';
$success = '';
$pw_error = '';
$pw_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = (string)($_POST['csrf'] ?? '');
  if (!hash_equals($_SESSION['csrf_profile'], $csrf)) {
    $error = 'Invalid request. Please refresh and try again.';
  }
  // Honeypot
  $hp = trim((string)($_POST['website'] ?? ''));
  if ($error === '' && $hp !== '') { $error = 'Invalid request.'; }

  // Profile/contact update
  if ($error === '' && isset($_POST['update_profile'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '') { $email = function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email); }
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($username === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please provide a valid username and email.';
    }

    // Ensure columns exist (avatar/phone may not exist on older schema)
    try {
      $c = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
      if (!$c) { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL"); }
      $c2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'")->fetch();
      if (!$c2) { $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL"); }
      $c3 = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'")->fetch();
      if (!$c3) { $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(191) NULL"); }
    } catch (Throwable $e) {
      // continue; schema will be best-effort
    }

    // Check email is unique for other users
    if ($error === '') {
      $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
      $check->execute([$email, $user_id]);
      if ($check->fetch(PDO::FETCH_ASSOC)) {
        $error = 'That email is already in use by another account.';
      }
    }

    // Handle avatar upload
    $avatar_path = (string)($user['avatar'] ?? '');
    if ($error === '' && isset($_FILES['avatar']) && is_array($_FILES['avatar']) && ($_FILES['avatar']['error'] === UPLOAD_ERR_OK)) {
      $uploadDir = __DIR__ . '/uploads/thumbnails/';
      if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
      $orig = (string)$_FILES['avatar']['name'];
      $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      $allowed = ['jpg','jpeg','png','gif','webp'];
      if (!in_array($ext, $allowed, true)) {
        $error = 'Unsupported image type. Use JPG, PNG, GIF, or WEBP.';
      } else {
        $fileName = uniqid('ava_', true) . '.' . $ext;
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
          $avatar_path = 'uploads/thumbnails/' . $fileName;
        } else {
          $error = 'Failed to upload avatar.';
        }
      }
    }

    if ($error === '') {
      $upd = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, avatar = ? WHERE id = ?');
      if ($upd->execute([$username, $email, $phone, $avatar_path, $user_id])) {
        $success = 'Profile updated successfully.';
        $_SESSION['username'] = $username;
        $user['username'] = $username; $user['email'] = $email; $user['phone'] = $phone; $user['avatar'] = $avatar_path;
      } else {
        $error = 'Failed to update profile.';
      }
    }
  }

  // Password change
  if ($error === '' && isset($_POST['change_password'])) {
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_new_password'] ?? '');
    if ($new === '' || $confirm === '') {
      $pw_error = 'New password and confirmation are required.';
    } elseif ($new !== $confirm) {
      $pw_error = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
      $pw_error = 'Password must be at least 6 characters.';
    } else {
      $hash = password_hash($new, PASSWORD_DEFAULT);
      $u = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
      if ($u->execute([$hash, $user_id])) {
        $pw_success = 'Password changed successfully.';
      } else {
        $pw_error = 'Failed to change password.';
      }
    }
  }
}
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
  <h2 class="h4 mb-4" style="color:black;">My Profile</h2>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_profile']) ?>">
    <input type="hidden" name="update_profile" value="1">
    <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
      <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <div class="col-md-6">
      <label class="form-label" style="color:black;">Username</label>
      <input type="text" name="username" class="form-control" value="<?= htmlspecialchars((string)($user['username'] ?? '')) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label" style="color:black;">Email</label>
      <input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)($user['email'] ?? '')) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label" style="color:black;">Phone</label>
      <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars((string)($user['phone'] ?? '')) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" style="color:black;">Profile Picture</label><br>
      <?php if (!empty($user['avatar'])): ?>
        <img src="<?= htmlspecialchars((string)$user['avatar']) ?>" alt="Avatar" class="img-thumbnail mb-2" style="max-width:100px;">
      <?php endif; ?>
      <input type="file" name="avatar" class="form-control">
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
  <hr>
  <h2 class="h5 mt-4" style="color:black;">Change Password</h2>
  <?php if ($pw_success): ?><div class="alert alert-success"><?= htmlspecialchars($pw_success) ?></div><?php endif; ?>
  <?php if ($pw_error): ?><div class="alert alert-danger"><?= htmlspecialchars($pw_error) ?></div><?php endif; ?>
  <form method="POST" class="row g-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_profile']) ?>">
    <input type="hidden" name="change_password" value="1">
    <div class="col-md-6">
      <label class="form-label" style="color:black;">New Password</label>
      <input type="password" name="new_password" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label" style="color:black;">Confirm New Password</label>
      <input type="password" name="confirm_new_password" class="form-control" required>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-warning">Change Password</button>
    </div>
  </form>
</main>
<?php include __DIR__ . '/user_footer.php'; ?>
