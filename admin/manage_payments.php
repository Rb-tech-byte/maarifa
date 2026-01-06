<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;
$total_stmt = $pdo->query("SELECT COUNT(*) FROM payments");
$total_payments = $total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil(($total_payments ?: 0) / $limit));

$sql = "SELECT 
            p.id, p.order_id, p.amount, p.currency, p.status, p.payment_method AS method, p.order_tracking_id AS transaction_code, p.created_at, p.paid_at,
            o.status as order_status,
            pr.title as course_name,
            u.name AS username, u.email as user_email
        FROM payments p
        LEFT JOIN orders o ON p.order_id = o.id
        LEFT JOIN courses pr ON o.course_id = pr.id
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$title = 'Manage Payments - AK23 App';
include '../includes/admin_header.php';
?>
    <h1 style="color:#000;">Manage Payments</h1>
    <?php if (isset($_GET['success'])): ?><div class="alert alert-success">Payment updated successfully.</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Payment deleted successfully.</div><?php endif; ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Order ID</th>
                    <th>User</th>
                    <th>course</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Transaction ID</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= $payment['id'] ?></td>
                        <td><a href="orders.php?id=<?= $payment['order_id'] ?>"><?= $payment['order_id'] ?></a></td>
                        <td><?= htmlspecialchars((string)($payment['username'] ?? 'Guest'), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)($payment['user_email'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?>)</td>
                        <td><?= htmlspecialchars((string)($payment['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($payment['currency'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)($payment['amount'] ?? 0), 2) ?></td>
                        <td><?= htmlspecialchars((string)($payment['method'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($payment['transaction_code'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge bg-<?= strtolower($payment['status']) === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst(strtolower($payment['status'])) ?></span></td>
                        <td><?= date('Y-m-d H:i', strtotime($payment['paid_at'] ?? $payment['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
{{ ... }}
    <nav aria-label="Page navigation">
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