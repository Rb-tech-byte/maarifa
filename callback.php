<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pesapal_helper.php';

// Security: Only allow access from legitimate sources
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$remoteIP = $_SERVER['REMOTE_ADDR'] ?? '';

// Allow access from:
// 1. Localhost (development)
// 2. Pesapal domains
// 3. Our own domain
$allowedHosts = [
    'localhost',
    '127.0.0.1',
    'akdownloads.com',
    'www.akdownloads.com',
    'pay.pesapal.com',
    'www.pay.pesapal.com'
];

$refererHost = parse_url($referer, PHP_URL_HOST) ?? '';
$isAllowed = false;

foreach ($allowedHosts as $host) {
    if (strpos(strtolower($refererHost), $host) !== false || strpos(strtolower($_SERVER['HTTP_HOST'] ?? ''), $host) !== false) {
        $isAllowed = true;
        break;
    }
}

// Additional check: require OrderTrackingId for any status updates
$hasTrackingId = !empty($_GET['OrderTrackingId']) || !empty($_GET['orderTrackingId']) || !empty($_GET['order_tracking_id']);

if (!$isAllowed && !$hasTrackingId) {
    http_response_code(403);
    error_log("Unauthorized callback access from: $remoteIP, Referer: $referer, UA: $userAgent");
    echo 'Access denied';
    exit;
}

$orderTrackingId = $_GET['OrderTrackingId']
    ?? $_GET['orderTrackingId']
    ?? $_GET['order_tracking_id']
    ?? null;
$merchantReference = $_GET['OrderMerchantReference']
    ?? $_GET['merchant_reference']
    ?? $_GET['reference']
    ?? null;

if (function_exists('pesapal_log')) {
    pesapal_log('Callback received', [
        'OrderTrackingId'=>$orderTrackingId,
        'OrderMerchantReference'=>$merchantReference,
        'Referer'=>$referer,
        'UserAgent'=>$userAgent,
        'RemoteIP'=>$remoteIP,
        'Allowed'=>$isAllowed ? 'yes' : 'no'
    ]);
}

$statusText = 'Pending';
$details = [];
$localStatus = 'pending';
$order_id = null;
$orderRow = null;
if ($merchantReference && strpos($merchantReference, 'ORD') === 0) {
    $order_id = (int)substr($merchantReference, 3);
}

