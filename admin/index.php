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

// LMS dashboard stats
$stats = [
    'total_courses' => 0,
    'total_students' => 0,
    'total_instructors' => 0,
    'total_enrollments' => 0,
    'total_sales' => 0,
    'pending_withdrawals' => 0,
    'new_users' => 0,
];
// Total courses
$stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE visibility = 'published'");
$stats['total_courses'] = $stmt->fetchColumn();
// Total students
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$stats['total_students'] = $stmt->fetchColumn();
// Total instructors
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor'");
$stats['total_instructors'] = $stmt->fetchColumn();
// Total enrollments
$stmt = $pdo->query("SELECT COUNT(*) FROM enrollments");
$stats['total_enrollments'] = $stmt->fetchColumn();
// Total sales (sum of completed enrollment_payments)
$stmt = $pdo->query("SELECT SUM(amount) FROM enrollment_payments WHERE status = 'completed'");
$stats['total_sales'] = $stmt->fetchColumn() ?? 0;
// Pending instructor withdrawals
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM instructor_withdraw_requests WHERE status = 'pending'");
    $stats['pending_withdrawals'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $stats['pending_withdrawals'] = 0;
}
// New users in last 30 days
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['new_users'] = $stmt->fetchColumn();

$title = 'Admin Dashboard - AK23 App';
include '../includes/admin_header.php';
?>
<main class="flex-grow-1 p-3">
  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4 mb-4">
    <div class="col">
      <div class="card h-100 shadow-sm border-0 hover-shadow">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="fas fa-book fa-lg"></i></div>
          <div>
            <h6 class="card-title mb-1 text-secondary">Total Courses</h6>
            <div class="fs-3 fw-bold text-dark"><?= $stats['total_courses'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card h-100 shadow-sm border-0 hover-shadow">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="fas fa-user-graduate fa-lg"></i></div>
          <div>
            <h6 class="card-title mb-1 text-secondary">Total Students</h6>
            <div class="fs-3 fw-bold text-dark"><?= $stats['total_students'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card h-100 shadow-sm border-0 hover-shadow">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="fas fa-chalkboard-teacher fa-lg"></i></div>
          <div>
            <h6 class="card-title mb-1 text-secondary">Total Instructors</h6>
            <div class="fs-3 fw-bold text-dark"><?= $stats['total_instructors'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card h-100 shadow-sm border-0 hover-shadow">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="fas fa-layer-group fa-lg"></i></div>
          <div>
            <h6 class="card-title mb-1 text-secondary">Total Enrollments</h6>
            <div class="fs-3 fw-bold text-dark"><?= $stats['total_enrollments'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card h-100 shadow-sm border-0 hover-shadow">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="fas fa-credit-card fa-lg"></i></div>
          <div>
            <h6 class="card-title mb-1 text-secondary">Total Sales</h6>
            <div class="fs-3 fw-bold text-dark">TSH <?= number_format($stats['total_sales'], 0) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card h-100 shadow-sm border-0 hover-shadow">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="fas fa-wallet fa-lg"></i></div>
          <div>
            <h6 class="card-title mb-1 text-secondary">Pending Withdrawals</h6>
            <div class="fs-3 fw-bold text-dark"><?= $stats['pending_withdrawals'] ?></div>
            <a href="instructor_finances.php?tab=withdrawals" class="btn btn-sm btn-outline-danger mt-2">Manage</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card h-100 shadow-sm border-0 hover-shadow">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="fas fa-users fa-lg"></i></div>
          <div>
            <h6 class="card-title mb-1 text-secondary">New Users (30d)</h6>
            <div class="fs-3 fw-bold text-dark"><?= $stats['new_users'] ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Actions for Instructor Finances -->
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Instructor Financial Management</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3 col-sm-6">
              <a href="instructor_finances.php?tab=withdrawals" class="card text-decoration-none h-100 border-0 shadow-sm hover-lift">
                <div class="card-body text-center">
                  <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                    <i class="fas fa-money-check-alt fa-2x text-warning"></i>
                  </div>
                  <h6 class="card-title mb-1">Approve Withdrawals</h6>
                  <p class="text-muted small mb-0">Review and approve pending withdrawal requests</p>
                </div>
              </a>
            </div>
            <div class="col-md-3 col-sm-6">
              <a href="instructor_finances.php?tab=transfer" class="card text-decoration-none h-100 border-0 shadow-sm hover-lift">
                <div class="card-body text-center">
                  <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                    <i class="fas fa-paper-plane fa-2x text-primary"></i>
                  </div>
                  <h6 class="card-title mb-1">Transfer Money</h6>
                  <p class="text-muted small mb-0">Send funds to instructor wallets</p>
                </div>
              </a>
            </div>
            <div class="col-md-3 col-sm-6">
              <a href="instructor_finances.php?tab=payout" class="card text-decoration-none h-100 border-0 shadow-sm hover-lift">
                <div class="card-body text-center">
                  <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                    <i class="fas fa-hand-holding-usd fa-2x text-success"></i>
                  </div>
                  <h6 class="card-title mb-1">Make Payout</h6>
                  <p class="text-muted small mb-0">Process approved withdrawals</p>
                </div>
              </a>
            </div>
            <div class="col-md-3 col-sm-6">
              <a href="instructor_finances.php?tab=transactions" class="card text-decoration-none h-100 border-0 shadow-sm hover-lift">
                <div class="card-body text-center">
                  <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                    <i class="fas fa-exchange-alt fa-2x text-info"></i>
                  </div>
                  <h6 class="card-title mb-1">All Transactions</h6>
                  <p class="text-muted small mb-0">View complete transaction history</p>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include '../includes/admin_footer.php'; ?>
<style>
  
@media (max-width: 600px) {
  .card-body { flex-direction: column !important; align-items: flex-start !important; gap: 0.7rem !important; }
  .card .fs-3 { font-size: 1.3rem !important; }
  .card-title { font-size: 1rem !important; }
}
.card.hover-shadow:hover { box-shadow: 0 8px 32px #0002 !important; transform: translateY(-2px) scale(1.03); }
.hover-lift:hover { transform: translateY(-5px); transition: transform 0.2s; box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important; }
</style>