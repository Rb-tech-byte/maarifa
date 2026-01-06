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

// Get user's contact messages
$messages = $pdo->prepare("
    SELECT cm.*, u.name as user_name, u.email as user_email
    FROM contact_messages cm
    LEFT JOIN users u ON cm.user_id = u.id
    WHERE cm.user_id = ? OR (cm.user_id IS NULL AND cm.email = ?)
    ORDER BY cm.created_at DESC
");
$messages->execute([$user_id, $_SESSION['user_email'] ?? '']);
$messages = $messages->fetchAll(PDO::FETCH_ASSOC);

// Mark contact messages with replies as viewed
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS user_contact_views (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      contact_id INT NOT NULL,
      viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_user_contact (user_id, contact_id),
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (contact_id) REFERENCES contact_messages(id) ON DELETE CASCADE
    )
  ");

  // Insert view records for contact messages that have been replied to and haven't been viewed yet
  $stmt = $pdo->prepare("
    INSERT IGNORE INTO user_contact_views (user_id, contact_id)
    SELECT ?, cm.id
    FROM contact_messages cm
    WHERE (cm.user_id = ? OR (cm.user_id IS NULL AND cm.email = ?))
    AND cm.status = 'replied'
  ");
  $stmt->execute([$user_id, $user_id, $_SESSION['user_email'] ?? '']);
} catch (Exception $e) {
  // Silently handle errors - don't break the page
}

$title = 'My Contact Messages - AK23 App';
include 'includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">My Contact Messages</h1>
    <a href="contact_us.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> Send New Message
    </a>
  </div>

  <?php if (empty($messages)): ?>
    <div class="text-center py-5">
      <i class="fas fa-envelope-open-text fa-3x text-muted mb-3"></i>
      <h5 class="text-muted">No contact messages yet</h5>
      <p class="text-muted">Send us a message using the contact form to get started.</p>
      <a href="contact_us.php" class="btn btn-primary">Send Message</a>
    </div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($messages as $message): ?>
        <div class="col-md-6 mb-4">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h6 class="mb-0">Message #<?= $message['id'] ?></h6>
              <span class="badge bg-<?= $message['status'] === 'new' ? 'secondary' : ($message['status'] === 'read' ? 'info' : ($message['status'] === 'replied' ? 'success' : 'secondary')) ?>">
                <?= htmlspecialchars(ucfirst($message['status'])) ?>
              </span>
            </div>
            <div class="card-body">
              <h6 class="card-title text-truncate" title="<?= htmlspecialchars($message['subject']) ?>">
                <?= htmlspecialchars($message['subject']) ?>
              </h6>
              <p class="card-text small text-muted mb-2">
                <strong>Category:</strong> <?= htmlspecialchars(ucfirst($message['category'])) ?> |
                <strong>Priority:</strong> <?= htmlspecialchars(ucfirst($message['priority'])) ?>
              </p>
              <p class="card-text">
                <?= htmlspecialchars(substr($message['message'], 0, 150)) ?>
                <?php if (strlen($message['message']) > 150): ?>...<?php endif; ?>
              </p>
              <?php if ($message['phone']): ?>
                <p class="card-text small text-muted">
                  <strong>Phone:</strong> <?= htmlspecialchars($message['phone']) ?>
                </p>
              <?php endif; ?>
            </div>
            <div class="card-footer text-muted small">
              Sent: <?= htmlspecialchars(date('M d, Y H:i', strtotime($message['created_at']))) ?>
              <?php if ($message['updated_at'] && $message['updated_at'] !== $message['created_at']): ?>
                <br>Last updated: <?= htmlspecialchars(date('M d, Y H:i', strtotime($message['updated_at']))) ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
