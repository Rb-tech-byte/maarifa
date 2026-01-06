<?php
require_once 'includes/db_config.php';
$title = 'Trending courses - AK23 App';
// Fetch trending courses (top 10 by sales or random if no sales data)
$stmt = $pdo->prepare("\n    SELECT p.id, p.name, p.slug, p.price, p.thumbnail, COUNT(pay.id) AS sales\n    FROM courses p\n    LEFT JOIN orders o ON o.course_id = p.id\n    LEFT JOIN payments pay ON pay.order_id = o.id AND pay.status = 'completed'\n    WHERE p.is_active = 1 AND p.deleted_at IS NULL\n    GROUP BY p.id\n    ORDER BY sales DESC, RAND()\n    LIMIT 10\n");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
// For layout compatibility, define these as empty arrays if not set
$trendingcourses = [];
$recentPosts = [];
$categoryTree = $categoryTree ?? [];
$audioLibcourses = $audioLibcourses ?? [];
$audioLibCatId = null;
require_once 'layouts/head.php';
require_once 'layouts/body.php';
?>
<div class="container py-4">
  <h1 class="section-title mb-4">Trending courses</h1>
  <div class="course-grid">
    <?php foreach ($courses as $course): ?>
      <div class="course-card">
        <div class="course-img">
          <img src="<?= htmlspecialchars($course['thumbnail'] ?: 'assets/images/placeholder.png') ?>" alt="<?= htmlspecialchars($course['name']) ?>" />
        </div>
        <div class="course-body">
          <div class="course-title"><?= htmlspecialchars($course['name']) ?></div>
          <div class="course-price">
            <?= ((float)$course['price'] <= 0) ? 'FREE' : ('TSH ' . number_format((float)$course['price'], 0)) ?>
          </div>
          <a href="course-details.php?slug=<?= urlencode($course['slug']) ?>" class="btn btn-primary w-100 mt-2">View Details</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php require_once 'layouts/body.php'; ?> 