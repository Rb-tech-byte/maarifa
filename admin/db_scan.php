<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

$title = 'Database Scanner - AK23 Admin';
include '../includes/admin_header.php';

$notice = '';
$errorMsg = '';

// Utilities
$make_slug = function(string $name) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    return trim($slug, '-') ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', uniqid('item-')));
};

// Handle fixes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    try {
        if ($action === 'fix_course_slugs_missing') {
            $rows = qall($pdo, "SELECT id, name FROM courses WHERE slug IS NULL OR slug = ''");
            foreach ($rows as $r) {
                $base = $make_slug($r['name'] ?: ('course-'.$r['id']));
                $slug = $base; $i=1;
                while (qval($pdo, 'SELECT COUNT(*) FROM courses WHERE slug = ? AND id <> ?', [$slug, $r['id']])) { $slug = $base.'-'.(++$i); }
                $stmt = $pdo->prepare('UPDATE courses SET slug = ? WHERE id = ?');
                $stmt->execute([$slug, $r['id']]);
            }
            $notice = 'Missing course slugs generated.';
        } elseif ($action === 'fix_course_slugs_dupe') {
            $dupes = qall($pdo, "SELECT slug FROM courses WHERE slug IS NOT NULL AND slug<>'' GROUP BY slug HAVING COUNT(*)>1");
            foreach ($dupes as $d) {
                $slug = $d['slug'];
                $rows = qall($pdo, 'SELECT id FROM courses WHERE slug = ? ORDER BY id ASC', [$slug]);
                $n = 0;
                foreach ($rows as $r) {
                    if ($n === 0) { $n++; continue; }
                    $new = $slug.'-'.($n+1);
                    while (qval($pdo, 'SELECT COUNT(*) FROM courses WHERE slug = ? AND id <> ?', [$new, $r['id']])) { $new = $slug.'-'.(++$n+1); }
                    $stmt = $pdo->prepare('UPDATE courses SET slug = ? WHERE id = ?');
                    $stmt->execute([$new, $r['id']]);
                    $n++;
                }
            }
            $notice = 'Duplicate course slugs normalized.';
        } elseif ($action === 'fix_course_category_invalid') {
            $stmt = $pdo->prepare('UPDATE courses p LEFT JOIN course_categories c ON c.id = p.category_id SET p.category_id = NULL WHERE p.category_id IS NOT NULL AND c.id IS NULL');
            $stmt->execute();
            $notice = 'courses with invalid category have been detached (category set to NULL).';
        } elseif ($action === 'fix_category_slugs_missing') {
            $rows = qall($pdo, "SELECT id, name FROM course_categories WHERE slug IS NULL OR slug = ''");
            foreach ($rows as $r) {
                $base = $make_slug($r['name'] ?: ('category-'.$r['id']));
                $slug = $base; $i=1;
                while (qval($pdo, 'SELECT COUNT(*) FROM course_categories WHERE slug = ? AND id <> ?', [$slug, $r['id']])) { $slug = $base.'-'.(++$i); }
                $stmt = $pdo->prepare('UPDATE course_categories SET slug = ? WHERE id = ?');
                $stmt->execute([$slug, $r['id']]);
            }
            $notice = 'Missing category slugs generated.';
        } elseif ($action === 'fix_subcategory_parent_invalid') {
            $stmt = $pdo->prepare('UPDATE course_categories c LEFT JOIN course_categories p ON p.id = c.parent_id SET c.parent_id = NULL WHERE c.parent_id IS NOT NULL AND p.id IS NULL');
            $stmt->execute();
            $notice = 'Subcategories with invalid parent have been moved to top-level.';
        } elseif ($action === 'fix_orphan_medias') {
            $exists = qval($pdo, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medias'");
            if ($exists > 0) {
                // Delete orphan medias
                $ids = qall($pdo, "SELECT m.id FROM medias m LEFT JOIN courses p ON p.id = m.courses_id WHERE p.id IS NULL");
                if ($ids) {
                    $in = implode(',', array_map('intval', array_column($ids, 'id')));
                    $pdo->exec("DELETE FROM medias WHERE id IN ($in)");
                }
            }
            $notice = 'Orphan media rows removed.';
        }
    } catch (Exception $e) {
        $errorMsg = 'Fix error: ' . htmlspecialchars($e->getMessage());
    }
}

$issues = [];
$summary = [];

