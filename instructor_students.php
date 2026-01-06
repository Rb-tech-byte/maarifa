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

$students = [];
if ($instructor_id) {
    $sql = "SELECT DISTINCT u.id AS user_id, u.name, u.email,
                       c.title AS course_title,
                       e.status AS enrollment_status,
                       e.created_at
            FROM instructor_course_assignments a
            JOIN courses c ON a.course_id = c.id
            JOIN enrollments e ON e.course_id = c.id
            JOIN users u ON e.user_id = u.id
            WHERE a.instructor_id = ?
            ORDER BY e.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$instructor_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$title = 'My Students - Instructor';
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
  <h1 class="h3 mb-4" style="color:black;">My Students</h1>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>Student</th>
          <th>Email</th>
          <th>Course</th>
          <th>Status</th>
          <th>Enrolled At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr><td colspan="5" class="text-center text-muted">No students found for your courses.</td></tr>
        <?php else: foreach ($students as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['course_title'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['enrollment_status'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include 'user_footer.php'; ?>
