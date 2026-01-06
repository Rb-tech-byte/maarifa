<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$id = (int)$_GET['id'];

// Perform delete first, then redirect without sending any output
if (deletecourse($id)) {
    header('Location: courses.php?deleted=1');
    exit();
}

// If delete failed, render an error page
$title = 'Delete Course - AK23 App';
include '../includes/admin_header.php';
?>
<main class="admin-main container">
  <div class="alert alert-danger mt-4">Failed to delete course. <a href="dashboard.php">Back to Dashboard</a></div>
</main>
<?php include '../includes/admin_footer.php'; ?>