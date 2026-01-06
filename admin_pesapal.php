<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pesapal_helper.php';

// Simple token-based protection. Call with ?token=... matching ADMIN_ACTION_TOKEN
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (!defined('ADMIN_ACTION_TOKEN') || !$token || $token !== ADMIN_ACTION_TOKEN) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$action = $_POST['action'] ?? '';
$message = '';
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client = new PesapalClient();
        $bearer = $client->getToken();
        if ($action === 'refund') {
            $confirmationCode = trim((string)($_POST['confirmation_code'] ?? ''));
            $amount = (float)($_POST['amount'] ?? 0);
            $username = trim((string)($_POST['username'] ?? 'admin'));
            $remarks = trim((string)($_POST['remarks'] ?? 'Admin refund'));
            if (!$confirmationCode || $amount <= 0) throw new Exception('Confirmation code and positive amount are required.');
            $result = $client->requestRefund($bearer, $confirmationCode, $amount, $username, $remarks);
            $message = 'Refund request sent';
            if (function_exists('pesapal_log')) pesapal_log('Admin refund', ['confirmation_code'=>$confirmationCode,'amount'=>$amount]);
        } elseif ($action === 'cancel') {
            $orderTrackingId = trim((string)($_POST['order_tracking_id'] ?? ''));
            if (!$orderTrackingId) throw new Exception('order_tracking_id is required.');
            $result = $client->cancelOrder($bearer, $orderTrackingId);
            $message = 'Cancel request sent';
            if (function_exists('pesapal_log')) pesapal_log('Admin cancel', ['order_tracking_id'=>$orderTrackingId]);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pesapal Admin</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:24px;background:#0b1020;color:#e8edf3}
    .wrap{max-width:960px;margin:0 auto}
    .card{background:#121a2f;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.3);margin-bottom:20px}
    .card h2{margin:0;padding:14px 18px;border-bottom:1px solid #1f2a44}
    .card .content{padding:18px}
    input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid #334155;background:#0b1020;color:#e8edf3}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .row .col{min-width:0}
    .btn{background:#3b82f6;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
    .alert{padding:10px 14px;border-radius:8px;margin-bottom:10px}
    .ok{background:#0d9488}
    .err{background:#b91c1c}
    pre{white-space:pre-wrap;word-break:break-word;background:#0b1020;color:#b7c1d6;padding:12px;border-radius:8px}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Pesapal Admin Actions</h1>

    <?php if ($message): ?>
      <div class="alert ok"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert err">Error: <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
      <h2>Refund Request</h2>
      <div class="content">
        <form method="post">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
          <input type="hidden" name="action" value="refund" />
          <div class="row">
            <div class="col">
              <label>Confirmation Code</label>
              <input type="text" name="confirmation_code" required />
            </div>
            <div class="col">
              <label>Amount</label>
              <input type="number" name="amount" step="0.01" required />
            </div>
          </div>
          <div class="row">
            <div class="col">
              <label>Username</label>
              <input type="text" name="username" value="admin" />
            </div>
            <div class="col">
              <label>Remarks</label>
              <input type="text" name="remarks" value="Admin refund" />
            </div>
          </div>
          <div style="margin-top:12px"><button class="btn" type="submit">Submit Refund</button></div>
        </form>
      </div>
    </div>

    <div class="card">
      <h2>Cancel Order</h2>
      <div class="content">
        <form method="post">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
          <input type="hidden" name="action" value="cancel" />
          <label>Order Tracking ID</label>
          <input type="text" name="order_tracking_id" required />
          <div style="margin-top:12px"><button class="btn" type="submit">Cancel Order</button></div>
        </form>
      </div>
    </div>

    <?php if ($result): ?>
      <div class="card">
        <h2>Response</h2>
        <div class="content">
          <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
