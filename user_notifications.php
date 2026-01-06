<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$notifications = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notifications->execute([$user_id]);
$notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);

$title = 'My Notifications - AK23 App';
include 'includes/header.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-4">My Notifications</h1>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Message</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notifications as $note): ?>
          <tr>
            <td><?= $note['id'] ?></td>
            <td><?= htmlspecialchars($note['message']) ?></td>
            <td><?= htmlspecialchars($note['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?> 