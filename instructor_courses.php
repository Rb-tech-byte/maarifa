<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

checkInstructorAuth();

$instructor_user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id FROM instructors WHERE user_id = ?");
$stmt->execute([$instructor_user_id]);
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);
$instructor_id = $instructor['id'] ?? 0;

$courses = [];
if ($instructor_id) {
    $stmt = $pdo->prepare("SELECT c.*, COUNT(DISTINCT e.user_id) AS students_count
                           FROM instructor_course_assignments a
                           JOIN courses c ON a.course_id = c.id
                           LEFT JOIN enrollments e ON e.course_id = c.id
                           WHERE a.instructor_id = ?
                           GROUP BY c.id
                           ORDER BY c.created_at DESC");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$title = 'My Courses - Instructor';
include 'user_header.php';
include 'instructor_sidebar.php';
?>
<style>
/* Main content adjustment for fixed sidebar */
@media (min-width: 768px) {
    main {
        margin-left: 200px;
        width: calc(100% - 200px);
    }
}
</style>
<main class="px-md-4 py-4">
  <h1 class="h3 mb-4" style="color:black;">My Courses</h1>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>Title</th>
          <th>Visibility</th>
          <th>Students</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($courses)): ?>
          <tr><td colspan="4" class="text-center text-muted">No courses assigned yet.</td></tr>
        <?php else: foreach ($courses as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['title']) ?></td>
            <td><?= htmlspecialchars($c['visibility'] ?? '') ?></td>
            <td><?= (int)($c['students_count'] ?? 0) ?></td>
            <td><?= htmlspecialchars($c['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include 'user_footer.php'; ?>
