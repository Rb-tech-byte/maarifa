<?php
// One-time utility to backfill missing category slugs
// Usage: visit /admin/backfill_category_slugs.php in your browser (local only)

require_once __DIR__ . '/../includes/db_config.php';

define('NOWTZ', date('Y-m-d H:i:s'));

function slugify($text) {
    // Convert to UTF-8, transliterate accented chars
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    // Lowercase
    $text = strtolower($text);
    // Replace non alphanum with hyphens
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    // Trim hyphens
    $text = trim($text, '-');
    // Collapse multiple hyphens
    $text = preg_replace('~-+~', '-', $text);
    return $text;
}

function html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$results = [ 'updated' => [], 'skipped' => [], 'errors' => [] ];

try {
    // Fetch all current non-null slugs to ensure uniqueness
    $stmt = $pdo->query("SELECT id, slug FROM categories WHERE slug IS NOT NULL AND slug != ''");
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $used = [];
    foreach ($existing as $row) {
        $used[$row['slug']] = true;
    }

    // Get categories missing slugs
    $stmt = $pdo->query("SELECT id, name, slug FROM categories WHERE slug IS NULL OR slug = ''");
    $missing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $pdo->prepare("UPDATE categories SET slug = ?, updated_at = NOW() WHERE id = ?");

    foreach ($missing as $row) {
        $base = slugify($row['name'] ?? '') ?: ('category-' . $row['id']);
        $candidate = $base;
        $suffix = 1;
        while (isset($used[$candidate])) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }
        if ($updateStmt->execute([$candidate, $row['id']])) {
            $used[$candidate] = true;
            $results['updated'][] = [ 'id' => $row['id'], 'name' => $row['name'], 'slug' => $candidate ];
        } else {
            $results['errors'][] = [ 'id' => $row['id'], 'name' => $row['name'], 'error' => 'Update failed' ];
        }
    }
} catch (Throwable $e) {
    $results['errors'][] = $e->getMessage();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Backfill Category Slugs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
  <div class="container">
    <h1 class="h3 mb-3">Backfill Category Slugs</h1>
    <p class="text-muted">Run at <?= html(NOWTZ) ?>. This tool generates URL-friendly, unique slugs for categories that have NULL/empty slugs.</p>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-header bg-success text-white">Updated</div>
          <div class="card-body">
            <?php if (empty($results['updated'])): ?>
              <p class="text-muted mb-0">No categories required updates.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead><tr><th>ID</th><th>Name</th><th>New Slug</th></tr></thead>
                  <tbody>
                  <?php foreach ($results['updated'] as $r): ?>
                    <tr>
                      <td><?= html($r['id']) ?></td>
                      <td><?= html($r['name']) ?></td>
                      <td><code><?= html($r['slug']) ?></code></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-header bg-danger text-white">Errors</div>
          <div class="card-body">
            <?php if (empty($results['errors'])): ?>
              <p class="text-muted mb-0">No errors encountered.</p>
            <?php else: ?>
              <ul class="mb-0">
              <?php foreach ($results['errors'] as $err): ?>
                <li><pre class="mb-0"><?= html(is_string($err) ? $err : json_encode($err)) ?></pre></li>
              <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="alert alert-info mt-3">
      After running this tool, refresh the site. Category links should now work without empty slugs.
    </div>
  </div>
</body>
</html>
