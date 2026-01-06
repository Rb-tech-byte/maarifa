<?php
// Admin tool: Backfill legacy courses into ps_courses so FK constraints are satisfied
// Maps courses -> ps_courses (active only), preserving IDs where possible

require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAdminAuth();

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errors = [];
$inserted = 0;
$skipped = 0;
$updatedDl = 0;
$activated = 0;

try {
    // Ensure ps_courses exists
    $pdo->query('SELECT 1 FROM ps_courses LIMIT 1');
} catch (Throwable $e) {
    $errors[] = 'ps_courses table not found. Please import the ps_* schema first.';
}

if (empty($errors)) {
    try {
        // Insert active legacy courses that do not already exist in ps_courses
        // Preserve ID to keep consistent references
        $sql = "
            INSERT INTO ps_courses (id, name, description, price, currency, download_url, status, created_at)
            SELECT p.id, p.name, COALESCE(p.description, ''),
                   COALESCE(NULLIF(p.price, ''), 0), 'TZS',
                   COALESCE(p.file_url, ''),
                   'active', NOW()
            FROM courses p
            LEFT JOIN ps_courses s ON s.id = p.id
            WHERE s.id IS NULL AND p.is_active = 1;
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $inserted = $stmt->rowCount();

        // Count how many are already present (skipped)
        $countSql = "
            SELECT COUNT(*) AS cnt
            FROM courses p
            INNER JOIN ps_courses s ON s.id = p.id
            WHERE p.is_active = 1;
        ";
        $skipped = (int)$pdo->query($countSql)->fetch(PDO::FETCH_ASSOC)['cnt'];
        // Update existing ps_courses with missing download_url from legacy courses
        $updSql = "
            UPDATE ps_courses s
            JOIN courses p ON p.id = s.id
            SET s.download_url = COALESCE(NULLIF(p.file_url, ''), s.download_url)
            WHERE (s.download_url IS NULL OR s.download_url = '')
        ";
        $upd = $pdo->prepare($updSql);
        $upd->execute();
        $updatedDl = $upd->rowCount();

        // Activate ps_courses where legacy course is active
        $actSql = "
            UPDATE ps_courses s
            JOIN courses p ON p.id = s.id
            SET s.status = 'active'
            WHERE p.is_active = 1 AND s.status <> 'active'
        ";
        $act = $pdo->prepare($actSql);
        $act->execute();
        $activated = $act->rowCount();

    } catch (Throwable $e) {
        $errors[] = 'Backfill failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Backfill ps_courses</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="h4 mb-3">Backfill ps_courses</h1>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <div><strong>Errors:</strong></div>
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php else: ?>
    <div class="alert alert-success">
      <div><strong>Backfill Complete</strong></div>
      <div class="small">Inserted new rows: <strong><?= (int)$inserted ?></strong></div>
      <div class="small">Active legacy already present (skipped): <strong><?= (int)$skipped ?></strong></div>
      <div class="small">Updated missing download URLs: <strong><?= (int)$updatedDl ?></strong></div>
      <div class="small">Activated courses: <strong><?= (int)$activated ?></strong></div>
    </div>
    <p class="text-muted small">This operation preserves course IDs by inserting into ps_courses.id directly.</p>
  <?php endif; ?>
  <a class="btn btn-primary" href="index.php">Return to Admin</a>
</div>
</body>
</html>
