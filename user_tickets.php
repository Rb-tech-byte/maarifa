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
$tickets = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC");
$tickets->execute([$user_id]);
$tickets = $tickets->fetchAll(PDO::FETCH_ASSOC);

// Mark tickets with admin replies as viewed
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS user_ticket_views (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      ticket_id INT NOT NULL,
      viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_user_ticket (user_id, ticket_id),
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
    )
  ");

  // Insert view records for tickets that have admin replies and haven't been viewed yet
  $stmt = $pdo->prepare("
    INSERT IGNORE INTO user_ticket_views (user_id, ticket_id)
    SELECT ?, t.id
    FROM tickets t
    INNER JOIN ticket_replies tr ON t.id = tr.ticket_id
    LEFT JOIN admin_users au ON tr.user_id = au.id
    WHERE t.user_id = ? AND au.id IS NOT NULL
    AND t.status != 'closed'
  ");
  $stmt->execute([$user_id, $user_id]);
} catch (Exception $e) {
  // Silently handle errors - don't break the page
}

$title = 'My Tickets - AK23 App';
include 'includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">My Support Tickets</h1>
    <a href="user_ticket_submit.php" class="btn btn-primary"><i class="fas fa-plus"></i> Submit Ticket</a>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Subject</th>
          <th>Status</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <tr>
            <td><?= $ticket['id'] ?></td>
            <td><?= htmlspecialchars($ticket['subject']) ?></td>
            <td><?= htmlspecialchars($ticket['status']) ?></td>
            <td><?= htmlspecialchars($ticket['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?> 