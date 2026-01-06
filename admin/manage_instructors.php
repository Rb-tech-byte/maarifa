<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

// --- Helpers ---
function getAllInstructorsWithUser(PDO $pdo): array {
    $sql = "SELECT i.*, u.name AS user_name, u.email
            FROM instructors i
            JOIN users u ON i.user_id = u.id
            ORDER BY i.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getAllCoursesSimple(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, title FROM courses ORDER BY title");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getInstructorAssignedCourseIds(PDO $pdo, int $instructor_id): array {
    $stmt = $pdo->prepare("SELECT course_id FROM instructor_course_assignments WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'course_id'));
}

// --- Handle POST actions ---
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_instructor') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $user_email = trim((string)($_POST['user_email'] ?? ''));
        $user_password = trim((string)($_POST['user_password'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        $approved = isset($_POST['approved']) ? 1 : 0;

        if ($user_email === '' || $user_password === '') {
            $errors[] = 'User email and password are required.';
        }
        if ($name === '') {
            $errors[] = 'Instructor name is required.';
        }

        if (empty($errors)) {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE instructors SET name = ?, bio = ?, approved = ? WHERE id = ?");
                $stmt->execute([$name, $bio, $approved, $id]);
                $success = 'Instructor updated successfully.';
            } else {
                // Create new user
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role, created_at, updated_at) VALUES (?, ?, 'instructor', NOW(), NOW())");
                $stmt->execute([$user_email, password_hash($user_password, PASSWORD_DEFAULT)]);
                $user_id = (int)$pdo->lastInsertId();

                // Create new instructor
                $stmt = $pdo->prepare("INSERT INTO instructors (user_id, name, bio, approved, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$user_id, $name, $bio, $approved]);
                $instructor_id = (int)$pdo->lastInsertId();

                // Create wallet with unique card number for this instructor
                if ($instructor_id > 0) {
                    try {
                        $walletNumber = null;
                        do {
                            $walletNumber = 'AKW-'
                              . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT)
                              . '-'
                              . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

                            $chk = $pdo->prepare("SELECT COUNT(*) FROM instructor_wallet WHERE wallet_number = ?");
                            $chk->execute([$walletNumber]);
                        } while ((int)$chk->fetchColumn() > 0);

                        $ins = $pdo->prepare("INSERT INTO instructor_wallet (instructor_id, balance, total_earned, last_withdraw, wallet_number) VALUES (?, 0, 0, NULL, ?)");
                        $ins->execute([$instructor_id, $walletNumber]);
                    } catch (Throwable $e) {
                        // If wallet creation fails, do not block instructor creation
                    }
                }

                $success = 'Instructor created successfully.';
            }
        }
    } elseif ($action === 'assign_courses') {
        $instructor_id = (int)($_POST['instructor_id'] ?? 0);
        $course_ids = isset($_POST['course_ids']) && is_array($_POST['course_ids'])
            ? array_map('intval', $_POST['course_ids'])
            : [];

        if ($instructor_id <= 0) {
            $errors[] = 'Invalid instructor.';
        } else {
            // Reset assignments for this instructor
            $del = $pdo->prepare("DELETE FROM instructor_course_assignments WHERE instructor_id = ?");
            $del->execute([$instructor_id]);

            if (!empty($course_ids)) {
                $ins = $pdo->prepare("INSERT INTO instructor_course_assignments (instructor_id, course_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
                $admin_id = (int)($_SESSION['admin_id'] ?? 0);
                foreach ($course_ids as $cid) {
                    $ins->execute([$instructor_id, $cid, $admin_id ?: null]);
                }
            }
            $success = 'Course assignments updated.';
        }
    }
}

// --- Data for view ---
$instructors = getAllInstructorsWithUser($pdo);
$courses = getAllCoursesSimple($pdo);

// Determine instructor being edited/assigned
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_instructor = null;
$edit_assigned_ids = [];
if ($edit_id > 0) {
    foreach ($instructors as $inst) {
        if ((int)$inst['id'] === $edit_id) {
            $edit_instructor = $inst;
            break;
        }
    }
    if ($edit_instructor) {
        $edit_assigned_ids = getInstructorAssignedCourseIds($pdo, $edit_id);
    }
}

// Users that can be instructors: for now, all users; you can filter by role = 'instructor' if present.
$users_stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY name");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$title = 'Manage Instructors - AK23 App';
include '../includes/admin_header.php';
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<main class="admin-main container py-4">
  <h1 class="h3 mb-3" style="color:#000;">Manage Instructors</h1>
  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-md-5">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-dark text-white">
          <?= $edit_instructor ? 'Edit Instructor' : 'Add Instructor' ?>
        </div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="action" value="save_instructor">
            <input type="hidden" name="id" value="<?= $edit_instructor ? (int)$edit_instructor['id'] : 0 ?>">
            <div class="mb-3">
              <label class="form-label">User Email</label>
              <input type="email" name="user_email" class="form-control" required
                     value="<?= $edit_instructor ? htmlspecialchars($edit_instructor['email'] ?? '') : '' ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">User Password</label>
              <input type="password" name="user_password" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Display Name</label>
              <input type="text" name="name" class="form-control" required
                     value="<?= $edit_instructor ? htmlspecialchars($edit_instructor['name']) : '' ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Bio</label>
              <textarea name="bio" class="form-control" rows="3"><?= $edit_instructor ? htmlspecialchars($edit_instructor['bio'] ?? '') : '' ?></textarea>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="approved" id="approvedCheck"
                     <?= $edit_instructor && (int)$edit_instructor['approved'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="approvedCheck">Approved</label>
            </div>
            <button type="submit" class="btn btn-primary">Save Instructor</button>
            <?php if ($edit_instructor): ?>
              <a href="manage_instructors.php" class="btn btn-secondary ms-2">Cancel</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-dark text-white">Assign Courses</div>
        <div class="card-body">
          <?php if (!$edit_instructor): ?>
            <p class="text-muted mb-0">Select an instructor from the list below to assign courses.</p>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="action" value="assign_courses">
              <input type="hidden" name="instructor_id" value="<?= (int)$edit_instructor['id'] ?>">
              <div class="mb-3">
                <label class="form-label">Courses</label>
                <div class="border rounded p-2" style="max-height:260px;overflow:auto;">
                  <?php foreach ($courses as $c): ?>
                    <?php $checked = in_array((int)$c['id'], $edit_assigned_ids, true) ? 'checked' : ''; ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="course_ids[]" value="<?= (int)$c['id'] ?>" id="cid<?= (int)$c['id'] ?>" <?= $checked ?>>
                      <label class="form-check-label" for="cid<?= (int)$c['id'] ?>">
                        <?= htmlspecialchars($c['title']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                  <?php if (empty($courses)): ?>
                    <p class="text-muted mb-0">No courses found.</p>
                  <?php endif; ?>
                </div>
              </div>
              <button type="submit" class="btn btn-primary">Save Assignments</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white">Instructors List</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
          <thead class="table-dark">
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Name</th>
              <th>Approved</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($instructors)): ?>
              <tr><td colspan="5" class="text-center text-muted">No instructors found.</td></tr>
            <?php else: foreach ($instructors as $inst): ?>
              <tr>
                <td><?= (int)$inst['id'] ?></td>
                <td><?= htmlspecialchars(($inst['user_name'] ?? '') . ' (' . ($inst['email'] ?? '') . ')') ?></td>
                <td><?= htmlspecialchars($inst['name']) ?></td>
                <td>
                  <span class="badge bg-<?= (int)$inst['approved'] ? 'success' : 'secondary' ?>">
                    <?= (int)$inst['approved'] ? 'Yes' : 'No' ?>
                  </span>
                </td>
                <td>
                  <a href="manage_instructors.php?edit=<?= (int)$inst['id'] ?>" class="btn btn-sm btn-primary">Edit / Assign</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<?php include '../includes/admin_footer.php'; ?>
