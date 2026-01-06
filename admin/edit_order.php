<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: orders.php');
    exit();
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    header('Location: orders.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? $order['amount']);
    $status = $_POST['status'] ?? $order['status'];
    $transaction_id = $_POST['transaction_id'] ?? $order['transaction_id'];
    $reference = $_POST['reference'] ?? $order['reference'];
    $notes = $_POST['notes'] ?? $order['notes'];
    $stmt = $pdo->prepare("UPDATE orders SET amount = ?, status = ?, transaction_id = ?, reference = ?, notes = ? WHERE id = ?");
    $stmt->execute([$amount, $status, $transaction_id, $reference, $notes, $id]);
    header('Location: orders.php?success=1');
    exit();
}

$title = 'Edit Order - AK23 App';
include '../includes/admin_header.php';
?>
<main class="admin-main container py-4">
  <h1 class="h4 mb-4" style="color:#000;">Edit Order</h1>
  <form method="POST" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Amount (TSH)</label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($order['amount']) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Status</label>
      <select name="status" class="form-select" required>
        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
        <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
        <option value="failed" <?= $order['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Transaction ID</label>
      <input type="text" name="transaction_id" class="form-control" value="<?= htmlspecialchars($order['transaction_id'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Reference</label>
      <input type="text" name="reference" class="form-control" value="<?= htmlspecialchars($order['reference'] ?? '') ?>">
    </div>
    <div class="col-12">
      <label class="form-label">Notes</label>
      <textarea name="notes" class="form-control rte" rows="2"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="orders.php" class="btn btn-secondary ms-2">Cancel</a>
    </div>
  </form>
</main>
<?php include '../includes/admin_footer.php'; ?>