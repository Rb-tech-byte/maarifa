<?php
session_start();
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: courses.php');
    exit();
}

try {
    // Fetch source course
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $src = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$src) {
        header('Location: courses.php?error=notfound');
        exit();
    }

    // Build new name and ensure unique slug
    $newName = $src['name'] . ' (Copy)';
    $baseSlug = $src['slug'] !== '' ? $src['slug'] . '-copy' : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $src['name'])) . '-copy';
    $slug = trim($baseSlug, '-');
    $i = 1;
    $chk = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE slug = ?');
    while (true) {
        $chk->execute([$slug]);
        if ((int)$chk->fetchColumn() === 0) break;
        $slug = $baseSlug . '-' . (++$i);
    }

    // Compute next ID explicitly to avoid zero-ID when AUTO_INCREMENT is missing
    $maxStmt = $pdo->query('SELECT COALESCE(MAX(id),0) + 1 AS next_id FROM courses');
    $next = (int)($maxStmt->fetch(PDO::FETCH_ASSOC)['next_id'] ?? 1);

    // Insert new course (copying most fields)
    $ins = $pdo->prepare('INSERT INTO courses (id, name, slug, description, price, file_type, file_path, file_url, thumbnail, license, file_size, publisher, password_hint, promo_video, is_active, is_featured, category_id, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())');
    $ok = $ins->execute([
        $next,
        $newName,
        $slug,
        $src['description'],
        $src['price'],
        $src['file_type'],
        $src['file_path'],
        $src['file_url'],
        $src['thumbnail'],
        $src['license'],
        $src['file_size'],
        $src['publisher'],
        $src['password_hint'],
        $src['promo_video'],
        $src['is_featured'],
        $src['category_id']
    ]);

    if (!$ok) {
        header('Location: courses.php?error=insert');
        exit();
    }

    $newId = $next;

    // Ensure junction table exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS course_category_links (
            course_id INT NOT NULL,
            category_id INT NOT NULL,
            PRIMARY KEY (course_id, category_id),
            KEY idx_category (category_id),
            CONSTRAINT fk_pcl_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            CONSTRAINT fk_pcl_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { /* ignore */ }

    // Copy category links
    try {
        $lk = $pdo->prepare('SELECT category_id FROM course_category_links WHERE course_id = ?');
        $lk->execute([$id]);
        $catIds = array_map('intval', array_column($lk->fetchAll(PDO::FETCH_ASSOC), 'category_id'));
        if (!empty($catIds)) {
            $insLink = $pdo->prepare('INSERT IGNORE INTO course_category_links (course_id, category_id) VALUES (?, ?)');
            foreach ($catIds as $cid) {
                if ($cid) { $insLink->execute([$newId, (int)$cid]); }
            }
        } elseif (!empty($src['category_id'])) {
            $insLink = $pdo->prepare('INSERT IGNORE INTO course_category_links (course_id, category_id) VALUES (?, ?)');
            $insLink->execute([$newId, (int)$src['category_id']]);
        }
    } catch (Exception $e) { /* ignore */ }

    // Redirect to edit page for quick adjustments
    header('Location: edit_course.php?id=' . $newId . '&duplicated=1');
    exit();

} catch (Exception $e) {
    header('Location: courses.php?error=exception');
    exit();
}
