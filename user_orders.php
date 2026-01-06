<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT p.*, pr.name as course_name FROM payments p JOIN courses pr ON p.course_id = pr.id WHERE p.user_id = ?";
$params = [$user_id];
if ($search) {
    $sql .= " AND (pr.name LIKE ? OR p.amount LIKE ? OR p.status LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY p.created_at DESC";
$orders = $pdo->prepare($sql);
$orders->execute($params);
$orders = $orders->fetchAll(PDO::FETCH_ASSOC);

$title = 'My Orders - AK23 App';
include 'includes/header.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-4">My Orders</h1>
  <form class="mb-3" method="get">
    <div class="input-group">
      <input type="text" name="search" class="form-control" placeholder="Search orders..." value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-outline-secondary" type="submit">Search</button>
    </div>
  </form>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>course</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td><?= $order['id'] ?></td>
            <td><?= htmlspecialchars($order['course_name']) ?></td>
            <td>$<?= number_format($order['amount'], 2) ?></td>
            <td><?= htmlspecialchars($order['status']) ?></td>
            <td><?= htmlspecialchars($order['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?> 