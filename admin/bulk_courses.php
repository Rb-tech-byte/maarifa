<?php
session_start();
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: courses.php');
    exit();
}

$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_values(array_unique(array_map('intval', $_POST['ids']))) : [];

if (empty($ids)) {
    header('Location: courses.php?bulk=invalid');
    exit();
}

// Helper: duplicate one course
function duplicate_course(PDO $pdo, array $src): ?int {
    $newName = $src['name'] . ' (Copy)';
    $baseSlug = $src['slug'] !== '' ? $src['slug'] . '-copy' : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $src['name'])) . '-copy';
    $slug = trim($baseSlug, '-');
    $i = 1;
    while (true) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE slug = ?');
        $chk->execute([$slug]);
        if ((int)$chk->fetchColumn() === 0) break;
        $slug = $baseSlug . '-' . (++$i);
    }
    // Compute next id explicitly (schema lacks AUTO_INCREMENT on courses.id)
    $maxStmt = $pdo->query('SELECT COALESCE(MAX(id),0) + 1 AS next_id FROM courses');
    $next = (int)($maxStmt->fetch(PDO::FETCH_ASSOC)['next_id'] ?? 1);
    $ins = $pdo->prepare('INSERT INTO courses (id, name, slug, description, price, file_type, file_path, file_url, thumbnail, license, file_size, publisher, password_hint, promo_video, is_active, is_featured, category_id, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
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
        $src['is_active'],
        $src['is_featured'],
        $src['category_id']
    ]);
    if (!$ok) return null;
    return $next;
}

$resultCount = 0;
switch ($action) {
    case 'duplicate':
        foreach ($ids as $id) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND deleted_at IS NULL');
                $stmt->execute([$id]);
                $src = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$src) { continue; }
                $newId = duplicate_course($pdo, $src);
                if ($newId) {
                    // Ensure junction table exists and copy links
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

                    try {
                        $lk = $pdo->prepare('SELECT category_id FROM course_category_links WHERE course_id = ?');
                        $lk->execute([$id]);
                        $catIds = array_map('intval', array_column($lk->fetchAll(PDO::FETCH_ASSOC), 'category_id'));
                        if (!empty($catIds)) {
                            $insLink = $pdo->prepare('INSERT IGNORE INTO course_category_links (course_id, category_id) VALUES (?, ?)');
                            foreach ($catIds as $cid) { if ($cid) { $insLink->execute([$newId, (int)$cid]); } }
                        } elseif (!empty($src['category_id'])) {
                            $insLink = $pdo->prepare('INSERT IGNORE INTO course_category_links (course_id, category_id) VALUES (?, ?)');
                            $insLink->execute([$newId, (int)$src['category_id']]);
                        }
                    } catch (Exception $e) { /* ignore */ }
                    $resultCount++;
                }
            } catch (Exception $e) { continue; }
        }
        header('Location: courses.php?bulk=duplicated&count=' . (int)$resultCount);
        exit();

    case 'activate':
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE courses SET is_active = 1, updated_at = NOW() WHERE id IN ($in)");
        $stmt->execute($ids);
        header('Location: courses.php?bulk=activated&count=' . count($ids));
        exit();

    case 'deactivate':
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE courses SET is_active = 0, updated_at = NOW() WHERE id IN ($in)");
        $stmt->execute($ids);
        header('Location: courses.php?bulk=deactivated&count=' . count($ids));
        exit();

    case 'feature':
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE courses SET is_featured = 1, updated_at = NOW() WHERE id IN ($in)");
        $stmt->execute($ids);
        header('Location: courses.php?bulk=featured&count=' . count($ids));
        exit();

    case 'unfeature':
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE courses SET is_featured = 0, updated_at = NOW() WHERE id IN ($in)");
        $stmt->execute($ids);
        header('Location: courses.php?bulk=unfeatured&count=' . count($ids));
        exit();

    case 'delete':
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE courses SET deleted_at = NOW(), updated_at = NOW() WHERE id IN ($in)");
        $stmt->execute($ids);
        header('Location: courses.php?bulk=deleted&count=' . count($ids));
        exit();
}

header('Location: courses.php?bulk=invalid');
exit();
