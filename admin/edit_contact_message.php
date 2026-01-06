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
    header('Location: contact_messages.php');
    exit();
}
$message_id = intval($_GET['id']);

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $reply_message = trim($_POST['reply_message']);
    $status = $_POST['status'];
    $send_email = isset($_POST['send_email']) ? true : false;

    // Update message status
    $stmt = $pdo->prepare("UPDATE contact_messages SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $message_id]);

    // If there's a reply message, store it (we'll create a replies table or add to the message)
    if (!empty($reply_message)) {
        // For now, we'll append to the message or create a simple reply system
        // You could create a contact_message_replies table similar to ticket_replies

        // Get original message details
        $msg_stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $msg_stmt->execute([$message_id]);
        $message = $msg_stmt->fetch(PDO::FETCH_ASSOC);

        if ($message && $send_email) {
            // Send email reply
            $to = $message['email'];
            $subject = "Re: " . $message['subject'];
            $email_body = "Dear " . $message['name'] . ",\n\n";
            $email_body .= $reply_message . "\n\n";
            $email_body .= "Best regards,\n";
            $email_body .= "AK23 Support Team\n\n";
            $email_body .= "---\nOriginal message:\n" . $message['message'];

            // Send email using existing function
            if (function_exists('sendMail')) {
                sendMail($to, $subject, $email_body);
            }
        }
    }

    header('Location: edit_contact_message.php?id=' . $message_id . '&success=1');
    exit();
}

