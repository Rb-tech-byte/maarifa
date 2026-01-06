<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

// Use cached category tree for header/navigation
$categoryTree = getCategoryTreeCached();
$categories = array_merge($categoryTree[0] ?? []); // flat top parents for helper logic below

// Pagination for main course list
$courses_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$courses_limit = 8; // two rows per page on typical 4-column grids
$courses_offset = ($courses_page - 1) * $courses_limit;

// Total count for pagination (match actual courses schema)
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE visibility = 'published'");
$countStmt->execute();
$courses_total = (int)$countStmt->fetchColumn();
$courses_total_pages = (int)ceil($courses_total / $courses_limit);

// Fetch latest courses with pagination using real columns
// Map: title -> name, short_description -> description, cover_image -> thumbnail
$stmt = $pdo->prepare("
    SELECT p.id, p.category_id, p.title AS name, p.slug, p.short_description AS description, p.price, p.created_at,
           c.name AS category_name, c.slug AS category_slug, p.cover_image AS thumbnail
    FROM courses p
    LEFT JOIN course_categories c ON p.category_id = c.id
    WHERE p.visibility = 'published'
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $courses_limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $courses_offset, PDO::PARAM_INT);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent 5 posts
$stmt = $pdo->prepare("SELECT id, title AS name, slug, created_at FROM courses WHERE visibility = 'published' ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recentPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch 5 trending courses (no extra image loops)
$stmt = $pdo->prepare("
    SELECT p.id, p.category_id, p.title AS name, p.slug, p.short_description AS description, p.price, p.created_at,
           c.name AS category_name, c.slug AS category_slug, p.cover_image AS thumbnail
    FROM courses p
    LEFT JOIN course_categories c ON p.category_id = c.id
    WHERE p.visibility = 'published'
    ORDER BY RAND() LIMIT 5
");
$stmt->execute();
$trendingcourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Detect "Audio Library" category
$audioLibCatId = null;
foreach ($categories as $cat) {
    if (stripos($cat['name'], 'audio library') !== false) {
        $audioLibCatId = $cat['id'];
        break;
    }
}

// Fetch 5 Audio Library courses (thumbnail used directly)
$audioLibcourses = [];
if ($audioLibCatId) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.category_id, p.title AS name, p.slug, p.short_description AS description, p.price, p.created_at,
               c.name AS category_name, c.slug AS category_slug, p.cover_image AS thumbnail
        FROM courses p
        JOIN course_categories c ON p.category_id = c.id
        WHERE p.visibility = 'published' AND p.category_id = ?
        ORDER BY p.created_at DESC LIMIT 5
    ");
    $stmt->execute([$audioLibCatId]);
    $audioLibcourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Set page title and render with shared header/footer
$title = "Welcome to AK23DOWNLOADS";
require_once 'includes/header.php';
require_once 'layouts/home_content.php';
require_once 'includes/footer.php';
?>
<?php
