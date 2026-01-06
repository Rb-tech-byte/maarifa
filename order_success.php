<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/download_helper.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$stmt = $pdo->prepare("SELECT o.*, p.title AS course_name, p.price FROM orders o JOIN courses p ON o.course_id=p.id WHERE o.id=?");
$stmt->execute([$order_id]);
$o = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$o) {
  http_response_code(404);
  exit('Order not found');
}

$downloadLink = '';
if ($o['status'] === 'completed') {
  $token = generate_download_token((int)$o['id']);
  $downloadLink = 'downloads.php?token=' . urlencode($token);
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Order Summary</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container" style="max-width:720px;">
    <h2 class="mb-3">Order Summary</h2>
    <div class="card mb-3">
      <div class="card-body">
        <p class="mb-1"><strong>Order:</strong> ORD<?= (int)$o['id'] ?></p>
        <p class="mb-1"><strong>course:</strong> <?= htmlspecialchars($o['course_name']) ?></p>
        <p class="mb-1"><strong>Amount:</strong> <?= PESAPAL_CURRENCY ?> <?= number_format((float)$o['price'], 2) ?></p>
        <p class="mb-1"><strong>Status:</strong> <?= htmlspecialchars($o['status']) ?></p>
        <?php if ($downloadLink): ?>
          <a class="btn btn-success mt-2" href="<?= htmlspecialchars($downloadLink) ?>">Download</a>
        <?php else: ?>
          <p class="text-muted mt-2">Once your payment completes, your download link will appear here.</p>
        <?php endif; ?>
      </div>
    </div>
    <a href="/my_downloads.php" class="btn btn-outline-primary">Go to My Downloads</a>
  </div>
</body>
</html>
