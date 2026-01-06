<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// Security check - only allow admin access
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$title = "Fix Pending Payments";
require_once '../includes/admin_header.php';

$log_file = __DIR__ . '/../logs/fix_payments.log';

// Function to log actions
function logAction($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

logAction("=== PAYMENT FIX SCRIPT STARTED ===");

// Get all pending payments
$stmt = $pdo->prepare("
SELECT p.*, o.status as order_status, pr.title as course_name, u.email 
FROM payments p 
LEFT JOIN orders o ON p.order_id = o.id 
LEFT JOIN courses pr ON o.course_id = pr.id 
LEFT JOIN users u ON o.user_id = u.id 
WHERE p.status = 'pending' 
ORDER BY p.created_at DESC
");
$stmt->execute();
$pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

logAction("Found " . count($pending_payments) . " pending payments");
?>
<style>
h1, h2, label, .form-label, .form-control-label, .label {
    color: #000 !important;
}
</style>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tools me-2"></i>Fix Pending Payments</h2>
        <div class="badge bg-warning fs-6"><?= count($pending_payments) ?> Pending</div>
    </div>

    <?php if (empty($pending_payments)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No pending payments found.
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Payments</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Payment ID</th>
                                <th>Tracking ID</th>
                                <th>course</th>
                                <th>Amount</th>
                                <th>User Email</th>
                                <th>Created</th>
                                <th>Order Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_payments as $payment): ?>
                                <tr>
                                    <td><span class="badge bg-secondary">#<?= $payment['id'] ?></span></td>
                                    <td><code><?= htmlspecialchars($payment['order_tracking_id'] ?? '') ?></code></td>
                                    <td><?= htmlspecialchars($payment['course_name'] ?? 'Unknown course') ?></td>
                                    <td><span class="fw-bold">TSH <?= number_format($payment['amount'], 0) ?></span></td>
                                    <td><?= htmlspecialchars($payment['email'] ?? 'No Email') ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($payment['created_at'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $payment['order_status'] === 'pending' ? 'warning' : 'info' ?>">
                                            <?= ucfirst($payment['order_status'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?action=complete&payment_id=<?= $payment['id'] ?>" 
                                           class="btn btn-success btn-sm me-1" 
                                           onclick="return confirm('Mark this payment as completed?')">
                                            <i class="fas fa-check"></i> Complete
                                        </a>
                                        <a href="?action=fail&payment_id=<?= $payment['id'] ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Mark this payment as failed?')">
                                            <i class="fas fa-times"></i> Fail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php
    // Handle actions
    if (isset($_GET['action']) && isset($_GET['payment_id'])) {
        $action = $_GET['action'];
        $payment_id = intval($_GET['payment_id']);
        
        // Get payment details
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            echo '<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-2"></i>Payment not found.</div>';
            logAction("ERROR: Payment ID $payment_id not found");
        } else {
            $new_status = '';
            switch ($action) {
                case 'complete':
                    $new_status = 'completed';
                    break;
                case 'fail':
                    $new_status = 'failed';
                    break;
                default:
                    echo '<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-2"></i>Invalid action.</div>';
                    exit;
            }
            
            // Update payment status
            $payment_updated = updatePaymentStatus($payment_id, $new_status);
            
            if ($payment_updated) {
                echo '<div class="alert alert-success mt-3">
                        <i class="fas fa-check-circle me-2"></i>Payment #' . $payment_id . ' status updated to \'' . $new_status . '\' successfully.
                        <script>setTimeout(function(){ window.location.href = \'fix_pending_payments.php\'; }, 2000);</script>
                      </div>';
                logAction("SUCCESS: Payment ID $payment_id updated to $new_status");
            } else {
                echo '<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-2"></i>Failed to update payment #' . $payment_id . ' status.</div>';
                logAction("ERROR: Failed to update Payment ID $payment_id to $new_status");
            }
        }
    }
    ?>

    <!-- Recent Payment Activity -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Payment Activity</h5>
        </div>
        <div class="card-body p-0">
            <?php
            $stmt = $pdo->prepare("
                SELECT p.*, pr.title as course_name 
                FROM payments p 
                LEFT JOIN orders o ON p.order_id = o.id 
                LEFT JOIN courses pr ON o.course_id = pr.id 
                WHERE p.status IN ('completed', 'failed', 'reject') 
                ORDER BY p.updated_at DESC 
                LIMIT 10
            ");
            $stmt->execute();
            $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Payment ID</th>
                            <th>Reference</th>
                            <th>course</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?= $payment['id'] ?></span></td>
                                <td><code><?= htmlspecialchars($payment['reference'] ?? '') ?></code></td>
                                <td><?= htmlspecialchars($payment['course_name'] ?? 'Unknown course') ?></td>
                                <td><span class="fw-bold">TSH <?= number_format($payment['amount'], 0) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($payment['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y H:i', strtotime($payment['updated_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
logAction("=== PAYMENT FIX SCRIPT COMPLETED ===");
require_once '../includes/admin_footer.php';
?> 