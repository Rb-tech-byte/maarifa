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

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : 'student'; // Default to students only

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query for regular users (exclude instructors)
$where_conditions = ["role != 'instructor' OR role IS NULL"];
$params = [];

if ($search) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($role_filter && $role_filter !== 'all') {
    if ($role_filter === 'student') {
        $where_conditions[] = "(role = 'student' OR role IS NULL)";
    } else {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get total number of users
$count_sql = "SELECT COUNT(*) FROM users WHERE {$where_clause}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Fetch users for the current page
$sql = "SELECT id, name, email, phone, role, created_at FROM users WHERE {$where_clause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$title = 'Users - AK23 App';
include '../includes/admin_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3 mb-1" style="color: black;"><i class="fas fa-users me-2"></i>Users Management</h1>
    <p class="text-muted mb-0">Manage regular users and students</p>
  </div>
  <div>
    <a href="instructors_list.php" class="btn btn-outline-info me-2">
      <i class="fas fa-chalkboard-teacher me-1"></i> View Instructors
    </a>
    <a href="add_user.php" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i> Add User
    </a>
  </div>
</div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>User saved successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>User deleted successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Search and Filter -->
<div class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <form method="get" class="row g-3">
      <div class="col-md-4">
        <label class="form-label fw-bold">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold">Role Filter</label>
        <select name="role" class="form-select">
          <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Students Only</option>
          <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Users</option>
          <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admins</option>
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">
          <i class="fas fa-search me-1"></i> Search
        </button>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <a href="users.php" class="btn btn-outline-secondary w-100">
          <i class="fas fa-redo me-1"></i> Reset
        </a>
      </div>
    </form>
  </div>
</div>
<div class="card shadow-sm border-0">
  <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="fas fa-list me-2"></i>Users List
      <span class="badge bg-light text-dark ms-2"><?= $total_users ?> total</span>
    </h5>
  </div>
  <div class="card-body p-0">
<div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
            <th>Role</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="7" class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <p class="text-muted">No users found.</p>
              </td>
            </tr>
          <?php else: ?>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><?= $user['id'] ?></td>
                <td>
                  <div class="fw-bold"><?= htmlspecialchars($user['name']) ?></div>
                </td>
          <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                <td>
                  <?php
                  $role = $user['role'] ?? 'student';
                  $badge_class = $role === 'admin' ? 'bg-danger' : ($role === 'instructor' ? 'bg-info' : 'bg-secondary');
                  ?>
                  <span class="badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($role)) ?></span>
                </td>
                <td>
                  <small><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                </td>
          <td>
                  <div class="btn-group" role="group">
                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>
                    <a href="delete_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" 
                       onclick="return confirm('Are you sure you want to delete this user?');" title="Delete">
                      <i class="fas fa-trash"></i>
                    </a>
                    <a href="impersonate.php?user_id=<?= (int)$user['id'] ?>" class="btn btn-sm btn-dark" 
                       target="_blank" title="Login as this user">
                      <i class="fas fa-user-secret"></i>
                    </a>
                  </div>
          </td>
        </tr>
      <?php endforeach; ?>
          <?php endif; ?>
    </tbody>
  </table>
    </div>
  </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
  <ul class="pagination justify-content-center">
    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
    </li>
            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++): 
            ?>
    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>">
                        <?= $i ?>
                    </a>
    </li>
    <?php endfor; ?>
    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
    </li>
  </ul>
</nav>
<?php endif; ?>

<?php include '../includes/admin_footer.php'; ?> 