<?php
$current_page = basename($_SERVER['PHP_SELF']);
function isUserActive($pages) {
  global $current_page;
  if (is_array($pages)) {
    return in_array($current_page, $pages) ? ' active' : '';
  }
  return $current_page === $pages ? ' active' : '';
}
?>
<!-- Offcanvas Sidebar for Mobile -->
<div class="offcanvas offcanvas-start d-md-none bg-dark text-white" tabindex="-1" id="userSidebarOffcanvas" aria-labelledby="userSidebarOffcanvasLabel">
  <div class="offcanvas-header bg-dark text-white">
    <h5 class="offcanvas-title" id="userSidebarOffcanvasLabel">AK23 App</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0 bg-dark text-white user-sidebar-mobile">
    <ul class="nav flex-column">
      <li class="nav-item"><a class="nav-link text-warning" href="index.php"><i class="fas fa-home me-2"></i>Home</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive(['user_index.php','user_dashboard.php']) ?>" href="user_index.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('orders.php') ?>" href="orders.php"><i class="fas fa-shopping-bag me-2"></i>Orders</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('purchases.php') ?>" href="purchases.php"><i class="fas fa-receipt me-2"></i>Purchases</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('downloads.php') ?>" href="downloads.php"><i class="fas fa-download me-2"></i>Downloads</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('request_course.php') ?>" href="request_course.php"><i class="fas fa-lightbulb me-2"></i>course Requests</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('tickets.php') ?>" href="tickets.php"><i class="fas fa-ticket-alt me-2"></i>Tickets</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('user_contact_messages.php') ?>" href="user_contact_messages.php"><i class="fas fa-envelope-open-text me-2"></i>My Messages</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('contact_us.php') ?>" href="contact_us.php"><i class="fas fa-envelope me-2"></i>Contact Us</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('notifications.php') ?>" href="notifications.php"><i class="fas fa-bell me-2"></i>Notifications</a></li>
      <?php $profileActive = isUserActive(['my-profile.php','profile_update.php','contact_update.php']); ?>
      <li class="nav-item"><a class="nav-link text-white<?= $profileActive ?>" href="my-profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
      <li class="nav-item"><a class="nav-link text-danger<?= isUserActive('logout.php') ?>" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
    </ul>
  </div>
</div>
<!-- Static Sidebar for Desktop -->
<nav class="col-md-2 d-none d-md-block bg-dark sidebar text-white p-0 min-vh-100">
  <div class="sidebar-sticky pt-3">
    <ul class="nav flex-column">
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('index.php') ?>" href="index.php"><i class="fas fa-home me-2"></i>Home</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive(['user_index.php','user_dashboard.php']) ?>" href="user_index.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('orders.php') ?>" href="orders.php"><i class="fas fa-shopping-bag me-2"></i>Orders</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('purchases.php') ?>" href="purchases.php"><i class="fas fa-receipt me-2"></i>Purchases</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('downloads.php') ?>" href="downloads.php"><i class="fas fa-download me-2"></i>Downloads</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('request_course.php') ?>" href="request_course.php"><i class="fas fa-lightbulb me-2"></i>course Requests</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('tickets.php') ?>" href="tickets.php"><i class="fas fa-ticket-alt me-2"></i>Tickets</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('user_contact_messages.php') ?>" href="user_contact_messages.php"><i class="fas fa-envelope-open-text me-2"></i>My Messages</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('contact_us.php') ?>" href="contact_us.php"><i class="fas fa-envelope me-2"></i>Contact Us</a></li>
      <li class="nav-item"><a class="nav-link text-white<?= isUserActive('notifications.php') ?>" href="notifications.php"><i class="fas fa-bell me-2"></i>Notifications</a></li>
      <?php $profileActive = isUserActive(['my-profile.php','profile_update.php','contact_update.php']); ?>
      <li class="nav-item"><a class="nav-link text-white<?= $profileActive ?>" href="my-profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
      <li class="nav-item"><a class="nav-link text-danger<?= isUserActive('logout.php') ?>" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
    </ul>
  </div>
</nav>