// Get message details
$stmt = $pdo->prepare("
    SELECT cm.*,
           CASE WHEN cm.user_id IS NOT NULL THEN u.name ELSE cm.name END as display_name,
           CASE WHEN cm.user_id IS NOT NULL THEN u.email ELSE cm.email END as display_email
    FROM contact_messages cm
    LEFT JOIN users u ON cm.user_id = u.id
    WHERE cm.id = ?
");
$stmt->execute([$message_id]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    header('Location: contact_messages.php');
    exit();
}

// Mark as read if it's new
if ($message['status'] === 'new') {
    $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?")->execute([$message_id]);
    $message['status'] = 'read'; // Update local copy
}

$title = 'View Contact Message - AK23 App';
include '../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3" style="color: black;">Contact Message #<?= $message['id'] ?></h1>
  <div>
    <span class="badge bg-<?= $message['status'] === 'new' ? 'danger' : ($message['status'] === 'read' ? 'warning' : ($message['status'] === 'replied' ? 'info' : 'success')) ?> me-2">
      <?= htmlspecialchars(ucfirst($message['status'])) ?>
    </span>
    <a href="contact_messages.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Back to Messages
    </a>
  </div>
</div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">Message updated successfully.</div>
<?php endif; ?>

<!-- Message Details -->
<div class="row">
  <div class="col-md-8">
    <!-- Original Message -->
    <div class="card mb-3">
      <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <strong>From:</strong> <?= htmlspecialchars($message['display_name']) ?>
            <?php if ($message['user_id']): ?>
              <span class="badge bg-primary ms-2">Registered User</span>
            <?php else: ?>
              <span class="badge bg-secondary ms-2">Guest</span>
            <?php endif; ?>
            <br>
            <strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($message['display_email']) ?>"><?= htmlspecialchars($message['display_email']) ?></a>
            <?php if ($message['phone']): ?>
              <br><strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($message['phone']) ?>"><?= htmlspecialchars($message['phone']) ?></a>
            <?php endif; ?>
          </div>
          <div class="text-end">
            <small class="text-muted">
              <?= htmlspecialchars(date('M d, Y \a\t H:i', strtotime($message['created_at']))) ?>
            </small>
            <br>
            <span class="badge bg-light text-dark">Priority: <?= htmlspecialchars(ucfirst($message['priority'])) ?></span>
          </div>
        </div>
      </div>
      <div class="card-body">
        <h5 class="card-title"><?= htmlspecialchars($message['subject']) ?></h5>
        <p class="text-muted mb-2">Category: <span class="badge bg-light text-dark"><?= htmlspecialchars(ucfirst($message['category'])) ?></span></p>
        <div class="card-text">
          <?= nl2br(htmlspecialchars($message['message'])) ?>
        </div>
        <?php if ($message['ip_address']): ?>
          <hr>
          <small class="text-muted">
            <strong>IP Address:</strong> <?= htmlspecialchars($message['ip_address']) ?>
            <?php if ($message['user_agent']): ?>
              <br><strong>User Agent:</strong> <?= htmlspecialchars(substr($message['user_agent'], 0, 100)) ?><?php if (strlen($message['user_agent']) > 100): ?>...<?php endif; ?>
            <?php endif; ?>
          </small>
        <?php endif; ?>
      </div>
    </div>

    <!-- Reply Form -->
    <div class="card">
      <div class="card-header bg-warning text-dark">
        <h6 class="mb-0"><i class="fas fa-reply me-2"></i>Reply to Message</h6>
      </div>
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label for="reply_message" class="form-label" style="color: black;">Your Reply</label>
            <div class="mb-2">
              <select id="reply_template" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                <option value="">Choose a reply template...</option>
                <option value="acknowledgment">Acknowledgment</option>
                <option value="thank_you">Thank You</option>
                <option value="follow_up">Follow Up Required</option>
                <option value="resolution">Issue Resolved</option>
                <option value="escalation">Escalation Notice</option>
              </select>
              <button type="button" id="apply_template" class="btn btn-sm btn-outline-secondary ms-2">Apply Template</button>
            </div>
            <textarea id="reply_message" name="reply_message" class="form-control rte" rows="8"
                      placeholder="Type your reply to the customer..."></textarea>
            <div class="form-text">Use the rich text editor for formatting your reply. Templates can help speed up common responses.</div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="status" class="form-label" style="color: black;">Update Status</label>
                <select id="status" name="status" class="form-select">
                  <option value="read" <?= $message['status'] == 'read' ? 'selected' : '' ?>>Read</option>
                  <option value="replied" <?= $message['status'] == 'replied' ? 'selected' : '' ?>>Replied</option>
                  <option value="closed" <?= $message['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="send_email" name="send_email" checked>
                  <label class="form-check-label" for="send_email" style="color: black;">
                    Send reply via email
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" name="submit_reply" class="btn btn-success">
              <i class="fas fa-paper-plane me-2"></i>Send Reply & Update
            </button>
            <a href="mailto:<?= htmlspecialchars($message['display_email']) ?>?subject=Re: <?= htmlspecialchars($message['subject']) ?>"
               class="btn btn-outline-primary">
              <i class="fas fa-envelope me-2"></i>Reply via Email Client
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <!-- Quick Actions -->
    <div class="card mb-3">
      <div class="card-header bg-info text-white">
        <h6 class="mb-0">Quick Actions</h6>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="mailto:<?= htmlspecialchars($message['display_email']) ?>" class="btn btn-outline-primary">
            <i class="fas fa-envelope me-2"></i>Send Email
          </a>
          <?php if ($message['phone']): ?>
            <a href="tel:<?= htmlspecialchars($message['phone']) ?>" class="btn btn-outline-success">
              <i class="fas fa-phone me-2"></i>Call Customer
            </a>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $message['phone']) ?>" target="_blank" class="btn btn-outline-success">
              <i class="fab fa-whatsapp me-2"></i>WhatsApp
            </a>
          <?php endif; ?>
          <?php if ($message['user_id']): ?>
            <a href="user_profile.php?user_id=<?= $message['user_id'] ?>" class="btn btn-outline-secondary">
              <i class="fas fa-user me-2"></i>View User Profile
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Message Stats -->
    <div class="card">
      <div class="card-header bg-light">
        <h6 class="mb-0">Message Details</h6>
      </div>
      <div class="card-body">
        <div class="mb-2">
          <strong>Status:</strong>
          <span class="badge bg-<?= $message['status'] === 'new' ? 'danger' : ($message['status'] === 'read' ? 'warning' : ($message['status'] === 'replied' ? 'info' : 'success')) ?> float-end">
            <?= htmlspecialchars(ucfirst($message['status'])) ?>
          </span>
        </div>
        <div class="mb-2">
          <strong>Priority:</strong>
          <span class="badge bg-<?= $message['priority'] === 'high' ? 'danger' : ($message['priority'] === 'normal' ? 'primary' : 'secondary') ?> float-end">
            <?= htmlspecialchars(ucfirst($message['priority'])) ?>
          </span>
        </div>
        <div class="mb-2">
          <strong>Category:</strong>
          <span class="badge bg-light text-dark float-end">
            <?= htmlspecialchars(ucfirst($message['category'])) ?>
          </span>
        </div>
        <div class="mb-2">
          <strong>Submitted:</strong>
          <small class="float-end text-muted">
            <?= htmlspecialchars(date('M d, Y H:i', strtotime($message['created_at']))) ?>
          </small>
        </div>
        <?php if ($message['updated_at'] && $message['updated_at'] !== $message['created_at']): ?>
          <div class="mb-0">
            <strong>Last Updated:</strong>
            <small class="float-end text-muted">
              <?= htmlspecialchars(date('M d, Y H:i', strtotime($message['updated_at']))) ?>
            </small>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Reply templates
const replyTemplates = {
  acknowledgment: `Dear ${<?= json_encode($message['display_name']) ?>},

Thank you for contacting AK23 Downloads support. We have received your message regarding "${<?= json_encode($message['subject']) ?>}" and our team is reviewing it.

We will get back to you within 24 hours with a detailed response.

Best regards,
AK23 Support Team`,

  thank_you: `Dear ${<?= json_encode($message['display_name']) ?>},

Thank you for your patience and for choosing AK23 Downloads. We're glad we could assist you with your inquiry.

If you have any further questions or need additional support, please don't hesitate to contact us.

Best regards,
AK23 Support Team`,

  follow_up: `Dear ${<?= json_encode($message['display_name']) ?>},

We need some additional information to better assist you with your inquiry regarding "${<?= json_encode($message['subject']) ?>}".

Could you please provide:

1. [Additional details needed]
2. [Any relevant screenshots or files]
3. [Your account information if applicable]

Once we receive this information, we'll be able to provide you with a complete solution.

Best regards,
AK23 Support Team`,

  resolution: `Dear ${<?= json_encode($message['display_name']) ?>},

I'm pleased to inform you that your issue has been resolved. The problem with "${<?= json_encode($message['subject']) ?>" has been fixed and everything should now be working correctly.

If you encounter any further issues or have questions about this resolution, please let us know.

Thank you for your patience and for using AK23 Downloads.

Best regards,
AK23 Support Team`,

  escalation: `Dear ${<?= json_encode($message['display_name']) ?>},

Thank you for bringing this matter to our attention. Your inquiry regarding "${<?= json_encode($message['subject']) ?>" has been escalated to our senior technical team for immediate attention.

We apologize for any inconvenience this may have caused and appreciate your patience while we work on resolving this issue.

We will provide you with an update within the next 12 hours.

Best regards,
AK23 Support Team`
};

// Apply template functionality
document.getElementById('apply_template').addEventListener('click', function() {
  const templateSelect = document.getElementById('reply_template');
  const selectedTemplate = templateSelect.value;
  const textarea = document.getElementById('reply_message');

  if (selectedTemplate && replyTemplates[selectedTemplate]) {
    // Check if TinyMCE is loaded
    if (typeof tinymce !== 'undefined' && tinymce.get('reply_message')) {
      tinymce.get('reply_message').setContent(replyTemplates[selectedTemplate]);
    } else {
      textarea.value = replyTemplates[selectedTemplate];
    }
  }
});
</script>

<?php include '../includes/admin_footer.php'; ?>
