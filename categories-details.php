<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$category = null;
$courses = [];
$error = '';
// Pagination params
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 8; // two rows per page on typical 4-column grids
$offset = ($page - 1) * $limit;
$total = 0;
$total_pages = 1;

if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM course_categories WHERE slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($category) {
        // Build list of this category id plus all descendant (sub)category ids
        $catIds = [(int)$category['id']];
        try {
            $allCats = $pdo->query("SELECT id, parent_id FROM course_categories WHERE is_active = 1")
                           ->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $childrenByParent = [];
            foreach ($allCats as $r) {
                $pid = isset($r['parent_id']) ? (int)$r['parent_id'] : 0;
                $cid = (int)$r['id'];
                if (!isset($childrenByParent[$pid])) $childrenByParent[$pid] = [];
                $childrenByParent[$pid][] = $cid;
            }
            // BFS to collect descendants
            $queue = [(int)$category['id']];
            $seen = [];
            while ($queue) {
                $cur = array_shift($queue);
                if (isset($seen[$cur])) continue;
                $seen[$cur] = true;
                if (!in_array($cur, $catIds, true)) $catIds[] = $cur;
                if (isset($childrenByParent[$cur])) {
                    foreach ($childrenByParent[$cur] as $ch) { $queue[] = (int)$ch; }
                }
            }
        } catch (Throwable $e) {
            // Fallback to only the current category on any error
            $catIds = [(int)$category['id']];
        }

        // Prepare placeholders for IN clause
        $inPh = [];
        $bind = [];
        foreach ($catIds as $i => $cid) { $ph = ':c'.$i; $inPh[] = $ph; $bind[$ph] = (int)$cid; }
        $inList = implode(',', $inPh);

        // Count total courses for category + descendants (courses table has no is_active/deleted_at)
        $sqlCnt = "SELECT COUNT(*) FROM courses WHERE category_id IN ($inList)";
        $cnt = $pdo->prepare($sqlCnt);
        foreach ($bind as $k=>$v) { $cnt->bindValue($k, $v, PDO::PARAM_INT); }
        $cnt->execute();
        $total = (int)$cnt->fetchColumn();
        $total_pages = max(1, (int)ceil($total / $limit));

        // Fetch paginated courses for category + descendants
        $sql = "\n            SELECT p.*, c.name AS category_name, c.slug AS category_slug\n            FROM courses p\n            JOIN course_categories c ON p.category_id = c.id\n            WHERE p.category_id IN ($inList)\n            ORDER BY p.created_at DESC, p.id DESC\n            LIMIT :limit OFFSET :offset\n        ";
        $stmt = $pdo->prepare($sql);
        foreach ($bind as $k=>$v) { $stmt->bindValue($k, $v, PDO::PARAM_INT); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Fetch images for each course
        foreach ($courses as &$course) {
            $stmtImg = $pdo->prepare("SELECT value, type FROM medias WHERE courses_id = ? AND file_type = 'image' AND deleted_at IS NULL ORDER BY id ASC");
            $stmtImg->execute([$course['id']]);
            $course['images'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($course);
    } else {
        $error = 'Category not found.';
    }
} else {
    $error = 'No category specified.';
}
$title = $category ? htmlspecialchars($category['name']) . ' - Category' : 'Category Details';
// Expose parent slug when on a subcategory so the main category stays highlighted in the header strip
$currentCategorySlug = '';
if ($category) {
    if (!empty($category['parent_id'])) {
        try {
            $pstmt = $pdo->prepare("SELECT slug FROM course_categories WHERE id = ? LIMIT 1");
            $pstmt->execute([(int)$category['parent_id']]);
            $parent = $pstmt->fetch(PDO::FETCH_ASSOC);
            $currentCategorySlug = isset($parent['slug']) ? (string)$parent['slug'] : (string)$category['slug'];
        } catch (PDOException $e) {
            $currentCategorySlug = (string)$category['slug'];
        }
    } else {
        $currentCategorySlug = (string)$category['slug'];
    }
}

// Fetch top-level categories for quick navigation
$topCats = [];
try {
    $stmt = $pdo->query("SELECT id, name, slug FROM course_categories WHERE parent_id IS NULL AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
    $topCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topCats = [];
}

// Fetch subcategories of the current category (if any)
$subCats = [];
if ($category) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, slug FROM course_categories WHERE parent_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
        $stmt->execute([$category['id']]);
        $subCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subCats = [];
    }
}

require_once 'includes/header.php';
?>
<div class="container py-4">
    <?php if (!$error && $category): ?>
      <nav class="breadcrumb mb-3 small d-none d-md-flex">
        <a class="breadcrumb-item" href="index.php">Home</a>
        <span class="breadcrumb-item active"><?php echo htmlspecialchars($category['name']); ?></span>
      </nav>
    <?php endif; ?>

    

    <?php if (!empty($subCats)): ?>
      <div class="mb-3">
        <h3 class="h6 text-muted mb-2">Subcategories</h3>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($subCats as $sc): ?>
            <a href="categories-details.php?slug=<?php echo rawurlencode((string)($sc['slug'] ?? '')); ?>"
               class="badge rounded-pill text-dark"
               style="background:#e8f4ff; padding:0.5rem 0.8rem; font-weight:600; color:#000 !important; text-decoration:none !important;">
               <?php echo htmlspecialchars($sc['name']); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger text-center my-4"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <h2 class="mb-3" style="font-weight:700; color:#23272b; letter-spacing:1px;">
            <?php echo htmlspecialchars($category['name']); ?>
        </h2>
        <?php if (!empty($category['description'])): ?>
            <p class="mb-4" style="color:#888; font-size:1.1em; max-width:600px;">
                <?php echo nl2br(htmlspecialchars($category['description'])); ?>
            </p>
        <?php endif; ?>
        <div class="course-grid">
            <?php if (empty($courses)): ?>
                <div class="col-12"><p class="text-center text-muted py-5">No courses found in this category.</p></div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <?php
                    if (!empty($course['cover_image'])) {
                        $imgSrc = (string)$course['cover_image'];
                    } elseif (!empty($course['images'])) {
                        $imgSrc = (string)$course['images'][0]['value'];
                    } else {
                        $imgSrc = 'assets/images/ak.png';
                    }
                    $imgSrc = (str_starts_with($imgSrc, 'http') || str_starts_with($imgSrc, '/')) ? $imgSrc : ($base . '/' . ltrim($imgSrc, '/'));
                    $courseTitle = $course['title'] ?? $course['name'] ?? '';
                    $courseUrl = 'course-details.php?slug=' . rawurlencode((string)($course['slug'] ?? ''));
                    ?>
                    <div class="course-card-wrapper">
                      <a href="<?php echo $courseUrl; ?>" class="course-card-link">
                        <div class="course-card" tabindex="0">
                          <div class="course-image-container">
                            <img src="<?php echo htmlspecialchars($imgSrc ?? ''); ?>" alt="<?php echo htmlspecialchars($courseTitle); ?>" class="course-image" loading="lazy">
                            <button class="course-action-btn" title="Download" onclick="event.stopPropagation();"><i class="bi bi-download"></i></button>
                          </div>
                          <div class="course-info">
                            <h3 class="course-title"><?php echo htmlspecialchars($courseTitle); ?></h3>
                            <?php $isFree = empty($course['price']) || $course['price'] <= 0; ?>
                            <div class="course-price<?php echo $isFree ? ' free' : ''; ?>"><?php echo $isFree ? 'FREE' : 'TSH ' . number_format((float)$course['price'], 0); ?></div>
                            <div class="course-meta"><?php echo htmlspecialchars($course['category_name']); ?> &middot; <?php echo date('M d, Y', strtotime($course['created_at'])); ?></div>
                          </div>
                        </div>
                      </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-3">
              <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?slug=<?= rawurlencode((string)$category['slug']) ?>&page=<?= max(1, $page - 1) ?>">Previous</a>
              </li>
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                  <a class="page-link" href="?slug=<?= rawurlencode((string)$category['slug']) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?slug=<?= rawurlencode((string)$category['slug']) ?>&page=<?= min($total_pages, $page + 1) ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<style>
.course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.5rem; margin: 1.5rem 0; }
.course-card-wrapper { position: relative; transition: transform 0.2s; }
.course-card-link { text-decoration: none; color: inherit; display: block; height: 100%; }
.course-card { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1); transition: all 0.2s ease; height:100%; display:flex; flex-direction:column; }
.course-card:hover { transform: translateY(-5px); box-shadow:0 5px 15px rgba(0,0,0,0.15); }
.course-image-container { position:relative; width:100%; padding-top:75%; background:#f5f5f5; overflow:hidden; }
.course-image { position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; padding:10px; background:#fff; border-radius:8px; transition: transform 0.3s; }
.course-card:hover .course-image { transform: scale(1.03); }
.course-action-btn { position:absolute; top:10px; right:10px; width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.9); border:none; color:#FF6600; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:2; transition: all 0.2s; }
.course-action-btn:hover { background:#FF6600; color:white; }
.course-info { padding:1rem; flex-grow:1; display:flex; flex-direction:column; }
.course-title { font-size:1rem; font-weight:600; color:#000; margin:0 0 0.5rem 0; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.course-price { font-weight:700; color:#FFAA00; margin:0.25rem 0; font-size:1.1rem; }
.course-price.free { color:#16a34a !important; }
.course-meta { font-size:0.85rem; color:#555; margin-top:auto; }
@media (max-width: 768px) { .course-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; } }
@media (max-width: 480px) { .course-grid { grid-template-columns: repeat(2, 1fr); } .course-title { font-size:0.9rem; } .course-price { font-size:1rem; } }
/* Pagination brand colors (override Bootstrap via CSS variables) */
.pagination {
  --bs-pagination-color: #e7a549;
  --bs-pagination-hover-color: #ffffff;
  --bs-pagination-hover-bg: #e7a549;
  --bs-pagination-hover-border-color: #e7a549;
  --bs-pagination-focus-box-shadow: 0 0 0 .2rem rgba(231,165,73,.25);
  --bs-pagination-active-bg: #e7a549;
  --bs-pagination-active-border-color: #e7a549;
}
.pagination .page-link,
.pagination .page-item .page-link { color:#e7a549 !important; border-color:#e7a54922 !important; }
.pagination .page-link:hover,
.pagination .page-item .page-link:hover { color:#ffffff !important; background-color:#e7a549 !important; border-color:#e7a549 !important; }
.pagination .page-item.active .page-link { background-color:#e7a549 !important; border-color:#e7a549 !important; color:#ffffff !important; }
.pagination .page-item.disabled .page-link { color:#999999 !important; border-color:#dddddd !important; }
.pagination .page-link:focus { box-shadow:0 0 0 .2rem rgba(231,165,73,.25) !important; }
</style>
<?php require_once 'includes/footer.php'; ?>