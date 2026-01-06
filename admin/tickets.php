<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$total_stmt = $pdo->query("SELECT COUNT(*) FROM tickets");
$total_tickets = $total_stmt->fetchColumn();
$total_pages = ceil($total_tickets / $per_page);

$tickets_stmt = $pdo->prepare("
    SELECT t.*, u.name AS username 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.updated_at DESC
    LIMIT :limit OFFSET :offset
");
$tickets_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$tickets_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$tickets_stmt->execute();
$tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Support Tickets - AK23 App';
include '../includes/admin_header.php';
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3" style="color: black;">Support Tickets</h1>
  <a href="add_ticket.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Ticket</a>
</div>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">Ticket saved successfully.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Ticket deleted successfully.</div><?php endif; ?>
<div class="table-responsive">
  <table class="table table-striped table-bordered align-middle">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Subject</th>
        <th>User</th>
        <th>Status</th>
        <th>Last Updated</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tickets as $ticket): ?>
        <tr>
          <td><?= $ticket['id'] ?></td>
          <td><?= htmlspecialchars($ticket['subject']) ?></td>
          <td><?= htmlspecialchars($ticket['username']) ?></td>
          <td><?= htmlspecialchars($ticket['status']) ?></td>
          <td><?= htmlspecialchars($ticket['updated_at']) ?></td>
          <td>
            <a href="edit_ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit/Reply</a>
            <a href="delete_ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this ticket?');"><i class="fas fa-trash"></i> Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">
    <?php if ($page > 1): ?>
      <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a></li>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <li class="page-item <?= ($i == $page) ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">Next</a></li>
    <?php endif; ?>
  </ul>
</nav>

<?php include '../includes/admin_footer.php'; ?> 