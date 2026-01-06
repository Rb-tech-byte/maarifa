<?php
session_start();
require 'config.php';
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}
$title = 'My Orders - AK23 App';
include 'user_header.php';
?>
<?php include 'user_sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
  <?php
  $user_id = $_SESSION['user_id'];
  // Optional order detail view
  $selected_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
  $order_detail = null;
  if ($selected_order_id > 0) {
      $stmt = $pdo->prepare("SELECT o.id, o.course_id, pay.amount, o.status, o.created_at, p.title as course_name, p.file_url as download_url \n FROM orders o JOIN courses p ON o.course_id = p.id LEFT JOIN payments pay ON pay.order_id = o.id WHERE o.user_id = ? AND o.id = ? LIMIT 1");
      $stmt->execute([$user_id, $selected_order_id]);
      $order_detail = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  // Pagination
  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $limit = 5;
  $offset = ($page - 1) * $limit;

  // Get total number of orders for the user
  $total_orders_stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
  $total_orders_stmt->execute([$user_id]);
  $total_orders = $total_orders_stmt->fetchColumn();
  $total_pages = ceil($total_orders / $limit);

  // If a specific order_id is requested, compute its page position using DESC(created_at, id)
  if ($order_detail) {
      $posStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND (created_at > ? OR (created_at = ? AND id >= ?))");
      $posStmt->execute([$user_id, $order_detail['created_at'], $order_detail['created_at'], (int)$order_detail['id']]);
      $position = (int)$posStmt->fetchColumn();
      if ($position > 0) {
          $page = (int)floor(($position - 1) / $limit) + 1;
          $offset = ($page - 1) * $limit;
      }
  }

  // Fetch orders for the current page (stable ordering by created_at DESC, id DESC)
  $orders_stmt = $pdo->prepare("SELECT o.id, o.course_id, pay.amount, o.status, o.created_at, p.title as course_name FROM orders o JOIN courses p ON o.course_id = p.id LEFT JOIN payments pay ON pay.order_id = o.id WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC LIMIT ? OFFSET ?");
  $orders_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
  $orders_stmt->bindValue(2, $limit, PDO::PARAM_INT);
  $orders_stmt->bindValue(3, $offset, PDO::PARAM_INT);
  $orders_stmt->execute();
  $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <?php if ($order_detail): ?>
    <?php
      $isPaid = (strtolower($order_detail['status']) === 'completed');
      $bannerClass = $isPaid ? 'success' : (in_array(strtolower($order_detail['status']), ['failed','rejected','cancelled']) ? 'danger' : 'warning');
    ?>
    <div class="alert alert-<?= $bannerClass ?> d-flex align-items-center justify-content-between" role="alert">
      <div>
        <strong>Order #<?= (int)$order_detail['id'] ?>:</strong>
        This order is <strong><?= ucfirst(strtolower($order_detail['status'])) ?></strong>.
        <?php if (!$isPaid): ?> Click <em>Retry Payment</em> to complete your purchase.<?php endif; ?>
      </div>
      <?php if (!$isPaid): ?>
        <a class="btn btn-sm btn-dark" href="pay_pesapal.php?order_id=<?= (int)$order_detail['id'] ?>" data-bs-toggle="tooltip" data-bs-placement="left" title="Most payments confirm within seconds. If it takes longer, you can retry safely.">Retry Payment</a>
      <?php endif; ?>
    </div>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-dark text-white">Order #<?= (int)$order_detail['id'] ?> Details</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div><strong>course:</strong> <?= htmlspecialchars($order_detail['course_name']) ?></div>
            <div><strong>Amount:</strong> TSH <?= number_format((float)($order_detail['amount'] ?? 0), 0) ?></div>
          </div>
          <div class="col-md-6">
            <div><strong>Status:</strong> <span class="badge bg-<?= strtolower($order_detail['status']) === 'completed' ? 'success' : (in_array(strtolower($order_detail['status']), ['failed','reversed','cancelled']) ? 'danger' : 'warning') ?>"><?= ucfirst(strtolower($order_detail['status'])) ?></span></div>
            <div><strong>Date:</strong> <?= htmlspecialchars($order_detail['created_at']) ?></div>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <?php if (strtolower($order_detail['status']) === 'completed' && !empty($order_detail['download_url'])): ?>
            <?php $token = ak23_sign(['order_id' => (int)$order_detail['id'], 'course_id' => (int)$order_detail['course_id'], 'url' => (string)$order_detail['download_url']]); ?>
            <a class="btn btn-success" href="downloads.php?token=<?= urlencode($token) ?>" target="_blank">Download</a>
          <?php else: ?>
            <a class="btn btn-primary" href="pay_pesapal.php?order_id=<?= (int)$order_detail['id'] ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Payments usually confirm within seconds. If delayed, retry won't duplicate charges.">Proceed to Payment</a>
          <?php endif; ?>
          <a class="btn btn-outline-secondary" href="downloads.php">Open Download List</a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <h2 class="h4 mb-4" style="color: black;">My Orders</h2>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>course</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr id="order-row-<?= (int)$order['id'] ?>">
            <td><?= $order['id'] ?></td>
            <td><?= htmlspecialchars($order['course_name']) ?></td>
            <td>TSH <?= number_format($order['amount'] ?? 0, 0) ?></td>
            <td><span class="badge bg-<?= strtolower($order['status']) === 'completed' ? 'success' : (in_array(strtolower($order['status']), ['failed', 'reversed', 'cancelled']) ? 'danger' : 'warning') ?>"><?= ucfirst(strtolower($order['status'])) ?></span></td>
            <td><?= htmlspecialchars($order['created_at']) ?></td>
            <td>
              <?php if (strtolower($order['status']) !== 'completed'): ?>
                <a href="pay_pesapal.php?order_id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-primary">Retry Payment</a>
              <?php else: ?>
                <span class="text-success">Completed</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($selected_order_id > 0): ?>
  <style>
    .row-highlight { animation: rowFlash 2s ease-in-out 0s 2 alternate; }
    @keyframes rowFlash {
      from { background-color: #fff8e1; }
      to   { background-color: #ffffff; }
    }
  </style>
  <script>
    (function(){
      var el = document.getElementById('order-row-<?= (int)$selected_order_id ?>');
      if (el){
        try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch(_) { el.scrollIntoView(true); }
        el.classList.add('row-highlight');
        setTimeout(function(){ el.classList.remove('row-highlight'); }, 5000);
      }
    })();
  </script>
  <?php endif; ?>
  <script>
    (function(){
      if (window.bootstrap && typeof bootstrap.Tooltip === 'function') {
        var triggers = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        triggers.forEach(function(el){ try { new bootstrap.Tooltip(el); } catch(_){} });
      }
    })();
  </script>

  <!-- Pagination -->
  <nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
      </li>
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
      <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
      </li>
    </ul>
  </nav>
</main>
<?php include 'user_footer.php'; ?> 