try {
    if ($orderTrackingId) {
        $client = new PesapalClient();
        $token = $client->getToken();
        $resp = $client->getTransactionStatus($token, $orderTrackingId);
        $details = $resp;
        $statusText = $resp['payment_status_description']
            ?? $resp['status']
            ?? ($resp['payment_status'] ?? 'PENDING');
        $localStatus = pesapal_map_status($statusText);
        if (!is_string($localStatus) || $localStatus === '') { $localStatus = (string)$statusText; }
        $localStatus = strtolower($localStatus);
        if (function_exists('pesapal_log')) { pesapal_log('Callback status', ['pesapalStatus'=>$statusText,'localStatus'=>$localStatus]); }

        if ($order_id === null && !empty($resp['merchant_reference']) && strpos($resp['merchant_reference'], 'ORD') === 0) {
            $order_id = (int)substr($resp['merchant_reference'], 3);
        }

        if ($order_id) {
            // Security: Verify this order actually has a payment record before allowing status updates
            $paymentCheck = $pdo->prepare("SELECT COUNT(*) as payment_count FROM payments WHERE order_id = ?");
            $paymentCheck->execute([$order_id]);
            $paymentExists = $paymentCheck->fetch(PDO::FETCH_ASSOC)['payment_count'] > 0;

            if (!$paymentExists) {
                error_log("Callback attempt for order $order_id with no payment record - possible attack");
                http_response_code(403);
                echo 'Invalid order';
                exit;
            }

            // Only allow status updates for orders that are currently pending or failed
            $currentStatusCheck = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
            $currentStatusCheck->execute([$order_id]);
            $currentStatus = $currentStatusCheck->fetch(PDO::FETCH_ASSOC)['status'] ?? '';

            if (!in_array(strtolower($currentStatus), ['pending', 'failed', 'cancelled'])) {
                error_log("Callback attempt to change order $order_id from status '$currentStatus' - not allowed");
                // Still show the status page but don't update
                $localStatus = $currentStatus;
            } else {
                try { $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?")->execute([$localStatus, $order_id]); } catch (Exception $e) { $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$localStatus, $order_id]); }
            }

            $payment_method = $resp['payment_method'] ?? null;
            $payment_account = $resp['payment_account'] ?? null;
            $currency = $resp['currency'] ?? null;
            $status_code = isset($resp['status_code']) ? (int)$resp['status_code'] : null;
            $confirmation_code = $resp['confirmation_code'] ?? null;
            $gateway_message = $resp['description'] ?? ($resp['message'] ?? null);
            $created_date = $resp['created_date'] ?? null;
            $isCard = is_string($payment_method ?? null) && preg_match('/visa|mastercard|amex|card/i', (string)$payment_method);
            if ($localStatus === 'completed' && empty($confirmation_code) && $isCard) {
                $localStatus = 'pending';
            }
            $paid_at = null;
            if ($created_date && in_array($localStatus, ['completed','reversed','failed','cancelled'])) {
                $paid_at = date('Y-m-d H:i:s', strtotime($created_date));
            }
            $gateway_raw = json_encode($resp);

            $sql = "UPDATE payments SET status=?, order_tracking_id=?, confirmation_code=?, payment_method=?, payment_account=?, currency=?, status_code=?, gateway_message=?, gateway_raw=?, paid_at=?, updated_at=NOW() WHERE order_id=?";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    $localStatus,
                    $orderTrackingId,
                    $confirmation_code,
                    $payment_method,
                    $payment_account,
                    $currency,
                    $status_code,
                    $gateway_message,
                    $gateway_raw,
                    $paid_at,
                    $order_id
                ]);
                if (function_exists('pesapal_log')) { pesapal_log('Callback payments updated', ['order_id'=>$order_id,'status'=>$localStatus]); }
            } catch (Exception $e) {
                if (function_exists('pesapal_log')) { pesapal_log('Callback payments update fallback', ['error'=>$e->getMessage()]); }
                $pdo->prepare("UPDATE payments SET status=?, updated_at=NOW() WHERE order_id=?")->execute([$localStatus, $order_id]);
            }
            // Load order + course for display/decisions
            try {
              $os = $pdo->prepare("SELECT o.id, o.user_id, o.course_id, o.status, o.created_at, p.title AS course_name, p.price AS amount, u.email AS buyer_email FROM orders o JOIN courses p ON o.course_id=p.id JOIN users u ON u.id=o.user_id WHERE o.id=? LIMIT 1");
              $os->execute([(int)$order_id]);
              $orderRow = $os->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Exception $e) { $orderRow = null; }
        }
    }
} catch (Exception $e) {
    $statusText = 'Error';
}

// Decide redirect target but delay so we can show a readable summary first
$redirectTarget = '';
$callbackToken = '';

