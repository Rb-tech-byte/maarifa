<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

// Get reference from URL parameter
$reference = $_GET['reference'] ?? '';
$search_query = $_GET['search'] ?? '';

$title = 'Payment Status Checker - AK23 App';
require_once '../includes/admin_header.php';
?>
<main class="admin-main container">
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                    <label for="reference" class="form-label">Payment Reference</label>
                    <input type="text" class="form-control" id="reference" name="reference" 
                           value="<?= htmlspecialchars($reference) ?>" placeholder="Enter payment reference">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Check</button>
                        <a href="check_payment_status.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php
    // If a reference is provided, try to fetch that single payment and show details
    $details = null;
    if ($reference !== '') {
        // 1) Try by order_tracking_id
        $stmt = $pdo->prepare("SELECT p.*, o.id AS order_id, pr.title AS course_name, u.email AS user_email, u.phone AS user_phone
                               FROM payments p
                               JOIN orders o ON p.order_id = o.id
                               LEFT JOIN courses pr ON o.course_id = pr.id
                               LEFT JOIN users u ON o.user_id = u.id
                               WHERE p.order_tracking_id = ? LIMIT 1");
        $stmt->execute([$reference]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // 2) If not found and looks like ORD<id>, fetch by order and latest payment
        if (!$details && preg_match('/^ORD(\d+)$/i', $reference, $m)) {
            $orderId = (int)$m[1];
            $stmt = $pdo->prepare("SELECT p.*, o.id AS order_id, pr.title AS course_name, u.email AS user_email, u.phone AS user_phone
                                   FROM payments p
                                   JOIN orders o ON p.order_id = o.id
                                   LEFT JOIN courses pr ON o.course_id = pr.id
                                   LEFT JOIN users u ON o.user_id = u.id
                                   WHERE o.id = ? ORDER BY p.id DESC LIMIT 1");
            $stmt->execute([$orderId]);
            $details = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // 3) If numeric, try by payment id
        if (!$details && ctype_digit($reference)) {
            $pid = (int)$reference;
            $stmt = $pdo->prepare("SELECT p.*, o.id AS order_id, pr.title AS course_name, u.email AS user_email, u.phone AS user_phone
                                   FROM payments p
                                   JOIN orders o ON p.order_id = o.id
                                   LEFT JOIN courses pr ON o.course_id = pr.id
                                   LEFT JOIN users u ON o.user_id = u.id
                                   WHERE p.id = ? LIMIT 1");
            $stmt->execute([$pid]);
            $details = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }
    ?>

    <?php if ($reference !== ''): ?>
    <div class="card mb-4">
        <div class="card-header">Payment Details</div>
        <div class="card-body p-0">
            <?php if (!$details): ?>
            <div class="p-4 text-danger">No payment found for reference "<?= htmlspecialchars($reference) ?>".</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <tbody>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>
                                <span class="badge bg-<?= $details['status'] === 'completed' ? 'success' : ($details['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= htmlspecialchars(ucfirst($details['status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <tr><td><strong>Payment ID</strong></td><td><?= (int)$details['id'] ?></td></tr>
                        <tr><td><strong>Order</strong></td><td>#<?= (int)$details['order_id'] ?></td></tr>
                        <tr><td><strong>course</strong></td><td><?= htmlspecialchars($details['course_name'] ?? 'Unknown') ?></td></tr>
                        <tr><td><strong>Amount</strong></td><td>TSH <?= number_format((float)$details['amount'], 0) ?></td></tr>
                        <tr><td><strong>Reference</strong></td><td><?= htmlspecialchars($details['order_tracking_id'] ?? 'N/A') ?></td></tr>
                        <tr><td><strong>Email</strong></td><td><?= htmlspecialchars($details['user_email'] ?? 'No Email') ?></td></tr>
                        <tr><td><strong>Phone</strong></td><td><?= htmlspecialchars($details['user_phone'] ?? 'No Phone') ?></td></tr>
                        <tr><td><strong>Created</strong></td><td><?= htmlspecialchars($details['created_at']) ?></td></tr>
                        <tr><td><strong>Updated</strong></td><td><?= htmlspecialchars($details['updated_at']) ?></td></tr>
                        <tr><td><strong>Paid At</strong></td><td><?= htmlspecialchars($details['paid_at'] ?: 'Not set') ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

            <?php if (empty($recent_payments)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fas fa-search fa-3x mb-3"></i>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Reference</th>
                            <th>course</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment): ?>
                        <tr>
                            <td><?= $payment['id'] ?></td>
                            <td>
                                <a href="?reference=<?= urlencode($payment['order_tracking_id'] ?? '') ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($payment['order_tracking_id'] ?? 'N/A') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($payment['course_name'] ?? 'Unknown course') ?></td>
                            <td>TSH <?= number_format($payment['amount'], 0) ?></td>
                            <td>
                                <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($payment['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($payment['user_email'] ?? 'No Email') ?></td>
                            <td><?= date('M j, Y H:i', strtotime($payment['created_at'])) ?></td>
                            <td>
                                <?php if ($payment['status'] === 'pending'): ?>
                                <a href="../fix_pending_payments.php?action=complete&payment_id=<?= $payment['id'] ?>" 
                                   class="btn btn-success btn-sm me-1" title="Mark as Completed"
                                   onclick="return confirm('Mark this payment as completed?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="../fix_pending_payments.php?action=fail&payment_id=<?= $payment['id'] ?>" 
                                   class="btn btn-danger btn-sm" title="Mark as Failed"
                                   onclick="return confirm('Mark this payment as failed?')">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once '../includes/admin_footer.php'; ?>
