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
                <div class="col-md-6">
                    <label for="reference" class="form-label">Payment Reference</label>
                    <input type="text" class="form-control" id="reference" name="reference" 
                           value="<?= htmlspecialchars($reference) ?>" placeholder="Enter payment reference">
                </div>
                <div class="col-md-4">
{{ ... }}
                            <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                <?= ucfirst($payment['status']) ?>
                            </span>
                        </td></tr>
                        <tr><td><strong>Reference</strong></td><td><?= htmlspecialchars($payment['order_tracking_id'] ?? 'N/A') ?></td></tr>
                        <tr><td><strong>Email</strong></td><td><?= htmlspecialchars($payment['user_email'] ?? 'No Email') ?></td></tr>
    64→                        <tr><td><strong>Phone</strong></td><td><?= htmlspecialchars($payment['user_phone'] ?? 'No Phone') ?></td></tr>
                        <tr><td><strong>Created</strong></td><td><?= $payment['created_at'] ?></td></tr>
                        <tr><td><strong>Updated</strong></td><td><?= $payment['updated_at'] ?></td></tr>
                        <tr><td><strong>Paid At</strong></td><td><?= $payment['paid_at'] ?: 'Not set' ?></td></tr>
    131→            // Pagination params
    132→            $rp_page = isset($_GET['rp_page']) ? max(1, (int)$_GET['rp_page']) : 1;
{{ ... }}
    152→
    153→            // Paged query
    154→            $sql = "SELECT p.*, pr.name as course_name, u.email AS user_email, u.phone AS user_phone " . $fromJoins . $where . " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
    155→            $stmt = $pdo->prepare($sql);
    156→            // bind search params first
    157→            foreach ($params as $idx => $val) { $stmt->bindValue($idx + 1, $val, PDO::PARAM_STR); }
    158→            // then bind limit/offset
    159→            $stmt->bindValue(':limit', $rp_limit, PDO::PARAM_INT);
    160→            $stmt->bindValue(':offset', $rp_offset, PDO::PARAM_INT);
    161→            $stmt->execute();
    162→            $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <?php if (empty($recent_payments)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fas fa-search fa-3x mb-3"></i>
{{ ... }}
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
                            <td><?= htmlspecialchars($payment['email'] ?? 'No Email') ?></td>
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
