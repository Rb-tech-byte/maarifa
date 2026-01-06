<?php
// Returns subcategories and course counts for a given parent category
// GET params: parent_id (int)
try {
    require_once __DIR__ . '/../includes/db_config.php';
    $parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
    if ($parent_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'Invalid parent_id']);
        exit;
    }

    // Fetch children
    $stmt = $pdo->prepare("SELECT id, name, slug FROM course_categories WHERE parent_id = ? AND is_active = 1 AND (deleted_at IS NULL OR deleted_at = '') ORDER BY name ASC");
    $stmt->execute([$parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Counts
    $counts = [];
    if (!empty($children)) {
        $ids = array_map(function($r){ return (int)$r['id']; }, $children);
        $in = implode(',', array_unique($ids));
        $q = $pdo->query("SELECT category_id, COUNT(*) as cnt FROM courses WHERE is_active = 1 AND category_id IN ($in) GROUP BY category_id");
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(int)$row['category_id']] = (int)$row['cnt'];
        }
    }

    // Combine
    $out = [];
    foreach ($children as $ch) {
        $id = (int)$ch['id'];
        $out[] = [
            'id' => $id,
            'name' => $ch['name'],
            'slug' => $ch['slug'],
            'count' => isset($counts[$id]) ? (int)$counts[$id] : 0,
        ];
    }
    // Build payload and ETag
    $payload = ['ok'=>true,'children'=>$out];
    $json = json_encode($payload);
    $etag = 'W/"' . sha1($json) . '"';

    // ETag conditional
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=300, must-revalidate');
        http_response_code(304);
        exit;
    }

    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('ETag: ' . $etag);
    echo $json;
} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'error'=>'Server error']);
}
