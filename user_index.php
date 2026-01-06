<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}
$title = 'User Dashboard - AK23 App';
include 'user_header.php';
?>
<?php include 'user_sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
  <?php require_once __DIR__ . '/includes/breadcrumb.php';
    $breadcrumb = [
      ['label' => 'Home', 'url' => ($base ?? '') . '/index.php', 'icon' => 'fas fa-home'],
      ['label' => 'Dashboard', 'icon' => 'fas fa-gauge-high']
    ];
    render_breadcrumb($breadcrumb);
  ?>
  <?php $ref = isset($_GET['ref']) ? trim($_GET['ref']) : ''; ?>
  <?php if ($ref !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <strong>Payment successful!</strong> Your download is ready. <a href="downloads.php" class="alert-link">Open My Downloads</a>.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <h1 class="h3 mb-4" style="color: black;">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</h1>
  <?php
  $user_id = $_SESSION['user_id'];
  $notifications = getUserNotifications($user_id, 5);
  ?>
  <div class="row g-3 g-md-4 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card shadow-sm border-0 h-100 dashboard-card text-center p-3 hover-shadow">
        <div class="mb-2">
          <i class="fas fa-bell fa-3x text-danger"></i>
        </div>
        <h6 class="card-title mb-2" style="color: black;">Notifications</h6>
        <div class="position-relative d-inline-block mb-2" style="font-size:2.5rem;">
          <i class="fas fa-bell text-danger"></i>
          <?php $notif_count = count($notifications); ?>
          <?php if ($notif_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:1rem;">
              <?= $notif_count ?>
              <span class="visually-hidden">unread notifications</span>
            </span>
          <?php endif; ?>
        </div>
        <div class="mb-2">
          <span class="text-muted small">You have <?= $notif_count ?> notification<?= $notif_count === 1 ? '' : 's' ?>.</span>
        </div>
        <a href="notifications.php" class="btn btn-danger btn-sm w-100">View All</a>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card shadow-sm border-0 h-100 dashboard-card text-center p-3 hover-shadow">
        <div class="mb-2">
          <i class="fas fa-shopping-bag fa-3x text-primary"></i>
        </div>
        <h6 class="card-title mb-2" style="color: black;">Orders</h6>
        <?php
        // Orders count
        // Orders count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $order_count = $stmt->fetchColumn();
        // Downloads count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM downloads_log d JOIN orders o ON d.order_id = o.id WHERE o.user_id = ?");
        $stmt->execute([$user_id]);
        $download_count = $stmt->fetchColumn();
        // Payments total (completed)
        $stmt = $pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN orders o ON p.order_id = o.id WHERE o.user_id = ? AND p.status = 'completed'");
        $stmt->execute([$user_id]);
        $payments_total = $stmt->fetchColumn() ?: 0;
        ?>
        <div class="fs-4 fw-bold mb-2 text-dark"><?php echo $order_count; ?></div>
        <a href="orders.php" class="btn btn-primary btn-sm w-100">View Orders</a>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card shadow-sm border-0 h-100 dashboard-card text-center p-3 hover-shadow">
        <div class="mb-2">
          <i class="fas fa-download fa-3x text-success"></i>
        </div>
        <h6 class="card-title mb-2" style="color: black;">Downloads</h6>
        <div class="fs-4 fw-bold mb-2 text-dark"><?php echo $download_count; ?></div>
        <a href="downloads.php" class="btn btn-success btn-sm w-100">View Downloads</a>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card shadow-sm border-0 h-100 dashboard-card text-center p-3 hover-shadow">
        <div class="mb-2">
          <i class="fas fa-envelope fa-3x text-warning"></i>
        </div>
        <h6 class="card-title mb-2" style="color: black;">Contact Us</h6>
        <a href="contact_us.php" class="btn btn-warning btn-sm w-100">Get Help</a>
      </div>
    </div>
  </div>
  <hr class="my-4">
  <div class="row">
    <div class="col-md-6 mb-4">
      <h5 class="mb-3" style="color: black;">Recent Orders</h5>
      <?php
      $stmt = $pdo->prepare("SELECT pay.amount, o.status, o.created_at, p.title as course_name FROM orders o JOIN courses p ON o.course_id = p.id LEFT JOIN payments pay ON pay.order_id = o.id WHERE o.user_id = ? ORDER BY o.created_at DESC LIMIT 3");
      $stmt->execute([$user_id]);
      $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>course</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_orders as $order): ?>
              <tr>
                <td><?= htmlspecialchars($order['course_name']) ?></td>
                <td>TSH <?= number_format($order['amount'] ?? 0, 0) ?></td>
                <td><span class="badge bg-<?= strtolower($order['status']) === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst(strtolower($order['status'])) ?></span></td>
                <td><?= htmlspecialchars($order['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-md-6 mb-4">
      <h5 class="mb-3" style="color: black;">Recent Downloads</h5>
      <?php
      $stmt = $pdo->prepare("SELECT d.*, pr.title as course_name, 1 as downloads_left, '' as download_token, d.downloaded_at FROM downloads_log d JOIN orders o ON d.order_id = o.id JOIN courses pr ON o.course_id = pr.id WHERE o.user_id = ? ORDER BY d.downloaded_at DESC LIMIT 3");
      $stmt->execute([$user_id]);
      $recent_downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>course</th>
              <th>Download</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_downloads as $dl): ?>
              <tr>
                <td><?= htmlspecialchars($dl['course_name']) ?></td>
                <td><a href="downloads.php?token=<?= urlencode($dl['download_token']) ?>" target="_blank">Download</a></td>
                <td><span class="badge bg-<?= ($dl['downloads_left'] ?? 0) > 0 ? 'success' : 'danger' ?>"><?= ($dl['downloads_left'] ?? 0) > 0 ? 'Active' : 'Locked' ?></span></td>
                <td><?= htmlspecialchars($dl['downloaded_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <hr class="my-4">
  <div class="row">
    <div class="col-md-12 mb-4">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-3" style="color: black;">course Requests</h5>
        <div class="d-flex gap-2">
          <a href="request_course.php" class="btn btn-warning btn-sm">Request a course</a>
          <a href="contact_us.php" class="btn btn-info btn-sm">Contact Us</a>
        </div>
      </div>
      <?php
      $stmt = $pdo->prepare("SELECT id, course_name, status, created_at FROM course_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
      $stmt->execute([$user_id]);
      $my_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>course</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($my_requests as $req): ?>
              <tr>
                <td><?= (int)$req['id'] ?></td>
                <td><?= htmlspecialchars($req['course_name']) ?></td>
                <td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($req['status']) ?></span></td>
                <td><?= htmlspecialchars($req['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($my_requests)): ?>
              <tr>
                <td colspan="4" class="text-center text-muted">No requests yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <p class="lead text-center text-muted">Use the sidebar to access your orders, downloads, tickets, notifications, and profile settings.</p>
</main>
<style>
.dashboard-card {
  transition: box-shadow 0.2s, transform 0.2s;
  border-radius: 1rem;
}
.dashboard-card:hover {
  box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.10), 0 0.125rem 0.25rem rgba(0,0,0,0.08);
  transform: translateY(-2px) scale(1.03);
}
@media (max-width: 575.98px) {
  .dashboard-card {
    margin-bottom: 1rem;
  }
}
</style>
<?php include 'user_footer.php'; ?> 