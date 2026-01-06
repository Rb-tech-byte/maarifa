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

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';
$pw_error = '';
$pw_success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email'] ?? '');
    
    // Validate username
    if (empty($username)) {
        $error = 'Username is required.';
    } else {
        // Check if username already exists (excluding current admin)
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $admin_id]);
        if ($stmt->fetch()) {
            $error = 'Username already exists.';
        } else {
            // Update admin profile
            $stmt = $pdo->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
            if ($stmt->execute([$username, $admin_id])) {
                $success = 'Profile updated successfully.';
                // Update session
                $_SESSION['admin_username'] = $username;
                // Refresh admin data
                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update profile.';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $pw_error = 'All password fields are required.';
    } elseif (!password_verify($current_password, $admin['password'])) {
        $pw_error = 'Current password is incorrect.';
    } elseif ($new_password !== $confirm_password) {
        $pw_error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $pw_error = 'New password must be at least 6 characters long.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed_password, $admin_id])) {
            $pw_success = 'Password changed successfully.';
        } else {
            $pw_error = 'Failed to change password.';
        }
    }
}

$title = 'Admin Profile - AK23 App';
include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-3"><i class="fas fa-user-circle me-2"></i>Admin Profile</h1>
            
            <!-- Profile Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0" style="color:#000;">
                        <i class="fas fa-user me-2"></i>Profile Information
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Username</label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?= htmlspecialchars($admin['username']) ?>" required>
                            <div class="form-text">Your admin username for login</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($admin['email'] ?? 'admin@example.com') ?>" 
                                   placeholder="admin@example.com" disabled>
                            <div class="form-text text-muted">Email functionality coming soon</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Created</label>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars(date('F j, Y', strtotime($admin['created_at']))) ?>" 
                                   readonly>
                            <div class="form-text">When your admin account was created</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Last Updated</label>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars(date('F j, Y g:i A', strtotime($admin['created_at']))) ?>" 
                                   readonly>
                            <div class="form-text">Last time your profile was updated</div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Password Change Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0" style="color:#000;">
                        <i class="fas fa-lock me-2"></i>Change Password
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($pw_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($pw_success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($pw_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($pw_error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                            <div class="form-text">Enter your current password</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">New Password</label>
                            <input type="password" name="new_password" class="form-control" 
                                   minlength="6" required>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   minlength="6" required>
                            <div class="form-text">Re-enter your new password</div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Security Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Use a strong password with at least 6 characters</li>
                            <li>Include a mix of letters, numbers, and special characters</li>
                            <li>Never share your password with anyone</li>
                            <li>Change your password regularly for better security</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?> 