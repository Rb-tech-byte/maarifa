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

// Soft-delete logic
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    if ($stmt->execute([$id])) {
        header('Location: orders.php?deleted=1');
        exit();
    } else {
        $error = 'Failed to delete order.';
    }
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;
$total_stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $total_stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

$sql = "SELECT 
            o.id, o.status, o.created_at,
            u.name AS username, u.email as user_email,
            prod.title as course_name,
            pay.amount as amount, pay.status as payment_status, pay.payment_method as payment_method, pay.order_tracking_id as transaction_id, pay.created_at as paid_at
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN courses prod ON o.course_id = prod.id
        LEFT JOIN payments pay ON pay.order_id = o.id
        ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$title = 'Orders - AK23 App';
include '../includes/admin_header.php';
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<main class="admin-main container py-4">
  <h1 class="h3 mb-3" style="color:#000;">Orders</h1>
  <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Order deleted successfully.</div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>User</th>
          <th>Course</th>
          <th>Amount</th>
          <th>Order Status</th>
          <th>Payment Status</th>
          <th>Reference</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td><?= $order['id'] ?></td>
            <td><?= htmlspecialchars($order['username'] ?? 'Guest') ?> (<?= htmlspecialchars($order['user_email'] ?? 'N/A') ?>)</td>
            <td><?= htmlspecialchars($order['course_name'] ?? 'N/A') ?></td>
            <td>TSH <?= number_format((float)($order['amount'] ?? 0), 0) ?></td>
            <td><span class="badge bg-<?= strtolower($order['status']) === 'completed' ? 'success' : (in_array(strtolower($order['status']), ['failed','cancelled','reversed']) ? 'danger' : 'warning') ?>"><?= ucfirst(strtolower($order['status'])) ?></span></td>
            <td><span class="badge bg-<?= strtolower($order['payment_status'] ?? '') === 'completed' ? 'success' : (in_array(strtolower($order['payment_status'] ?? ''), ['failed','cancelled','reversed']) ? 'danger' : 'warning') ?>"><?= ucfirst(strtolower($order['payment_status'] ?? 'pending')) ?></span></td>
            <td><?= htmlspecialchars($order['transaction_id'] ?? '-') ?></td>
            <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
            <td>
              <a href="orders.php?delete=<?= $order['id'] ?>" class="btn btn-danger btn-sm ms-1" onclick="return confirm('Are you sure you want to delete this order?');"><i class="fas fa-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
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
<?php include '../includes/admin_footer.php'; ?>