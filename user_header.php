<?php
  require_once __DIR__ . '/includes/base.php';

  // Get unread replies count for user notifications
  $unread_replies_count = 0;
  if (isset($_SESSION['user_logged_in']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    try {
      if (isset($pdo)) {
        // Count tickets with admin replies that user hasn't seen
        $stmt = $pdo->prepare("
          SELECT COUNT(DISTINCT t.id) as unread_replies
          FROM tickets t
          INNER JOIN ticket_replies tr ON t.id = tr.ticket_id
          LEFT JOIN admin_users au ON tr.user_id = au.id
          WHERE t.user_id = ? AND au.id IS NOT NULL
          AND t.status != 'closed'
          AND NOT EXISTS (
            SELECT 1 FROM user_ticket_views uv
            WHERE uv.ticket_id = t.id AND uv.user_id = ?
          )
        ");
        $stmt->execute([$user_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unread_replies_count = $result['unread_replies'] ?? 0;

        // Also check for contact messages that have been replied to
        $contact_stmt = $pdo->prepare("
          SELECT COUNT(*) as contact_replies
          FROM contact_messages
          WHERE user_id = ? AND status = 'replied'
          AND NOT EXISTS (
            SELECT 1 FROM user_contact_views ucv
            WHERE ucv.contact_id = contact_messages.id AND ucv.user_id = ?
          )
        ");
        $contact_stmt->execute([$user_id, $user_id]);
        $contact_result = $contact_stmt->fetch(PDO::FETCH_ASSOC);
        $unread_replies_count += $contact_result['contact_replies'] ?? 0;
      }
    } catch (Throwable $e) {
      $unread_replies_count = 0;
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'User Dashboard - AK23 App' ?></title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
    <link rel="icon" href="<?= $base ?>/assets/images/ak.png" type="image/png">
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/4u6o2mz2yxh8t5wkmid20clr1747ciygh59dm09n7f03ibcj/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      (function(){
        function eligibleTextareas(root){
          var scope = root || document;
          return Array.prototype.slice.call(scope.querySelectorAll(
            'textarea.rte, textarea[name="description"], textarea[name*="description"], textarea[name*="content"], textarea[data-editor="rich"]'
          ));
        }
        function initTinyOn(el){
          if (el._tinymceInit) return;
          el._tinymceInit = true;
          var mode = (el.dataset && (el.dataset.editorMode || el.dataset.editor)) || '';
          var isSimple = /simple/i.test(mode) || el.classList.contains('rte-simple');
          var toolbar = isSimple
            ? 'bold italic underline | bullist numlist | link | removeformat'
            : 'undo redo | blocks | bold italic underline | bullist numlist | link table media | removeformat | code';
          var plugins = isSimple
            ? 'lists link autoresize'
            : 'lists link table code autoresize media';

          var baseUrl = '<?= $base ?>';
          var allowedEmbedDomains = [
            'youtube.com','www.youtube.com','youtu.be','vimeo.com','player.vimeo.com','soundcloud.com','w.soundcloud.com',
            location.hostname
          ];

          tinymce.init({
            target: el,
            menubar: false,
            plugins: plugins,
            toolbar: toolbar,
            branding: false,
            convert_urls: false,
            height: 300,
            media_live_embeds: true,
            extended_valid_elements: 'iframe[src|frameborder|style|scrolling|class|width|height|name|align|allow|allowfullscreen],audio[controls|src|type],source[src|type],video[controls|src|type|width|height|poster]',
            file_picker_types: 'media',
            file_picker_callback: function (callback, value, meta) {
              if (meta.filetype !== 'media') return;
              var input = document.createElement('input');
              input.type = 'file';
              input.accept = 'audio/*,video/*';
              input.onchange = function () {
                var file = this.files[0];
                if (!file) return;
                var form = new FormData();
                form.append('file', file);
                fetch(baseUrl + '/api/upload_media.php', { method: 'POST', body: form })
                  .then(function(res){ return res.json(); })
                  .then(function(data){
                    if (data && data.location) {
                      callback(data.location, { title: file.name });
                    } else {
                      alert((data && data.error) ? data.error : 'Upload failed');
                    }
                  })
                  .catch(function(){ alert('Upload error'); });
              };
              input.click();
            },
            media_url_resolver: function(data, resolve){
              try {
                var u = new URL(data.url, location.origin);
                if (allowedEmbedDomains.indexOf(u.hostname) !== -1) {
                  resolve({ html: '' });
                } else {
                  resolve({ html: '' });
                }
              } catch(_) { resolve({ html: '' }); }
            }
          });
        }
        function initRTE(root){ eligibleTextareas(root).forEach(initTinyOn); }
        window.initRTE = initRTE;
        function tryInit(){ if (window.tinymce) initRTE(); }
        document.addEventListener('DOMContentLoaded', tryInit);
        window.addEventListener('load', tryInit);
        setTimeout(tryInit, 500);
        setTimeout(tryInit, 1500);
        document.addEventListener('shown.bs.modal', function(e){ initRTE(e.target); });
        try {
          var mo = new MutationObserver(function(muts){
            muts.forEach(function(m){
              if (m.addedNodes && m.addedNodes.length){
                m.addedNodes.forEach(function(node){
                  if (node.nodeType === 1){
                    if (node.matches && (node.matches('textarea.rte') || node.matches('textarea[name="description"]') || node.matches('textarea[name*="description"]') || node.matches('textarea[name*="content"]') || node.matches('textarea[data-editor="rich"]'))){
                      initRTE(node.parentNode || node);
                    } else if (node.querySelector && (node.querySelector('textarea.rte') || node.querySelector('textarea[name="description"]') || node.querySelector('textarea[name*="description"]') || node.querySelector('textarea[name*="content"]') || node.querySelector('textarea[data-editor="rich"]'))){
                      initRTE(node);
                    }
                  }
                });
              }
            });
          });
          mo.observe(document.documentElement, { childList: true, subtree: true });
        } catch(_){ }
      })();
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">
<div class="d-flex min-vh-100 flex-column">
  <nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
      <button class="btn btn-outline-light me-2 d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebarOffcanvas" aria-controls="userSidebarOffcanvas">
        <i class="fas fa-bars" style="color:#fff;"></i>
      </button>
      <style>
        .brand-logo { height: 28px; width:auto; border-radius:6px; object-fit:contain; }
      </style>
      <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base ?>/user_index.php" style="color:#fff !important;">
        <img src="<?= $base ?>/assets/images/ak.png" class="brand-logo" alt="AK Logo">
        <span>AKDOWNLOADS</span>
      </a>
      <div class="ms-auto d-flex align-items-center">
        <!-- Notifications Bell -->
        <?php if (isset($_SESSION['user_logged_in'])): ?>
        <div class="dropdown me-3">
          <button class="btn btn-outline-light position-relative" type="button" id="userNotificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unread_replies_count > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                <?= $unread_replies_count > 99 ? '99+' : $unread_replies_count ?>
                <span class="visually-hidden">unread replies</span>
              </span>
            <?php endif; ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userNotificationsDropdown" style="min-width: 320px; max-width: 400px;">
            <li><h6 class="dropdown-header">Replies & Updates</h6></li>
            <?php
            // Get recent replies for dropdown
            $recent_replies = [];
            if (isset($_SESSION['user_logged_in']) && isset($_SESSION['user_id'])) {
              $user_id = $_SESSION['user_id'];
              try {
                if (isset($pdo)) {
                  // Get ticket replies
                  $ticket_stmt = $pdo->prepare("
                    SELECT DISTINCT t.id, t.subject, 'ticket' as type, tr.created_at as reply_date,
                           'Admin replied to your ticket' as message
                    FROM tickets t
                    INNER JOIN ticket_replies tr ON t.id = tr.ticket_id
                    LEFT JOIN admin_users au ON tr.user_id = au.id
                    WHERE t.user_id = ? AND au.id IS NOT NULL
                    AND t.status != 'closed'
                    AND NOT EXISTS (
                      SELECT 1 FROM user_ticket_views uv
                      WHERE uv.ticket_id = t.id AND uv.user_id = ?
                    )
                    ORDER BY tr.created_at DESC
                    LIMIT 3
                  ");
                  $ticket_stmt->execute([$user_id, $user_id]);
                  $ticket_replies = $ticket_stmt->fetchAll(PDO::FETCH_ASSOC);

                  // Get contact message replies
                  $contact_stmt = $pdo->prepare("
                    SELECT cm.id, cm.subject, 'contact' as type, cm.updated_at as reply_date,
                           'Admin replied to your message' as message
                    FROM contact_messages cm
                    WHERE cm.user_id = ? AND cm.status = 'replied'
                    AND NOT EXISTS (
                      SELECT 1 FROM user_contact_views ucv
                      WHERE ucv.contact_id = cm.id AND ucv.user_id = ?
                    )
                    ORDER BY cm.updated_at DESC
                    LIMIT 3
                  ");
                  $contact_stmt->execute([$user_id, $user_id]);
                  $contact_replies = $contact_stmt->fetchAll(PDO::FETCH_ASSOC);

                  $recent_replies = array_merge($ticket_replies, $contact_replies);
                  // Sort by reply date descending and limit to 5
                  usort($recent_replies, function($a, $b) {
                    return strtotime($b['reply_date']) - strtotime($a['reply_date']);
                  });
                  $recent_replies = array_slice($recent_replies, 0, 5);
                }
              } catch (Throwable $e) {
                $recent_replies = [];
              }
            }

            if (!empty($recent_replies)):
              foreach ($recent_replies as $reply):
                $url = ($reply['type'] === 'ticket') ? 'user_tickets.php' : 'user_contact_messages.php';
            ?>
              <li>
                <a class="dropdown-item" href="<?= $url ?>">
                  <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                      <div class="fw-bold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($reply['subject']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($reply['message']) ?></small>
                    </div>
                    <small class="text-muted ms-2">
                      <?= htmlspecialchars(date('M d', strtotime($reply['reply_date']))) ?>
                    </small>
                  </div>
                </a>
              </li>
            <?php
              endforeach;
            ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center fw-bold" href="notifications.php">View All Notifications</a></li>
            <?php else: ?>
              <li><span class="dropdown-item text-muted">No new replies</span></li>
            <?php endif; ?>
          </ul>
        </div>
        <?php endif; ?>

        <a class="btn btn-warning btn-sm" href="<?= $base ?>/index.php">
          <i class="fas fa-home me-1"></i> Home
        </a>
      </div>
    </div>
  </nav>
  <style>
    @media (max-width: 768px) {
      .navbar-brand,
      .navbar .btn,
      .navbar .fas {
        color: #fff !important;
      }
      .navbar {
        background-color: #222 !important;
      }
    }

    /* Notification Bell Styles */
    .notification-bell {
      position: relative;
    }
    .notification-bell .badge {
      animation: bell-pulse 2s infinite;
    }
    @keyframes bell-pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    .dropdown-item:hover {
      background-color: #f8f9fa;
    }
  </style>
  <div class="container-fluid flex-grow-1 d-flex p-0">