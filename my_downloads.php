<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/download_helper.php';
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
$buyerEmail = $_COOKIE['buyer_email'] ?? '';
if (!$buyerEmail) {
    echo '<p>Please provide your email by purchasing or contact support for your downloads.</p>';
    exit;
}

// Find the user by email
$u = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ?');
$u->execute([$buyerEmail]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo '<p>No account found for ' . htmlspecialchars($buyerEmail) . '.</p>';
    exit;
}

$ordersStmt = $pdo->prepare("SELECT o.id, o.created_at, p.title AS course_name
                              FROM orders o
                              JOIN courses p ON o.course_id = p.id
                              WHERE o.user_id = ? AND o.status = 'completed'
                              ORDER BY o.id DESC");
$ordersStmt->execute([$user['id']]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<h2>My Downloads</h2>
<p>Signed in as: <?= htmlspecialchars($buyerEmail) ?></p>
<?php if (empty($orders)): ?>
  <p>You have no completed orders yet.</p>
<?php else: ?>
  <ul>
  <?php foreach ($orders as $o): ?>
    <?php $token = generate_download_token((int)$o['id']); ?>
    <li>
      <?= htmlspecialchars($o['course_name']) ?>
      — <a href="downloads.php?token=<?= htmlspecialchars($token) ?>">Download</a>
    </li>
  <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php include 'user_footer.php'; ?> 