// Helper to safely fetch all
function qall($pdo, $sql, $params = []){
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function qval($pdo, $sql, $params = []){
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

try {
    // Summary counts - with error handling for missing tables
    $summary['courses'] = 0;
    $summary['categories'] = 0;
    $summary['orders'] = 0;
    $summary['payments'] = 0;
    
    try {
        $summary['courses'] = (int) qval($pdo, 'SELECT COUNT(*) FROM courses');
    } catch (Exception $e) {}
    
    try {
        $summary['categories'] = (int) qval($pdo, 'SELECT COUNT(*) FROM course_categories');
    } catch (Exception $e) {}
    
    try {
        $summary['orders'] = (int) qval($pdo, 'SELECT COUNT(*) FROM orders');
    } catch (Exception $e) {}
    
    try {
        $summary['payments'] = (int) qval($pdo, 'SELECT COUNT(*) FROM payments');
    } catch (Exception $e) {}

    // 1) courses with NULL/empty slug
    $rows = qall($pdo, "SELECT id, name FROM courses WHERE slug IS NULL OR slug = '' LIMIT 100");
    if ($rows) { $issues[] = ['title' => 'courses missing slug', 'rows' => $rows]; }

    // 2) Duplicate course slugs
    $rows = qall($pdo, "SELECT slug, COUNT(*) as cnt FROM courses WHERE slug IS NOT NULL AND slug <> '' GROUP BY slug HAVING cnt > 1 ORDER BY cnt DESC LIMIT 100");
    if ($rows) { $issues[] = ['title' => 'Duplicate course slugs', 'rows' => $rows]; }

    // 3) courses with invalid category_id
    try {
        $rows = qall($pdo, "SELECT p.id, p.name, p.category_id FROM courses p LEFT JOIN course_categories c ON c.id = p.category_id WHERE p.category_id IS NOT NULL AND c.id IS NULL LIMIT 100");
        if ($rows) { $issues[] = ['title' => 'courses referencing missing category', 'rows' => $rows]; }
    } catch (Exception $e) {}

    // 4) Categories with missing slug
    try {
        $rows = qall($pdo, "SELECT id, name FROM course_categories WHERE slug IS NULL OR slug = '' LIMIT 100");
        if ($rows) { $issues[] = ['title' => 'Categories missing slug', 'rows' => $rows]; }
    } catch (Exception $e) {}

    // 5) Subcategories with invalid parent
    try {
        $rows = qall($pdo, "SELECT c.id, c.name, c.parent_id FROM course_categories c LEFT JOIN course_categories p ON p.id = c.parent_id WHERE c.parent_id IS NOT NULL AND p.id IS NULL LIMIT 100");
        if ($rows) { $issues[] = ['title' => 'Subcategories referencing missing parent', 'rows' => $rows]; }
    } catch (Exception $e) {}

    // 6) Orphan media records
    $exists = qval($pdo, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medias'");
    if ($exists > 0) {
        $rows = qall($pdo, "SELECT m.id, m.courses_id FROM medias m LEFT JOIN courses p ON p.id = m.courses_id WHERE p.id IS NULL LIMIT 100");
        if ($rows) { $issues[] = ['title' => 'Media rows referencing missing course', 'rows' => $rows]; }
    }

} catch (Exception $e) {
    $issues[] = ['title' => 'Scanner error', 'rows' => [['error' => $e->getMessage()]]];
}
?>
<main class="container py-4">
  <h1 class="h4 mb-3" style="color:#000;">Database Scanner</h1>

  <?php if ($notice): ?>
    <div class="alert alert-success"><?= $notice ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="alert alert-danger"><?= $errorMsg ?></div>
  <?php endif; ?>

  <form method="POST" class="d-flex flex-wrap gap-2 mb-3">
    <button class="btn btn-sm btn-primary" name="action" value="fix_course_slugs_missing" onclick="return confirm('Generate slugs for courses missing slug?');">Fix Missing course Slugs</button>
    <button class="btn btn-sm btn-primary" name="action" value="fix_course_slugs_dupe" onclick="return confirm('Normalize duplicate course slugs?');">Fix Duplicate course Slugs</button>
    <button class="btn btn-sm btn-secondary" name="action" value="fix_course_category_invalid" onclick="return confirm('Set category to NULL for courses referencing missing categories?');">Detach Invalid course Categories</button>
    <button class="btn btn-sm btn-primary" name="action" value="fix_category_slugs_missing" onclick="return confirm('Generate slugs for categories missing slug?');">Fix Missing Category Slugs</button>
    <button class="btn btn-sm btn-secondary" name="action" value="fix_subcategory_parent_invalid" onclick="return confirm('Move subcategories with invalid parent to top-level?');">Fix Invalid Subcategory Parents</button>
    <button class="btn btn-sm btn-danger" name="action" value="fix_orphan_medias" onclick="return confirm('Delete orphan media rows? This cannot be undone.');">Delete Orphan Medias</button>
  </form>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm"><div class="card-body"><div class="text-muted">courses</div><div class="h5 mb-0"><?= (int)$summary['courses'] ?></div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm"><div class="card-body"><div class="text-muted">Categories</div><div class="h5 mb-0"><?= (int)$summary['categories'] ?></div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm"><div class="card-body"><div class="text-muted">Orders</div><div class="h5 mb-0"><?= (int)$summary['orders'] ?></div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm"><div class="card-body"><div class="text-muted">Payments</div><div class="h5 mb-0"><?= (int)$summary['payments'] ?></div></div></div>
    </div>
  </div>

  <?php if (empty($issues)): ?>
    <div class="alert alert-success">No issues found. Your database looks good.</div>
  <?php else: ?>
    <?php foreach ($issues as $block): ?>
      <div class="card mb-3 shadow-sm">
        <div class="card-header bg-dark text-white">
          <?= htmlspecialchars($block['title']) ?>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-sm mb-0">
              <tbody>
              <?php foreach ($block['rows'] as $r): ?>
                <tr>
                  <td><code><?= htmlspecialchars(json_encode($r)) ?></code></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<?php include '../includes/admin_footer.php'; ?>
