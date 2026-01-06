<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
if (!isset($_SESSION['user_logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_ticket'])) {
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        if (!empty($subject) && !empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, message, status, priority) VALUES (?, ?, ?, 'open', 'medium')");
            $stmt->execute([$_SESSION['user_id'], $subject, $message]);
            $ticket_id = $pdo->lastInsertId();
            // Notify user
            createNotification($_SESSION['user_id'], 'system', 'Your ticket has been submitted.');
            // Notify admin (user_id=1 as example)
            createNotification(1, 'system', 'A new ticket has been submitted by user #' . $_SESSION['user_id'] . '.');
            header('Location: tickets.php?action=reply&id=' . $ticket_id);
            exit();
        }
    } elseif (isset($_POST['submit_reply']) && isset($_GET['id'])) {
        $reply_message = trim($_POST['reply_message']);
        $ticket_id = intval($_GET['id']);
        if (!empty($reply_message)) {
            $check_stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ? AND user_id = ?");
            $check_stmt->execute([$ticket_id, $_SESSION['user_id']]);
            if ($check_stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
                $stmt->execute([$ticket_id, $_SESSION['user_id'], $reply_message]);
                // Notify user
                createNotification($_SESSION['user_id'], 'system', 'Your ticket reply has been submitted.');
                // Notify admin (user_id=1 as example)
                createNotification(1, 'system', 'A user replied to ticket #' . $ticket_id . '.');
                header('Location: tickets.php?action=reply&id=' . $ticket_id);
                exit();
            }
        }
    }
}

$title = 'My Tickets - AK23 App';
include 'user_header.php';
?>
<?php include 'user_sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <?php
    $action = $_GET['action'] ?? 'list';
    $user_id = $_SESSION['user_id'];

    if ($action === 'reply' && isset($_GET['id'])) {
        $ticket_id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT t.*, u.username FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.user_id = ?");
        $stmt->execute([$ticket_id, $user_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            echo "<div class='alert alert-danger'>Ticket not found or you don't have permission to view it.</div>";
        } else {
            $reply_stmt = $pdo->prepare("
                SELECT tr.*, u.is_admin, u.username, au.username as admin_username
                FROM ticket_replies tr
                LEFT JOIN users u ON tr.user_id = u.id AND u.is_admin = 0
                LEFT JOIN admin_users au ON tr.user_id = au.id AND u.id IS NULL
                WHERE tr.ticket_id = ?
                ORDER BY tr.created_at ASC
            ");
            $reply_stmt->execute([$ticket_id]);
            $replies = $reply_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
            <h3 style="color: black;">Ticket #<?= $ticket['id'] ?>: <?= htmlspecialchars($ticket['subject']) ?></h3>
            <a href="tickets.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Tickets</a>

            <div class="card mb-3">
                <div class="card-header fw-bold">You <span class="text-muted float-end"><?= $ticket['created_at'] ?></span></div>
                <div class="card-body">
                    <p class="card-text"><?= nl2br(htmlspecialchars($ticket['message'])) ?></p>
                </div>
            </div>

            <?php foreach ($replies as $reply) :
                $sender = ($reply['user_id'] == $_SESSION['user_id'] && !$reply['admin_username']) ? 'You' : 'Admin';
                $card_class = ($reply['user_id'] == $_SESSION['user_id'] && !$reply['admin_username']) ? 'bg-light' : '';
            ?>
                <div class="card mb-3 <?= $card_class ?>">
                    <div class="card-header fw-bold"><?= $sender ?> <span class="text-muted float-end"><?= $reply['created_at'] ?></span></div>
                    <div class="card-body">
                        <p class="card-text"><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>

            <h4 class="mt-4" style="color: black;">Your Reply</h4>
            <form method="POST">
                <div class="mb-3">
                    <textarea name="reply_message" class="form-control" rows="4" placeholder="Type your reply..." required></textarea>
                </div>
                <button type="submit" name="submit_reply" class="btn btn-primary">Send Reply</button>
            </form>
        <?php
        }
    } elseif ($action === 'new') {
        ?>
        <h3 class="mt-4" style="color: black;">Submit New Ticket</h3>
        <form method="POST">
            <div class="mb-3">
                <label for="subject" class="form-label" style="color: black;">Subject</label>
                <input type="text" id="subject" name="subject" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="message" class="form-label" style="color: black;">Message</label>
                <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" name="submit_ticket" class="btn btn-primary">Submit Ticket</button>
            <a href="tickets.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php
    } else {
        $tickets_stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC");
        $tickets_stmt->execute([$user_id]);
        $tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
        <h2 class="h4 mb-4" style="color: black;">My Support Tickets</h2>
        <a href="tickets.php?action=new" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Submit New Ticket</a>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket) : ?>
                        <tr>
                            <td><?= $ticket['id'] ?></td>
                            <td><?= htmlspecialchars($ticket['subject']) ?></td>
                            <td><span class="badge bg-<?= $ticket['status'] === 'closed' ? 'secondary' : ($ticket['status'] === 'in_progress' ? 'info' : 'success') ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span></td>
                            <td><?= htmlspecialchars($ticket['updated_at']) ?></td>
                            <td><a href="tickets.php?action=reply&id=<?= $ticket['id'] ?>" class="btn btn-sm btn-info">View / Reply</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php
    }
    ?>
</main>
<?php include 'user_footer.php'; ?>