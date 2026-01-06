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
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build WHERE clause for status filter
$where_clause = "";
$params = [];
if ($status_filter !== 'all') {
    $where_clause = "WHERE status = ?";
    $params[] = $status_filter;
}

// Get total count
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages $where_clause");
$total_stmt->execute($params);
$total_messages = $total_stmt->fetchColumn();
$total_pages = ceil($total_messages / $per_page);

// Get messages with pagination
$query = "
    SELECT cm.*,
           CASE WHEN cm.user_id IS NOT NULL THEN u.name ELSE cm.name END as display_name,
           CASE WHEN cm.user_id IS NOT NULL THEN u.email ELSE cm.email END as display_email
    FROM contact_messages cm
    LEFT JOIN users u ON cm.user_id = u.id
    $where_clause
    ORDER BY cm.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$messages_stmt = $pdo->prepare($query);
$messages_stmt->execute($params);
$messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status counts for filter tabs
$status_counts = [];
$status_query = $pdo->query("SELECT status, COUNT(*) as count FROM contact_messages GROUP BY status");
while ($row = $status_query->fetch(PDO::FETCH_ASSOC)) {
    $status_counts[$row['status']] = $row['count'];
}

$title = 'Contact Messages - AK23 App';
include '../includes/admin_header.php';
?>

<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
.status-badge {
  font-size: 0.8em;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3" style="color: black;">Contact Messages</h1>
  <div>
    <span class="badge bg-info me-2">Total: <?= $total_messages ?></span>
  </div>
</div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">Message updated successfully.</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success">Message deleted successfully.</div>
<?php endif; ?>

<!-- Status Filter Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $status_filter === 'all' ? 'active' : '' ?>" href="?status=all">
      All <span class="badge bg-secondary ms-1"><?= array_sum($status_counts) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $status_filter === 'new' ? 'active' : '' ?>" href="?status=new">
      New <span class="badge bg-danger ms-1"><?= $status_counts['new'] ?? 0 ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $status_filter === 'read' ? 'active' : '' ?>" href="?status=read">
      Read <span class="badge bg-warning ms-1"><?= $status_counts['read'] ?? 0 ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $status_filter === 'replied' ? 'active' : '' ?>" href="?status=replied">
      Replied <span class="badge bg-info ms-1"><?= $status_counts['replied'] ?? 0 ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $status_filter === 'closed' ? 'active' : '' ?>" href="?status=closed">
      Closed <span class="badge bg-success ms-1"><?= $status_counts['closed'] ?? 0 ?></span>
    </a>
  </li>
</ul>

<div class="table-responsive">
  <table class="table table-striped table-bordered align-middle">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Subject</th>
        <th>Category</th>
        <th>Status</th>
        <th>Priority</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($messages as $message): ?>
        <tr>
          <td><?= $message['id'] ?></td>
          <td>
            <?= htmlspecialchars($message['display_name']) ?>
            <?php if ($message['user_id']): ?>
              <br><small class="text-muted">Registered User</small>
            <?php else: ?>
              <br><small class="text-muted">Guest</small>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($message['display_email']) ?></td>
          <td>
            <strong><?= htmlspecialchars($message['subject']) ?></strong>
            <?php if (strlen($message['message']) > 100): ?>
              <br><small class="text-muted"><?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...</small>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge bg-light text-dark">
              <?= htmlspecialchars(ucfirst($message['category'])) ?>
            </span>
          </td>
          <td>
            <?php
            $status_colors = [
              'new' => 'danger',
              'read' => 'warning',
              'replied' => 'info',
              'closed' => 'success'
            ];
            $color = $status_colors[$message['status']] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $color ?> status-badge">
              <?= htmlspecialchars(ucfirst($message['status'])) ?>
            </span>
          </td>
          <td>
            <?php
            $priority_colors = [
              'low' => 'secondary',
              'normal' => 'primary',
              'high' => 'danger'
            ];
            $p_color = $priority_colors[$message['priority']] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $p_color ?>">
              <?= htmlspecialchars(ucfirst($message['priority'])) ?>
            </span>
          </td>
          <td>
            <?= htmlspecialchars(date('M d, Y', strtotime($message['created_at']))) ?>
            <br><small class="text-muted"><?= htmlspecialchars(date('H:i', strtotime($message['created_at']))) ?></small>
          </td>
          <td>
            <div class="btn-group" role="group">
              <a href="edit_contact_message.php?id=<?= $message['id'] ?>" class="btn btn-sm btn-warning" title="View & Reply">
                <i class="fas fa-eye"></i> View
              </a>
              <a href="delete_contact_message.php?id=<?= $message['id'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('Are you sure you want to delete this contact message?');" title="Delete">
                <i class="fas fa-trash"></i>
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (empty($messages)): ?>
        <tr>
          <td colspan="9" class="text-center text-muted py-4">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <br>No contact messages found.
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">
    <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>">Previous</a>
      </li>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&status=<?= $status_filter ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>">Next</a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>

<?php include '../includes/admin_footer.php'; ?>
