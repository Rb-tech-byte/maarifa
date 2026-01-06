<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}
$title = 'My Purchases - AK23 App';
include 'user_header.php';
?>
<?php include 'user_sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
  <?php
  $user_id = $_SESSION['user_id'];
  // Fetch completed payments as purchases
  $purchases = $pdo->prepare("SELECT p.*, pr.title as course_name, p.order_tracking_id as transaction_id FROM payments p JOIN orders o ON p.order_id = o.id JOIN courses pr ON o.course_id = pr.id WHERE o.user_id = ? AND p.status = 'completed' ORDER BY p.created_at DESC");
  $purchases->execute([$user_id]);
  $purchases = $purchases->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <h2 class="h4 mb-4" style="color: black;">My Purchases</h2>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>course</th>
          <th>Amount</th>
          <th>Payment Method</th>
          <th>Transaction ID</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($purchases)): ?>
          <tr><td colspan="7" class="text-center text-muted">No purchases found.</td></tr>
        <?php else: foreach ($purchases as $purchase): ?>
          <tr>
            <td><?= $purchase['id'] ?></td>
            <td><?= htmlspecialchars($purchase['course_name']) ?></td>
            <td>TSH <?= number_format($purchase['amount'], 0) ?></td>
            <td><?= htmlspecialchars($purchase['payment_method'] ?? '-') ?></td>
            <td><?= htmlspecialchars($purchase['transaction_id'] ?? '-') ?></td>
            <td><span class="badge bg-success">Completed</span></td>
            <td><?= htmlspecialchars($purchase['created_at']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include 'user_footer.php'; ?> 