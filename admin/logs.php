<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

// Ensure ps_logs table exists (self-healing)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ps_logs` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `level` VARCHAR(20) NOT NULL DEFAULT 'info',
      `message` TEXT NOT NULL,
      `context` LONGTEXT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_level_created` (`level`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {
    // If creation fails, continue; page will show a friendly error below
}

// Handle delete log
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM ps_logs WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: logs.php?deleted=1');
    exit();
}

$logs = [];
try {
    $logs = $pdo->query("SELECT * FROM ps_logs ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $logs = [];
}
$title = 'System Logs - AK23 App';
include '../includes/admin_header.php';
?>
<main class="admin-main container">
    <h1 style="color:#000;">System Logs</h1>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Log entry deleted.</div><?php endif; ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Level</th>
                    <th>Message</th>
                    <th>Context</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td>
                            <?php 
                                $level = strtolower($log['level']);
                                $color = 'secondary';
                                if ($level === 'error' || $level === 'critical') $color = 'danger';
                                if ($level === 'warning') $color = 'warning';
                                if ($level === 'info') $color = 'info';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= htmlspecialchars(strtoupper($log['level'])) ?></span>
                        </td>
                        <td style="word-break: break-word;"><?= htmlspecialchars($log['message']) ?></td>
                        <td><pre style="white-space: pre-wrap; word-break: break-all; max-width: 400px; background: #f1f1f1; padding: 8px; border-radius: 4px; font-size: 0.8rem;"><?= htmlspecialchars(json_encode(json_decode($log['context']), JSON_PRETTY_PRINT)) ?></pre></td>
                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                        <td>
                            <a href="logs.php?delete=<?= $log['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this log entry?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/admin_footer.php'; ?>