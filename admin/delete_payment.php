<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id) {
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: manage_payments.php?deleted=1');
    exit();
}

// Only include header/footer if not redirecting
$title = 'Delete Payment - AK23 App';
include '../includes/admin_header.php';
?>
<main class="admin-main container py-4">
  <h1 class="h4 mb-4" style="color:#000;">Delete Payment</h1>
  <div class="alert alert-danger">Invalid payment ID.</div>
  <a href="manage_payments.php" class="btn btn-secondary">Back to Payments</a>
</main>
<?php include '../includes/admin_footer.php'; ?> 