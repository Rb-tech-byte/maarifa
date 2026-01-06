<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

// Handle add category (supports parent_id, slug, description)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-');
    if ($name) {
        // Ensure unique slug by appending number if exists
        $baseSlug = $slug !== '' ? $slug : strtolower(preg_replace('/[^a-z0-9]+/i', '-', uniqid('cat-')));
        $slug = $baseSlug;
        $i = 1;
        while (true) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM course_categories WHERE slug = ?");
            $chk->execute([$slug]);
            if ($chk->fetchColumn() == 0) break;
            $slug = $baseSlug . '-' . (++$i);
        }
        $stmt = $pdo->prepare("INSERT INTO course_categories (parent_id, name, slug, description, is_active) VALUES (?, ?, ?, ?, 1)");
        if (!$stmt->execute([$parent_id, $name, $slug, $description])) {
            $error = 'Failed to add category.';
        }
    } else {
        $error = 'Category name is required.';
    }
}

// Handle delete category
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM course_categories WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: course_categories.php?deleted=true');
    exit();
}

// Handle update category (supports parent + slug regen if name changed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    if ($name) {
        // Build new slug candidate from name
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');
        $baseSlug = $slug !== '' ? $slug : strtolower(preg_replace('/[^a-z0-9]+/i', '-', uniqid('cat-')));
        $slug = $baseSlug;
        $i = 1;
        while (true) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM course_categories WHERE slug = ? AND id <> ?");
            $chk->execute([$slug, $id]);
            if ($chk->fetchColumn() == 0) break;
            $slug = $baseSlug . '-' . (++$i);
        }
        $stmt = $pdo->prepare("UPDATE course_categories SET parent_id = ?, name = ?, slug = ?, description = ? WHERE id = ?");
        $stmt->execute([$parent_id, $name, $slug, $description, $id]);
        header('Location: course_categories.php?success=true');
        exit();
    } else {
        $error = 'Category name is required.';
    }
}

// Pagination logic
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$total = $pdo->query("SELECT COUNT(*) FROM course_categories")->fetchColumn();
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;
$categories = $pdo->prepare("SELECT * FROM course_categories ORDER BY COALESCE(parent_id, id), name ASC LIMIT :offset, :perPage");
$categories->bindValue(':offset', $offset, PDO::PARAM_INT);
$categories->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$categories->execute();
$categories = $categories->fetchAll(PDO::FETCH_ASSOC);

$title = 'Manage Categories - AK23 App';
include '../includes/admin_header.php';

// Fetch all categories for parent dropdown
$allCats = $pdo->query("SELECT id, name, parent_id FROM course_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$parents = array_filter($allCats, function($c){ return empty($c['parent_id']); });
?>

<style>
.category-form-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    margin-bottom: 1.5rem;
}
.category-form-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
}
.card-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.25rem 1.5rem;
    border: none;
}
.category-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #e0e0e0;
}
.category-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<main class="admin-main container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1" style="color: #2c3e50;">
                <i class="fas fa-folder-open me-2 text-primary"></i>Manage Categories
            </h1>
            <p class="text-muted mb-0">Organize courses with categories and subcategories</p>
        </div>
        <a href="courses.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Courses
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>Category saved successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>Category deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column - Add Category Form -->
        <div class="col-lg-4">
            <div class="card category-form-card">
                <div class="card-header card-header-modern">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Add New Category
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-tag me-1 text-primary"></i>Category Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" class="form-control" 
                                   placeholder="Enter category name..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-sitemap me-1 text-info"></i>Parent Category
                            </label>
                            <select name="parent_id" class="form-select">
                                <option value="">No Parent (Top-level)</option>
                                <?php foreach ($parents as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Select a parent to create a subcategory</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-align-left me-1 text-secondary"></i>Description
                            </label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Optional description..."></textarea>
                        </div>
                        <button type="submit" name="add_category" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Add Category
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column - Categories List -->
        <div class="col-lg-8">
            <!-- Category Cards Grid -->
            <?php if (!empty($categories)): ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($categories as $cat): ?>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="card h-100 shadow-sm category-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($cat['name']) ?></h6>
                                        <?php if (!empty($cat['parent_id'])): ?>
                                            <span class="badge bg-info">Subcategory</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Parent</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($cat['description'])): ?>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars(mb_substr($cat['description'], 0, 60)) ?><?= mb_strlen($cat['description']) > 60 ? '...' : '' ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2 mt-3">
                                        <a href="course_categories.php?edit=<?= $cat['id'] ?>" 
                                           class="btn btn-sm btn-warning flex-fill">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="course_categories.php?delete=<?= $cat['id'] ?>" 
                                           class="btn btn-sm btn-danger flex-fill"
                                           onclick="return confirm('Delete this category? This action cannot be undone.');">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No categories found. Create your first category using the form on the left.
                </div>
            <?php endif; ?>

            <!-- Detailed Table View -->
            <div class="card category-form-card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>All Categories
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Parent</th>
                                    <th>Slug</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><?= $cat['id'] ?></td>
                                        <td>
                                            <?php if (isset($_GET['edit']) && $_GET['edit'] == $cat['id']): ?>
                                                <form method="POST" class="d-flex align-items-center flex-wrap gap-2">
                                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                    <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>" 
                                                           class="form-control" style="min-width:160px;" required>
                                                    <select name="parent_id" class="form-select" style="min-width:180px;">
                                                        <option value="">No Parent</option>
                                                        <?php foreach ($parents as $p): ?>
                                                            <option value="<?= (int)$p['id'] ?>" 
                                                                    <?= (!empty($cat['parent_id']) && (int)$cat['parent_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($p['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <textarea name="description" class="form-control" rows="2" 
                                                              style="min-width:200px;" placeholder="Description..."><?= htmlspecialchars($cat['description'] ?? '') ?></textarea>
                                                    <button type="submit" name="edit_category" class="btn btn-success btn-sm">
                                                        <i class="fas fa-save me-1"></i>Save
                                                    </button>
                                                    <a href="course_categories.php" class="btn btn-secondary btn-sm">
                                                        <i class="fas fa-times me-1"></i>Cancel
                                                    </a>
                                                </form>
                                            <?php else: ?>
                                                <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $parentName = '';
                                            if (!empty($cat['parent_id'])) {
                                                foreach ($allCats as $p) {
                                                    if ((int)$p['id'] === (int)$cat['parent_id']) {
                                                        $parentName = $p['name'];
                                                        break;
                                                    }
                                                }
                                            }
                                            echo $parentName ? htmlspecialchars($parentName) : '<span class="text-muted">—</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <code class="small"><?= htmlspecialchars($cat['slug'] ?? '') ?></code>
                                        </td>
                                        <td>
                                            <a href="course_categories.php?edit=<?= $cat['id'] ?>" 
                                               class="btn btn-warning btn-sm mb-1">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                            <a href="course_categories.php?delete=<?= $cat['id'] ?>" 
                                               class="btn btn-danger btn-sm ms-1 mb-1" 
                                               onclick="return confirm('Delete this category? This action cannot be undone.');">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <nav aria-label="Categories pagination" class="mt-3">
                    <ul class="pagination justify-content-center flex-wrap">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item<?= $i == $page ? ' active' : '' ?>">
                                <a class="page-link" href="course_categories.php?page=<?= $i ?><?= isset($_GET['edit']) ? '&edit=' . intval($_GET['edit']) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/admin_footer.php'; ?>
