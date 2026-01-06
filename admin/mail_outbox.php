<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$off = ($page - 1) * $perPage;

$total = (int)$pdo->query("SELECT COUNT(*) FROM mail_outbox")->fetchColumn();
$stmt = $pdo->prepare("SELECT id, `to`, `subject`, LEFT(`body`, 500) AS body, created_at, sent_at, last_error FROM mail_outbox ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $off, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
  <h1 class="h4 mb-3">Mail Outbox</h1>
  <div class="mb-3">
    <a class="btn btn-sm btn-primary" href="../scripts/send_outbox.php" target="_blank">Run Sender Now</a>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>To</th>
          <th>Subject</th>
          <th>Body</th>
          <th>Created</th>
          <th>Sent At</th>
          <th>Last Error</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['to']) ?></td>
            <td><?= htmlspecialchars($r['subject']) ?></td>
            <td><pre class="mb-0" style="white-space: pre-wrap; font-size: 0.85rem; max-width: 560px;"><?= htmlspecialchars($r['body']) ?></pre></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td><?= htmlspecialchars($r['sent_at'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['last_error'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php $pages = max(1, (int)ceil($total / $perPage)); if ($pages > 1): ?>
  <nav>
    <ul class="pagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
        <li class="page-item<?= $i===$page?' active':''?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
