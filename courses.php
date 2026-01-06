<?php
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/includes/auth.php'; // starts session if needed
require_once __DIR__ . '/includes/base.php';

// Page meta
$title = 'Courses - AK23 App';

// Filters
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Pagination params
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = [];
$bind = [];
if ($q !== '') {
  $where[] = '(p.name LIKE :q OR p.slug LIKE :q)';
  $bind[':q'] = '%' . $q . '%';
}
if ($categoryId > 0) {
  $where[] = 'p.category_id = :cat';
  $bind[':cat'] = $categoryId;
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// Total count with filters
$countSql = "SELECT COUNT(*) FROM courses p" . $whereSql;
$countStmt = $pdo->prepare($countSql);
foreach ($bind as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));

// Fetch courses page with category name
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name
                       FROM courses p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       " . $whereSql . "
                       ORDER BY p.created_at DESC 
                       LIMIT :limit OFFSET :offset");
foreach ($bind as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter dropdown
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>
<main class="flex-grow-1 p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Courses</h1>
        <?php if (!empty($_SESSION['admin_logged_in'])): ?>
            <a href="<?= $base ?>/admin/add_course.php" class="btn btn-primary">Add Course</a>
        <?php endif; ?>
    </div>
    <form method="get" class="row g-2 mb-3">
      <div class="col-sm-6 col-md-5 col-lg-4">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Search by name or slug">
      </div>
      <div class="col-sm-4 col-md-3 col-lg-3">
        <select name="category" class="form-select">
          <option value="0">All Categories</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($categoryId === (int)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2 col-md-2 col-lg-2 d-grid">
        <button class="btn btn-warning" type="submit">Search</button>
      </div>
      <?php if ($q !== '' || $categoryId > 0): ?>
      <div class="col-auto">
        <a class="btn btn-outline-secondary" href="courses.php">Reset</a>
      </div>
      <?php endif; ?>
    </form>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>File Size</th>
                    <th>Active</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?= $course['id'] ?></td>
                        <td><?= htmlspecialchars($course['name']) ?></td>
                        <td><?= htmlspecialchars($course['category_name'] ?? '-') ?></td>
                        <td>
                            <?= ((float)$course['price'] <= 0)
                                ? '<span class="badge bg-success">FREE</span>'
                                : 'TZS ' . number_format((float)$course['price'], 0) ?>
                        </td>
                        <td><?= htmlspecialchars($course['file_size']) ?></td>
                        <td><?= $course['is_active'] ? 'Yes' : 'No' ?></td>
                        <td><?= htmlspecialchars($course['created_at']) ?></td>
                        <td>
                            <?php if (!empty($_SESSION['admin_logged_in'])): ?>
                                <a href="<?= $base ?>/admin/edit_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="<?= $base ?>/admin/delete_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this course?');">Delete</a>
                            <?php else: ?>
                                <a href="<?= $base ?>/course-details.php?slug=<?= rawurlencode((string)($course['slug'] ?? '')) ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
      <nav aria-label="Courses pagination">
        <ul class="pagination justify-content-center mt-3">
          <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">Previous</a>
          </li>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
</main>
<?php include 'includes/footer.php'; ?>