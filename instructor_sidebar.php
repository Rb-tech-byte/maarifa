<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
function isInstructorActive($pages) {
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
    <h5 class="offcanvas-title" id="userSidebarOffcanvasLabel">Instructor Menu</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0 bg-dark text-white">
    <ul class="nav flex-column">
      <li class="nav-item">
        <a class="nav-link text-white<?= isInstructorActive('instructor_dashboard.php') ? ' active' : '' ?>" href="instructor_dashboard.php">
          <i class="fas fa-gauge-high me-2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white<?= isInstructorActive('instructor_courses.php') ? ' active' : '' ?>" href="instructor_courses.php">
          <i class="fas fa-book-open me-2"></i> My Courses
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white<?= isInstructorActive('instructor_students.php') ? ' active' : '' ?>" href="instructor_students.php">
          <i class="fas fa-users me-2"></i> Students
        </a>
      </li>
      <li class="nav-item mt-3">
        <span class="text-muted small ms-3 text-white-50">Wallet</span>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white<?= isInstructorActive('instructor_earnings.php') ? ' active' : '' ?>" href="instructor_earnings.php">
          <i class="fas fa-wallet me-2"></i> Overview
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="instructor_earnings.php#send-money">
          <i class="fas fa-paper-plane me-2"></i> Send Money
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="instructor_earnings.php#withdraw">
          <i class="fas fa-money-bill-transfer me-2"></i> Withdraw
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="instructor_earnings.php#transactions">
          <i class="fas fa-list me-2"></i> Transactions
        </a>
      </li>
    </ul>
  </div>
</div>

<!-- Static Sidebar for Desktop -->
<nav class="d-none d-md-block bg-dark sidebar text-white p-0 position-fixed" style="top: 56px; left: 0; width: 200px; height: calc(100vh - 56px); z-index: 1000; overflow-y: auto;">
  <div class="sidebar-sticky pt-3">
    <ul class="nav flex-column">
      <li class="nav-item">
        <a class="nav-link text-white<?= isInstructorActive('instructor_dashboard.php') ? ' active bg-primary' : '' ?>" href="instructor_dashboard.php">
          <i class="fas fa-gauge-high me-2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white<?= isInstructorActive('instructor_courses.php') ? ' active bg-primary' : '' ?>" href="instructor_courses.php">
          <i class="fas fa-book-open me-2"></i> My Courses
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white<?= isInstructorActive('instructor_students.php') ? ' active bg-primary' : '' ?>" href="instructor_students.php">
          <i class="fas fa-users me-2"></i> Students
        </a>
      </li>
      <li class="nav-item mt-3">
        <span class="text-muted small ms-3 text-white-50">Wallet</span>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white<?= isInstructorActive('instructor_earnings.php') ? ' active bg-primary' : '' ?>" href="instructor_earnings.php">
          <i class="fas fa-wallet me-2"></i> Overview
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="instructor_earnings.php#send-money">
          <i class="fas fa-paper-plane me-2"></i> Send Money
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="instructor_earnings.php#withdraw">
          <i class="fas fa-money-bill-transfer me-2"></i> Withdraw
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="instructor_earnings.php#transactions">
          <i class="fas fa-list me-2"></i> Transactions
        </a>
      </li>
    </ul>
  </div>
</nav>

<style>
/* Sidebar Styles */
.sidebar {
  box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}

.sidebar .nav-link {
  padding: 0.75rem 1rem;
  border-radius: 0;
  transition: all 0.2s;
}

.sidebar .nav-link:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: #fff !important;
}

.sidebar .nav-link.active {
  background-color: #0d6efd;
  color: #fff !important;
  font-weight: 600;
}

.sidebar .nav-link i {
  width: 20px;
  text-align: center;
}

/* Mobile offcanvas adjustments */
@media (max-width: 767.98px) {
  .offcanvas-body .nav-link {
    padding: 0.75rem 1rem;
  }
  
  .offcanvas-body .nav-link.active {
    background-color: rgba(13, 110, 253, 0.2);
    color: #fff !important;
  }
}

/* Ensure main content doesn't overlap with fixed sidebar */
@media (min-width: 768px) {
  main {
    margin-left: 200px !important;
  }
}
</style>
