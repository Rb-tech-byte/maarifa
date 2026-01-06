<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');

// Initialize response array
$response = [];

// Get search query from GET parameter
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    // Establish PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Only process if query is at least 2 characters
    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }

    // Prepare SQL query with LIKE for name and description
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.slug,
            p.description,
            p.thumbnail,
            c.name AS category_name,
            (
              SELECT m.value FROM medias m
              WHERE m.courses_id = p.id AND m.file_type = 'image' AND m.deleted_at IS NULL
              ORDER BY m.id ASC LIMIT 1
            ) AS first_image
        FROM courses p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND p.deleted_at IS NULL
          AND (p.name LIKE :query OR p.description LIKE :query)
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $searchTerm = "%$query%";
    $stmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();

    // Fetch results
    $results = $stmt->fetchAll();

    // Process results
    foreach ($results as $row) {
        $thumb = $row['thumbnail'] ?: ($row['first_image'] ?: '');
        $response[] = [
            'name' => $row['name'],
            'slug' => $row['slug'],
            'category_name' => $row['category_name'] ?: 'No category',
            'thumbnail' => $thumb,
            'description' => strlen($row['description']) > 100
                ? substr(strip_tags($row['description']), 0, 97) . '...'
                : strip_tags($row['description'])
        ];
    }

    // Output JSON
    echo json_encode($response);

} catch (PDOException $e) {
    // Log error (in courseion, log to a file instead of echoing)
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} catch (Exception $e) {
    // Handle other errors
    error_log("General error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// Close connection
$pdo = null;
?>