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
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    if ($subject && $message) {
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'open')");
        if ($stmt->execute([$user_id, $subject, $message])) {
            $success = 'Ticket submitted successfully.';
        } else {
            $error = 'Failed to submit ticket.';
        }
    } else {
        $error = 'All fields are required.';
    }
}
$title = 'Submit Ticket - AK23 App';
include 'includes/header.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-4">Submit Support Ticket</h1>
  <?php if ($success): ?><div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div><?php endif; ?>
  <form method="POST" class="row g-3">
    <div class="col-md-8">
      <label class="form-label">Subject</label>
      <input type="text" name="subject" class="form-control" required>
    </div>
    <div class="col-12">
      <label class="form-label">Message</label>
      <textarea name="message" class="form-control" rows="5" required></textarea>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Submit Ticket</button>
      <a href="user_tickets.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php include 'includes/footer.php'; ?> 