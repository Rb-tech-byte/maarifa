<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}
$title = 'My Notifications - AK23 App';
include 'user_header.php';
?>
<?php include 'user_sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
  <?php
  $user_id = $_SESSION['user_id'];
  $notifications = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
  $notifications->execute([$user_id]);
  $notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <h2 class="h4 mb-4" style="color: black;">My Notifications</h2>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Message</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notifications as $note): ?>
          <tr>
            <td><?= $note['id'] ?></td>
            <td><span class="badge bg-<?= $note['type'] === 'sms' ? 'info' : 'primary' ?> text-uppercase"><?= strtoupper($note['type']) ?></span></td>
            <td><?= htmlspecialchars($note['message']) ?></td>
            <td><?= htmlspecialchars($note['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<script>
function fetchNotifications() {
    fetch('api/notifications_poll.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let tbody = document.querySelector('tbody');
                if (!tbody) return;
                tbody.innerHTML = '';
                data.notifications.forEach(note => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${note.id}</td>
                            <td><span class="badge bg-${note.type === 'sms' ? 'info' : 'primary'} text-uppercase">${note.type.toUpperCase()}</span></td>
                            <td>${note.message}</td>
                            <td>${note.created_at}</td>
                        </tr>
                    `;
                });
            }
        });
}
setInterval(fetchNotifications, 5000); // Poll every 5 seconds
window.addEventListener('DOMContentLoaded', fetchNotifications);
</script>
<?php include 'user_footer.php'; ?> 