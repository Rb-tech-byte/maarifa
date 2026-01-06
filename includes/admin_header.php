<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../admin/login.php');
    exit();
}
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

// Get unread contact messages count
$unread_count = 0;
try {
  if (isset($pdo)) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    $unread_count = $stmt->fetchColumn();
  }
} catch (Throwable $e) {
  $unread_count = 0;
}

$title = isset($title) ? $title : 'Admin - AK23 App';
require_once __DIR__ . '/base.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Admin - AK23 App' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
    <link rel="icon" href="<?= $base ?>/assets/images/ak.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/4ry8bfbbgxzgks2p49gi9djbbp38ulfc52fh5r2lv9q29wyw/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
    // Verify TinyMCE loads
    window.addEventListener('load', function() {
        if (typeof tinymce === 'undefined') {
            console.error('TinyMCE failed to load! Check network tab for errors.');
        } else {
            console.log('TinyMCE loaded successfully, version:', tinymce.majorVersion);
        }
    });
    </script>
    <!-- Note: frame-ancestors can only be set via HTTP headers, not meta tags -->
</head>
<body class="bg-light">
<div class="d-flex min-vh-100 flex-column">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
      <button class="btn btn-outline-light me-2 d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
        <i class="fas fa-bars"></i>
      </button>
      <style>
        .brand-logo { height: 28px; width:auto; border-radius:6px; object-fit:contain; }

        /* Notification Bell Styles */
        .notification-bell {
          position: relative;
        }
        .notification-bell .badge {
          animation: bell-pulse 2s infinite;
        }
        @keyframes bell-pulse {
          0% { transform: scale(1); }
          50% { transform: scale(1.1); }
          100% { transform: scale(1); }
        }
        .dropdown-item:hover {
          background-color: #f8f9fa;
        }
      </style>
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
        <img src="<?= $base ?>/assets/images/ak.png" class="brand-logo" alt="AK Logo">
        <span>AKDOWNLOADS Admin</span>
      </a>
      <div class="ms-auto d-flex align-items-center">
        <!-- Notifications Bell -->
        <div class="dropdown me-3">
          <button class="btn btn-outline-light position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unread_count > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                <?= $unread_count > 99 ? '99+' : $unread_count ?>
                <span class="visually-hidden">unread notifications</span>
              </span>
            <?php endif; ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="min-width: 320px; max-width: 400px;">
            <li><h6 class="dropdown-header">Contact Messages</h6></li>
            <?php
            // Get recent unread messages for dropdown
            $recent_messages = [];
            try {
              if (isset($pdo)) {
                $stmt = $pdo->prepare("
                  SELECT cm.id, cm.subject, cm.name, cm.created_at,
                         CASE WHEN cm.user_id IS NOT NULL THEN u.name ELSE cm.name END as display_name
                  FROM contact_messages cm
                  LEFT JOIN users u ON cm.user_id = u.id
                  WHERE cm.status = 'new'
                  ORDER BY cm.created_at DESC
                  LIMIT 5
                ");
                $stmt->execute();
                $recent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
              }
            } catch (Throwable $e) {
              $recent_messages = [];
            }

            if (!empty($recent_messages)):
              foreach ($recent_messages as $msg):
            ?>
              <li>
                <a class="dropdown-item" href="contact_messages.php?id=<?= $msg['id'] ?>">
                  <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                      <div class="fw-bold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($msg['subject']) ?></div>
                      <small class="text-muted">From: <?= htmlspecialchars($msg['display_name']) ?></small>
                    </div>
                    <small class="text-muted ms-2">
                      <?= htmlspecialchars(date('M d', strtotime($msg['created_at']))) ?>
                    </small>
                  </div>
                </a>
              </li>
            <?php
              endforeach;
            ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center fw-bold" href="contact_messages.php?status=new">View All New Messages (<?= $unread_count ?>)</a></li>
            <?php else: ?>
              <li><span class="dropdown-item text-muted">No new messages</span></li>
            <?php endif; ?>
          </ul>
        </div>

        <img src="https://ui-avatars.com/api/?name=Admin" alt="User Avatar" class="rounded-circle me-2" width="32" height="32">
        <span class="text-white me-3">Admin</span>
        <a href="user_profile.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-user"></i> Profile</a>
        <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>
  </nav>
  <div class="container-fluid flex-grow-1 d-flex p-0">
    <div class="offcanvas-lg offcanvas-start bg-dark text-white" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel" style="width: 240px;">
      <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title" id="adminSidebarLabel">Menu</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body p-0 d-flex flex-column">
        <ul class="nav nav-pills flex-column mb-auto" id="sidebarMenu">
          <?php
          $current_page = basename($_SERVER['PHP_SELF']);
          function isActive($pages) {
            global $current_page;
            if (is_array($pages)) {
              return in_array($current_page, $pages) ? ' active' : '';
            }
            return $current_page === $pages ? ' active' : '';
          }
          ?>
          <li class="nav-item"><a href="index.php" class="nav-link text-white<?= isActive('index.php') ?>"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
          <li><a href="courses.php" class="nav-link text-white<?= isActive('courses.php') ?>"><i class="fas fa-boxes me-2"></i> courses</a></li>
          <li><a href="course_categories.php" class="nav-link text-white<?= isActive('course_categories.php') ?>"><i class="fas fa-tags me-2"></i> Course Categories</a></li>
          <li><a href="manage_payments.php" class="nav-link text-white<?= isActive('manage_payments.php') ?>"><i class="fas fa-credit-card me-2"></i> Payments</a></li>
          <li><a href="orders.php" class="nav-link text-white<?= isActive('orders.php') ?>"><i class="fas fa-shopping-cart me-2"></i> Orders</a></li>
          <li><a href="course_requests.php" class="nav-link text-white<?= isActive('course_requests.php') ?>"><i class="fas fa-clipboard-list me-2"></i> course Requests</a></li>
          <li><a href="users.php" class="nav-link text-white<?= isActive('users.php') ?>"><i class="fas fa-users me-2"></i> Users</a></li>
          <li><a href="instructors_list.php" class="nav-link text-white<?= isActive(['instructors_list.php', 'manage_instructors.php']) ?>"><i class="fas fa-chalkboard-teacher me-2"></i> Instructors</a></li>
          <li><a href="instructor_finances.php" class="nav-link text-white<?= isActive('instructor_finances.php') ?>"><i class="fas fa-money-bill-wave me-2"></i> Instructor Finances</a></li>
          
          <!-- Payment Management Tools (Responsive & Path Fixed) -->
<?php
// Determine base path for admin links
$adminBase = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '' : 'admin/';
?>
<li>
  <a class="nav-link text-white d-flex justify-content-between align-items-center<?= isActive(['fix_pending_payments.php','check_payment_status.php','debug_callback.php']) ?>" data-bs-toggle="collapse" href="#paymentToolsMenu" role="button" aria-expanded="false" aria-controls="paymentToolsMenu">
    <span><i class="fas fa-tools me-2"></i> Payment Tools</span>
    <i class="fas fa-chevron-down small"></i>
  </a>
  <div class="collapse<?= isActive(['fix_pending_payments.php','check_payment_status.php','debug_callback.php']) ? ' show' : '' ?>" id="paymentToolsMenu">
    <ul class="nav flex-column ms-3">
      <li><a href="<?= $adminBase ?>fix_pending_payments.php" class="nav-link text-white<?= isActive('fix_pending_payments.php') ?>"><i class="fas fa-check-circle me-2"></i> Fix Pending Payments</a></li>
      <li><a href="<?= $adminBase ?>check_payment_status.php" class="nav-link text-white<?= isActive('check_payment_status.php') ?>"><i class="fas fa-search me-2"></i> Check Payment Status</a></li>
      <li><a href="<?= $adminBase ?>ipn_logs.php" class="nav-link text-white<?= isActive('ipn_logs.php') ?>"><i class="fas fa-list-alt me-2"></i> IPN Logs</a></li>
      <li><a href="<?= $adminBase ?>mail_outbox.php" class="nav-link text-white<?= isActive('mail_outbox.php') ?>"><i class="fas fa-envelope-open-text me-2"></i> Mail Outbox</a></li>
    
    </ul>
  </div>
</li>

          <li><a href="tickets.php" class="nav-link text-white<?= isActive('tickets.php') ?>"><i class="fas fa-ticket-alt me-2"></i> Tickets</a></li>
          <li><a href="contact_messages.php" class="nav-link text-white<?= isActive('contact_messages.php') ?>"><i class="fas fa-envelope me-2"></i> Contact Messages</a></li>
          <li><a href="files-manager.php" class="nav-link text-white<?= isActive('files-manager.php') ?>"><i class="fas fa-folder-open me-2"></i> Files Manager</a></li>
          <li><a href="logs.php" class="nav-link text-white<?= isActive('logs.php') ?>"><i class="fas fa-file-alt me-2"></i> Download Logs</a></li>
          <li><a href="otp_logs.php" class="nav-link text-white<?= isActive('otp_logs.php') ?>"><i class="fas fa-key me-2"></i> OTP Logs</a></li>
          <li><a href="db_scan.php" class="nav-link text-white<?= isActive('db_scan.php') ?>"><i class="fas fa-database me-2"></i> DB Scanner</a></li>
          <li><a href="user_profile.php" class="nav-link text-white<?= isActive('user_profile.php') ?>"><i class="fas fa-user-circle me-2"></i> My Profile</a></li>
          
                   <!-- General Settings Dropdown -->
          <li>
            <a class="nav-link text-white d-flex justify-content-between align-items-center<?= isActive(['mails_smtp.php','sms_provider.php','payment_method.php','system_name.php','system_logo.php','files_storage_provider.php']) ?>" data-bs-toggle="collapse" href="#settingsMenu" role="button" aria-expanded="false" aria-controls="settingsMenu">
              <span><i class="fas fa-cogs me-2"></i> General Settings</span>
              <i class="fas fa-chevron-down small"></i>
            </a>
            <div class="collapse<?= isActive(['mails_smtp.php','sms_provider.php','payment_method.php','system_name.php','system_logo.php','files_storage_provider.php']) ? ' show' : '' ?>" id="settingsMenu">
              <ul class="nav flex-column ms-3">
                <li><a href="mails_smtp.php" class="nav-link text-white<?= isActive('mails_smtp.php') ?>"><i class="fas fa-envelope me-2"></i> Email SMTP</a></li>
                <li><a href="sms_provider.php" class="nav-link text-white<?= isActive('sms_provider.php') ?>"><i class="fas fa-sms me-2"></i> SMS Provider</a></li>
                <li><a href="chat_widget.php" class="nav-link text-white<?= isActive('chat_widget.php') ?>"><i class="fas fa-comments me-2"></i> Chat Widget</a></li>
                <li><a href="payment_method.php" class="nav-link text-white<?= isActive('payment_method.php') ?>"><i class="fas fa-money-check-alt me-2"></i> Payment Methods</a></li>
                <li><a href="system_name.php" class="nav-link text-white<?= isActive('system_name.php') ?>"><i class="fas fa-font me-2"></i> System Name</a></li>
                <li><a href="system_logo.php" class="nav-link text-white<?= isActive('system_logo.php') ?>"><i class="fas fa-image me-2"></i> System Logo</a></li>
                <li><a href="files_storage_provider.php" class="nav-link text-white<?= isActive('files_storage_provider.php') ?>"><i class="fas fa-cloud-upload-alt me-2"></i> Storage Provider</a></li>
              </ul>
            </div>
          </li>
        </ul>
      </div>
    </div>
    <main class="flex-grow-1 p-3">