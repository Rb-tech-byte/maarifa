<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pesapal_helper.php';

// Remove X-Frame-Options header to allow Pesapal iframe (CSP frame-src handles security)
if (!headers_sent()) {
    header_remove('X-Frame-Options');
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$isIntent = isset($_GET['intent']) && $_GET['intent'] == '1';

// Handle payment intent
if ($isIntent) {
    if (!isset($_SESSION['payment_intent'])) {
        error_log("Payment intent not found in session. Session data: " . json_encode($_SESSION));
        http_response_code(400);
        echo 'Payment session expired or not found. Please try purchasing again from the course page.';
        exit;
    }

    $intent = $_SESSION['payment_intent'];

    // Validate intent hasn't expired (24 hours)
    if (time() - $intent['created_at'] > 86400) {
        unset($_SESSION['payment_intent']);
        http_response_code(400);
        echo 'Payment session expired. Please try again.';
        exit;
    }

    // Validate that user is logged in and matches the intent
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_id'] != $intent['user_id']) {
        unset($_SESSION['payment_intent']);
        header('Location: login.php');
        exit;
    }

    // Create the order and payment records now
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, course_id, status, created_at, callback_token, callback_used_at) VALUES (?, ?, 'pending', NOW(), NULL, NULL)");
    $stmt->execute([$intent['user_id'], $intent['course_id']]);
    $order_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO payments (order_id, amount, status, payment_method, created_at) VALUES (?, ?, 'pending', 'pesapal', NOW())");
    $stmt->execute([$order_id, $intent['amount']]);

    // Clear the intent since we've used it
    unset($_SESSION['payment_intent']);

    // Redirect to the same page with the order_id to avoid confusion
    header('Location: pay_pesapal.php?order_id=' . $order_id);
    exit;
}

if ($order_id <= 0) {
    http_response_code(400);
    echo 'Invalid order id';
    exit;
}

// Validate that the user owns this order
$stmt = $pdo->prepare("SELECT o.user_id FROM orders o WHERE o.id = ?");
$stmt->execute([$order_id]);
$orderOwner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orderOwner || $orderOwner['user_id'] != ($_SESSION['user_id'] ?? 0)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Load order, course and buyer email for billing details
$stmt = $pdo->prepare("SELECT o.*, p.title AS course_name, p.price, u.email AS buyer_email, u.name AS buyer_name
                       FROM orders o
                       JOIN courses p ON o.course_id = p.id
                       JOIN users u ON o.user_id = u.id
                       WHERE o.id = ?");
$stmt->execute([$order_id]);
$orderData = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orderData) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

$amount = (float)$orderData['price'];
$merchantReference = 'ORD' . $order_id;

