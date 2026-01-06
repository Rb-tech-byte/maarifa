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
$sql = "SELECT pu.*, pr.name as course_name FROM purchases pu JOIN courses pr ON pu.course_id = pr.id WHERE pu.user_id = ?";
$params = [$user_id];
if ($search) {
    $sql .= " AND (pr.name LIKE ? OR pu.amount LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY pu.created_at DESC";
$purchases = $pdo->prepare($sql);
$purchases->execute($params);
$purchases = $purchases->fetchAll(PDO::FETCH_ASSOC);

$title = 'My Purchases - AK23 App';
include 'includes/header.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-4">My Purchases</h1>
  <form class="mb-3" method="get">
    <div class="input-group">
      <input type="text" name="search" class="form-control" placeholder="Search purchases..." value="<?= htmlspecialchars($search) ?>">
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
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($purchases as $purchase): ?>
          <tr>
            <td><?= $purchase['id'] ?></td>
            <td><?= htmlspecialchars($purchase['course_name']) ?></td>
            <td>$<?= number_format($purchase['amount'], 2) ?></td>
            <td><?= htmlspecialchars($purchase['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?> 