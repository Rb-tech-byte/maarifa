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
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query for instructors
$where_conditions = ["u.role = 'instructor'"];
$params = [];

if ($search) {
    $where_conditions[] = "(i.name LIKE ? OR u.email LIKE ? OR iw.wallet_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter && $status_filter !== 'all') {
    if ($status_filter === 'approved') {
        $where_conditions[] = "i.approved = 1";
    } elseif ($status_filter === 'pending') {
        $where_conditions[] = "i.approved = 0";
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get total number of instructors
$count_sql = "SELECT COUNT(*) 
              FROM instructors i
              JOIN users u ON i.user_id = u.id
              LEFT JOIN instructor_wallet iw ON i.id = iw.instructor_id
              WHERE {$where_clause}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_instructors = $count_stmt->fetchColumn();
$total_pages = ceil($total_instructors / $limit);

// Fetch instructors for the current page
$sql = "SELECT i.id, i.name, i.bio, i.approved, i.created_at,
              u.id AS user_id, u.email, u.phone,
              iw.wallet_number, iw.balance, iw.total_earned,
              (SELECT COUNT(*) FROM instructor_course_assignments WHERE instructor_id = i.id) AS courses_count,
              (SELECT COUNT(DISTINCT e.user_id) 
               FROM instructor_course_assignments ica
               JOIN enrollments e ON ica.course_id = e.course_id
               WHERE ica.instructor_id = i.id) AS students_count,
              (SELECT COUNT(*) FROM instructor_withdraw_requests WHERE instructor_id = i.id AND status = 'pending') AS pending_withdrawals
       FROM instructors i
       JOIN users u ON i.user_id = u.id
       LEFT JOIN instructor_wallet iw ON i.id = iw.instructor_id
       WHERE {$where_clause}
       ORDER BY i.created_at DESC
       LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Instructors List - Admin';
include '../includes/admin_header.php';
?>

<style>
.instructor-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.instructor-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
}
</style>

<main class="flex-grow-1 p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1" style="color: black;">
                <i class="fas fa-chalkboard-teacher me-2"></i>Instructors Management
            </h1>
            <p class="text-muted mb-0">Manage instructors, their courses, and finances</p>
        </div>
        <div>
            <a href="users.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-users me-1"></i> View Users
            </a>
            <a href="manage_instructors.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Instructor
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>Operation completed successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, or wallet number..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Status Filter</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Instructors</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved Only</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Approval</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="instructors_list.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Instructors List -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Instructors List
                <span class="badge bg-light text-dark ms-2"><?= $total_instructors ?> total</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($instructors)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No instructors found.</p>
                    <a href="manage_instructors.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add First Instructor
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Instructor</th>
                                <th>Wallet</th>
                                <th>Balance</th>
                                <th>Total Earned</th>
                                <th>Courses</th>
                                <th>Students</th>
                                <th>Pending Withdrawals</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($instructors as $inst): ?>
                                <tr>
                                    <td><?= $inst['id'] ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($inst['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($inst['email']) ?></small>
                                        <?php if ($inst['phone']): ?>
                                            <br><small class="text-muted"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($inst['phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="text-primary"><?= htmlspecialchars($inst['wallet_number'] ?? 'N/A') ?></code>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary">TZS <?= number_format((float)($inst['balance'] ?? 0), 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">TZS <?= number_format((float)($inst['total_earned'] ?? 0), 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= (int)$inst['courses_count'] ?> courses</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?= (int)$inst['students_count'] ?> students</span>
                                    </td>
                                    <td>
                                        <?php if ((int)$inst['pending_withdrawals'] > 0): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i><?= (int)$inst['pending_withdrawals'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$inst['approved']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Approved
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="manage_instructors.php?edit=<?= $inst['id'] ?>" 
                                               class="btn btn-sm btn-warning" title="Edit / Assign Courses">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="instructor_finances.php?tab=transfer&instructor_id=<?= $inst['id'] ?>" 
                                               class="btn btn-sm btn-primary" title="Transfer Money">
                                                <i class="fas fa-paper-plane"></i>
                                            </a>
                                            <a href="instructor_finances.php?tab=transactions&instructor_id=<?= $inst['id'] ?>" 
                                               class="btn btn-sm btn-info" title="View Transactions">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" 
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="impersonate.php?user_id=<?= (int)$inst['user_id'] ?>" target="_blank">
                                                            <i class="fas fa-user-secret me-2"></i> Login as Instructor
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" 
                                                           href="delete_user.php?id=<?= (int)$inst['user_id'] ?>" 
                                                           onclick="return confirm('Are you sure? This will delete the instructor and all related data.');">
                                                            <i class="fas fa-trash me-2"></i> Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                </li>
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<?php include '../includes/admin_footer.php'; ?>