try {
    $client = new PesapalClient();
    $token = $client->getToken();

    // If you already have a PESAPAL_NOTIFICATION_ID constant set, use it; otherwise find or create by URL
    if (defined('PESAPAL_NOTIFICATION_ID') && PESAPAL_NOTIFICATION_ID) {
        $notificationId = PESAPAL_NOTIFICATION_ID;
    } else {
        $notificationId = $client->findOrCreateNotificationId($token, PESAPAL_IPN_URL);
    }

    $payload = [
        'language' => 'EN',
        // Per v3 docs, 'id' is the merchant reference. Use our local reference for easier reconciliation
        'id' => $merchantReference,
        'currency' => PESAPAL_CURRENCY,
        'amount' => $amount,
        'description' => 'Payment for ' . $orderData['course_name'],
        'callback_url' => PESAPAL_CALLBACK_URL,
        'notification_id' => $notificationId,
        'billing_address' => [
            'phone_number' => '',
            'email_address' => $orderData['buyer_email'],
            'country_code' => 'KE',
            'first_name' => $orderData['buyer_name'],
            'middle_name' => '',
            'last_name' => '',
            'line_1' => '',
            'line_2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'zip_code' => ''
        ]
    ];

    $submit = $client->submitOrder($token, $payload);
    $iframeUrl = $submit['redirect_url'];
    // Save tracking id for background polling and reconciliation
    if (!empty($submit['order_tracking_id'])) {
        try {
            $pdo->prepare("UPDATE payments SET order_tracking_id=?, updated_at=NOW() WHERE order_id=?")
                ->execute([$submit['order_tracking_id'], $order_id]);
        } catch (Exception $e) {
            // ignore silently; will still be updated via IPN/callback
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo 'Payment initialization error: ' . htmlspecialchars($e->getMessage());
    exit;
}

// Ensure X-Frame-Options is removed after all includes (in case security.php was loaded)
if (!headers_sent()) {
    header_remove('X-Frame-Options');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>AKDOWNLOADS Secure Payment</title>
    <style>
        :root { --bg:#0b1020; --card:#121a2f; --border:#1f2a44; --text:#e8edf3; --muted:#b7c1d6; --brand:#ffcc00; --primary:#3b82f6; }
        body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:16px;background:var(--bg);color:var(--text)}
        .card{max-width:980px;margin:0 auto;background:var(--card);border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.3);overflow:hidden}
        .header{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--border)}
        .brand{display:flex;align-items:center;gap:12px}
        .brand img{width:28px;height:28px;border-radius:6px;object-fit:contain}
        .title{margin:0;font-size:16px;letter-spacing:.3px}
        .subtitle{margin:2px 0 0 0;font-size:12px;color:var(--muted)}
        .content{padding:0}
        iframe{border:0;width:100%;height:70vh;max-height:78vh;background:#fff}
        .meta{padding:12px 18px;font-size:14px;color:var(--muted);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;border-top:1px solid var(--border)}
        .actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:12px 18px;border-top:1px solid var(--border);background:rgba(255,255,255,0.02)}
        .actions a{ text-decoration:none }
        .btn{appearance:none;border:0;border-radius:10px;padding:10px 14px;font-size:14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:600;line-height:1;box-shadow:0 1px 0 rgba(0,0,0,.2); transition:transform .08s ease, box-shadow .2s ease, opacity .2s ease;}
        .btn-outline{background:transparent;color:var(--text);border:1px solid var(--border)}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-success{background:#16a34a;color:#fff}
        .btn-warning{background:#f59e0b;color:#000}
        .btn:hover,.btn:focus{opacity:.95;text-decoration:none; box-shadow:0 2px 8px rgba(0,0,0,.25)}
        .btn:active{ transform: translateY(1px) }
        .btn:focus-visible{outline:2px solid var(--primary);outline-offset:2px}
        .actions .btn{flex:0 0 auto}
        @media (max-width: 600px){
          .actions{gap:8px}
          .actions .btn{flex:1 1 calc(50% - 8px); justify-content:center}
        }
        @media (max-width: 600px){
          body{padding:10px}
          .title{font-size:14px}
          iframe{height:65vh}
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
          <div class="brand">
            <img src="assets/images/ak.png" alt="AKDOWNLOADS Logo">
            <div>
              <div class="title"><strong>AKDOWNLOADS SECURE PAYMENT</strong></div>
              <div class="subtitle">Pay for <?= htmlspecialchars($orderData['course_name']) ?> • <?= PESAPAL_CURRENCY ?> <?= number_format($amount,2) ?></div>
            </div>
          </div>
        </div>
        <div class="content">
            <iframe 
                id="pesapalIframe"
                src="<?= htmlspecialchars($iframeUrl) ?>" 
                allowpaymentrequest
                allow="payment; fullscreen"
                sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"
                title="Pesapal Secure Payment"></iframe>
        </div>
        <div class="meta">
          <span>Reference: <?= htmlspecialchars($merchantReference) ?></span>
          <span>Do not close this window until payment completes.</span>
        </div>
        <div class="actions">
          <button class="btn btn-outline" id="btnBack" type="button" aria-label="Back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 19l-7-7 7-7v4h8v6h-8v4z"/></svg>
            <span>Back</span>
          </button>
          <a class="btn btn-primary" id="btnStatus" href="orders.php?highlight=<?= (int)$order_id ?>" aria-label="Check Status">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 7v5l4 2"/></svg>
            <span>Check Status</span>
          </a>
          <a class="btn btn-outline" href="user_index.php" aria-label="Go to Dashboard">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 13l9-9 9 9-2 0v7h-5v-5h-4v5H5v-7H3z"/></svg>
            <span>Go to Dashboard</span>
          </a>
          <a class="btn btn-success" href="downloads.php" aria-label="My Downloads">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3v10.17l3.59-3.58L17 11l-5 5-5-5 1.41-1.41L11 13.17V3h1zM5 18h14v2H5z"/></svg>
            <span>My Downloads</span>
          </a>
          <a class="btn btn-warning" id="btnOpenHosted" href="<?= htmlspecialchars($iframeUrl) ?>" aria-label="Open Secure Payment Page">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 3h7v7h-2V6.41l-9.29 9.3-1.42-1.42 9.3-9.29H14V3z"/><path d="M5 5h6V3H3v8h2V5z"/></svg>
            <span>Open Secure Payment Page</span>
          </a>
        </div>
    </div>
    <script>
      (function(){
        // Suppress known non-critical console warnings from Pesapal iframe
        const originalError = console.error;
        const originalWarn = console.warn;
        const pesapalWarnings = [
          'Invalid \'X-Frame-Options\' header',
          'Missing required field for Hosted Fields',
          'Init completed slowly',
          'Base.SupportedPaymentsUtility',
          'Base.cors',
          'Base.Message.Init',
          'Base.Payment.ConsumerAuthentication',
          'Base.UiManager',
          'Base.Events.SetupHandler',
          'Base.Main'
        ];
        
        console.error = function(...args) {
          const message = args.join(' ');
          if (pesapalWarnings.some(w => message.includes(w))) {
            // Suppress known Pesapal/Cardinal Commerce informational messages
            return;
          }
          originalError.apply(console, args);
        };
        
        console.warn = function(...args) {
          const message = args.join(' ');
          if (pesapalWarnings.some(w => message.includes(w))) {
            // Suppress known Pesapal/Cardinal Commerce informational messages
            return;
          }
          originalWarn.apply(console, args);
        };
        
        const trackingId = '<?= isset($submit['order_tracking_id']) ? htmlspecialchars($submit['order_tracking_id']) : '' ?>';
        const merchantRef = '<?= htmlspecialchars($merchantReference) ?>';
        const orderId = '<?= (int)$order_id ?>';
        const ordersUrl = 'orders.php?highlight=' + encodeURIComponent(orderId);
        let navigating = false;
        // Only auto-redirect when completed; leave failures for manual user action
        const terminal = ['completed'];
        document.getElementById('btnBack').addEventListener('click', function(){
          navigating = true;
          if (history.length > 1) { history.back(); return; }
          window.location.href = ordersUrl;
        });
        // If user abandons the page, send them to orders to review status (best effort)
        const exitHandler = function(){ if (!navigating) { window.location.href = ordersUrl; } };
        window.addEventListener('pagehide', exitHandler);
        window.addEventListener('beforeunload', function(e){ if (!navigating) { /* hint only */ } });
        if (trackingId) {
          const check = async () => {
            try {
              const res = await fetch('status_api.php?orderTrackingId='+encodeURIComponent(trackingId), {cache:'no-store'});
              const data = await res.json();
              if (data && data.ok && data.local_status && terminal.includes(data.local_status)) {
                // Redirect to callback URL with expected params for consistency
                const cb = '<?= addslashes(PESAPAL_CALLBACK_URL) ?>';
                const url = cb + '?OrderTrackingId='+encodeURIComponent(trackingId)+'&OrderMerchantReference='+encodeURIComponent(merchantRef)+'&OrderNotificationType=CALLBACKURL';
                navigating = true;
                window.location.replace(url);
                return;
              }
            } catch (e) { /* ignore */ }
            setTimeout(check, 4000);
          };
          setTimeout(check, 4000);
        }
      })();
    </script>
</body>
</html>
