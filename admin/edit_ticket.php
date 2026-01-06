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

if (!isset($_GET['id'])) {
    header('Location: tickets.php');
    exit();
}
$ticket_id = intval($_GET['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $reply_message = trim($_POST['reply_message']);
    $status = $_POST['status'];
    $admin_id = $_SESSION['admin_id']; 

    if (!empty($reply_message)) {
        $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$ticket_id, $admin_id, $reply_message]);
        // Notify ticket owner
        $ticket_stmt = $pdo->prepare("SELECT user_id FROM tickets WHERE id = ?");
        $ticket_stmt->execute([$ticket_id]);
        $ticket = $ticket_stmt->fetch(PDO::FETCH_ASSOC);
        if ($ticket) {
            createNotification($ticket['user_id'], 'system', 'Admin replied to your ticket #' . $ticket_id . '.');
        }
        // Optionally notify admin (self or others)
        // createNotification($admin_id, 'system', 'You replied to ticket #' . $ticket_id . '.');
    }

    $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $ticket_id]);

    header('Location: edit_ticket.php?id=' . $ticket_id . '&success=1');
    exit();
}

$stmt = $pdo->prepare("SELECT t.*, u.username FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: tickets.php');
    exit();
}

$reply_stmt = $pdo->prepare("
    SELECT tr.*, u.username, u.is_admin, au.username as admin_username
    FROM ticket_replies tr 
    LEFT JOIN users u ON tr.user_id = u.id AND u.is_admin = 0
    LEFT JOIN admin_users au ON tr.user_id = au.id AND u.id IS NULL
    WHERE tr.ticket_id = ? 
    ORDER BY tr.created_at ASC
");
$reply_stmt->execute([$ticket_id]);
$replies = $reply_stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'View Ticket - AK23 App';
include '../includes/admin_header.php';
?>

<h3 style="color: black;">Ticket #<?= $ticket['id'] ?>: <?= htmlspecialchars($ticket['subject']) ?></h3>
<p style="color: black;"><strong>User:</strong> <?= htmlspecialchars($ticket['username']) ?></p>
<a href="tickets.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Tickets</a>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Ticket updated successfully.</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header fw-bold">User <span class="text-muted float-end"><?= $ticket['created_at'] ?></span></div>
    <div class="card-body">
        <p class="card-text"><?= nl2br(htmlspecialchars($ticket['message'])) ?></p>
    </div>
</div>

<?php foreach ($replies as $reply) :
    $sender = $reply['admin_username'] ? 'Admin (' . htmlspecialchars($reply['admin_username']) . ')' : 'User (' . htmlspecialchars($reply['username']) . ')';
    $card_class = $reply['admin_username'] ? 'bg-light' : '';
?>
    <div class="card mb-3 <?= $card_class ?>">
        <div class="card-header fw-bold"><?= $sender ?> <span class="text-muted float-end"><?= $reply['created_at'] ?></span></div>
        <div class="card-body">
            <p class="card-text"><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
        </div>
    </div>
<?php endforeach; ?>

<h4 class="mt-4" style="color: black;">Reply to Ticket</h4>
<form method="POST">
    <div class="mb-3">
        <label for="reply_message" class="form-label" style="color: black;">Your Reply</label>
        <textarea id="reply_message" name="reply_message" class="form-control rte" rows="4" placeholder="Type your reply..."></textarea>
    </div>
    <div class="mb-3">
        <label for="status" class="form-label" style="color: black;">Update Status</label>
        <select id="status" name="status" class="form-select">
            <option value="open" <?= $ticket['status'] == 'open' ? 'selected' : '' ?>>Open</option>
            <option value="in_progress" <?= $ticket['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="closed" <?= $ticket['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
    </div>
    <button type="submit" name="submit_reply" class="btn btn-primary">Submit Reply & Update Status</button>
</form>
<?php include '../includes/admin_footer.php'; ?>