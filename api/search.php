<?php
// JSON search API: supports suggestions and full results
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_config.php';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$suggestionsOnly = isset($_GET['suggestions']) && filter_var($_GET['suggestions'], FILTER_VALIDATE_BOOLEAN);

$response = [ 'query' => $q, 'suggestions' => [], 'results' => [] ];

if ($q === '') {
    echo json_encode($response);
    exit;
}

try {
    // Prefer name matches, then description
    $like = "%$q%";
    if ($suggestionsOnly) {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.name, p.slug, p.price, p.thumbnail,
                    c.name AS category_name,
                    (
                      SELECT m.value FROM medias m
                      WHERE m.courses_id = p.id AND m.file_type = 'image' AND m.deleted_at IS NULL
                      ORDER BY m.id ASC LIMIT 1
                    ) AS first_image
             FROM courses p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.is_active = 1 AND p.deleted_at IS NULL
               AND (p.name LIKE ? OR p.description LIKE ?)
             ORDER BY (p.name LIKE ?) DESC, p.created_at DESC
             LIMIT 8"
        );
        $stmt->execute([$like, $like, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Normalize thumbnail
        foreach ($rows as &$r) {
            if (empty($r['thumbnail'])) {
                $r['thumbnail'] = $r['first_image'] ?: '';
            }
        }
        unset($r);
        $response['suggestions'] = $rows;
    } else {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.name, p.slug, p.price, p.description, p.created_at, p.thumbnail,
                    c.name AS category_name
             FROM courses p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.is_active = 1 AND p.deleted_at IS NULL
               AND (p.name LIKE ? OR p.description LIKE ?)
             ORDER BY p.created_at DESC
             LIMIT 50"
        );
        $stmt->execute([$like, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['results'] = $rows;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'error' => 'Search failed', 'message' => $e->getMessage() ]);
    exit;
}

echo json_encode($response);