if (in_array($localStatus, ['completed'])) {
    // Generate a one-time callback token to prevent refresh exploits
    $callbackToken = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE orders SET callback_token = ?, callback_used_at = NULL WHERE id = ?")
         ->execute([$callbackToken, $order_id]);
    $redirectTarget = 'downloads.php?ref=callback&token=' . $callbackToken;
} elseif (in_array($localStatus, ['failed','reversed','cancelled','rejected','invalid','error','unknown','pending'])) {
    $redirectTarget = 'orders.php' . ($order_id ? ('?highlight=' . (int)$order_id) : '');
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AKDOWNLOADS Payment Status</title>
  <style>
    :root { --bg:#0b1020; --card:#121a2f; --border:#1f2a44; --text:#e8edf3; --muted:#b7c1d6; --primary:#3b82f6; --success:#16a34a; --danger:#dc2626; --warn:#f59e0b; }
    body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:16px;background:var(--bg);color:var(--text)}
    .card{max-width:980px;margin:0 auto;background:var(--card);border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.3);overflow:hidden}
    .header{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--border)}
    .brand{display:flex;align-items:center;gap:12px}
    .brand img{width:28px;height:28px;border-radius:6px;object-fit:contain}
    .title{margin:0;font-size:16px}
    .content{padding:18px}
    .badge{display:inline-block;padding:6px 10px;border-radius:20px;font-size:12px}
    .pending{background:#fffbcc;color:#1f2a44}
    .completed{background:#d4f8e8;color:#065f46}
    .failed{background:#fde2e1;color:#7f1d1d}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:12px 0}
    .item{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:10px}
    .label{font-size:12px;color:var(--muted);margin-bottom:6px}
    .value{font-size:14px}
    .actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none}
    .btn-primary{background:var(--primary);color:#fff}
    .btn-outline{background:transparent;color:var(--text);border:1px solid var(--border)}
    .btn-success{background:var(--success);color:#fff}
    .btn-danger{background:var(--danger);color:#fff}
    .hint{font-size:12px;color:var(--muted);margin-top:6px}
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <div class="brand">
        <img src="assets/images/ak.png" alt="AKDOWNLOADS Logo">
        <div class="title"><strong>AKDOWNLOADS Payment Status</strong> — <span class="badge <?= htmlspecialchars($localStatus) ?>"><?= htmlspecialchars(strtoupper($localStatus)) ?></span></div>
      </div>
    </div>
    <div class="content">
      <?php
        $pm = $details['payment_method'] ?? '';
        $cc = $details['confirmation_code'] ?? '';
        $created = $details['created_date'] ?? '';
        $otrack = $details['order_tracking_id'] ?? ($details['OrderTrackingId'] ?? '');
        $currency = $details['currency'] ?? ($orderRow['currency'] ?? '');
        $amount = $orderRow['amount'] ?? ($details['amount'] ?? '');
        $errObj = is_array($details['error'] ?? null) ? $details['error'] : null;
        $errMsg = $errObj['message'] ?? ($details['description'] ?? ($details['message'] ?? ''));
      ?>
      <div class="grid">
        <div class="item"><div class="label">Reference</div><div class="value"><?= htmlspecialchars($merchantReference ?? 'N/A') ?></div></div>
        <?php if ($order_id): ?><div class="item"><div class="label">Order ID</div><div class="value">#<?= (int)$order_id ?></div></div><?php endif; ?>
        <div class="item"><div class="label">Status</div><div class="value"><?= htmlspecialchars(strtoupper($localStatus)) ?></div></div>
        <?php if ($orderRow): ?>
          <div class="item"><div class="label">course</div><div class="value"><?= htmlspecialchars((string)$orderRow['course_name']) ?></div></div>
          <div class="item"><div class="label">Amount</div><div class="value"><?= htmlspecialchars((string)$currency) ?> <?= htmlspecialchars((string)$amount) ?></div></div>
          <div class="item"><div class="label">Buyer</div><div class="value"><?= htmlspecialchars((string)($orderRow['buyer_email'] ?? '')) ?></div></div>
        <?php endif; ?>
        <?php if ($pm): ?><div class="item"><div class="label">Payment Method</div><div class="value"><?= htmlspecialchars((string)$pm) ?></div></div><?php endif; ?>
        <?php if ($cc): ?><div class="item"><div class="label">Confirmation Code</div><div class="value"><?= htmlspecialchars((string)$cc) ?></div></div><?php endif; ?>
        <?php if ($created): ?><div class="item"><div class="label">Created</div><div class="value"><?= htmlspecialchars((string)$created) ?></div></div><?php endif; ?>
        <?php if ($otrack): ?><div class="item"><div class="label">Tracking ID</div><div class="value" style="font-family:monospace;"><?= htmlspecialchars((string)$otrack) ?></div></div><?php endif; ?>
        <?php if ($errMsg && strtolower($localStatus) !== 'completed'): ?>
          <div class="item"><div class="label">Gateway Note</div><div class="value"><?= htmlspecialchars((string)$errMsg) ?></div></div>
        <?php endif; ?>
      </div>
      <div class="actions">
        <?php if ($localStatus === 'completed'): ?>
          <a class="btn btn-success" href="downloads.php?ref=callback&token=<?= htmlspecialchars($callbackToken) ?>">My Downloads</a>
        <?php else: ?>
          <?php if ($order_id): ?><a class="btn btn-danger" href="pay_pesapal.php?order_id=<?= (int)$order_id ?>">Retry Payment</a><?php endif; ?>
          <a class="btn btn-outline" href="orders.php<?= $order_id ? ('?highlight='.(int)$order_id) : '' ?>">View Orders</a>
        <?php endif; ?>
        <a class="btn btn-primary" href="user_index.php">Go to Dashboard</a>
      </div>
      <?php if ($redirectTarget): ?>
        <div class="hint">You will be redirected shortly. If not, use the buttons above.</div>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($redirectTarget): ?>
  <script>
    setTimeout(function(){ window.location.href = '<?= addslashes($redirectTarget) ?>'; }, 3000);
  </script>
  <?php endif; ?>
</body>
</html>
