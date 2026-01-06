<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: manage_payments.php');
    exit();
}

// Fetch payment
$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    header('Location: manage_payments.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? $payment['amount']);
    $status = $_POST['status'] ?? $payment['status'];
    $payment_method = $_POST['payment_method'] ?? $payment['payment_method'];
    $transaction_id = $_POST['transaction_id'] ?? $payment['transaction_id'];
    $notes = $_POST['notes'] ?? $payment['notes'];
    
    // Validate status to ensure it's a valid enum value
    $valid_statuses = ['pending', 'completed', 'failed'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'pending'; // Default to pending if invalid
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE payments SET amount = ?, status = ?, payment_method = ?, transaction_id = ?, notes = ? WHERE id = ?");
        $stmt->execute([$amount, $status, $payment_method, $transaction_id, $notes, $id]);
        header('Location: manage_payments.php?success=1');
        exit();
    } catch (PDOException $e) {
        // Log the error and show user-friendly message
        error_log("Payment update error: " . $e->getMessage());
        $error_message = "Failed to update payment. Please try again.";
    }
}

$title = 'Edit Payment - AK23 App';
include '../includes/admin_header.php';
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<main class="admin-main container py-4">
  <h1 class="h4 mb-4" style="color:#000;">Edit Payment</h1>
  
  <?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
  <?php endif; ?>
  
  <form method="POST" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Amount (TSH)</label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($payment['amount']) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Status</label>
      <select name="status" class="form-select" required>
        <option value="pending" <?= $payment['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="completed" <?= $payment['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
        <option value="failed" <?= $payment['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Payment Method</label>
      <input type="text" name="payment_method" class="form-control" value="<?= htmlspecialchars($payment['payment_method'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Transaction ID</label>
      <input type="text" name="transaction_id" class="form-control" value="<?= htmlspecialchars($payment['transaction_id'] ?? '') ?>">
    </div>
    <div class="col-12">
      <label class="form-label">Notes</label>
      <textarea name="notes" class="form-control rte" rows="2"><?= htmlspecialchars($payment['notes'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="manage_payments.php" class="btn btn-secondary ms-2">Cancel</a>
    </div>
  </form>
</main>
<?php include '../includes/admin_footer.php'; ?> 