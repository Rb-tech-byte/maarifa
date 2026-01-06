<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pesapal_helper.php';

header('Content-Type: application/json');

$orderTrackingId = $_GET['orderTrackingId'] ?? $_GET['order_tracking_id'] ?? null;
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

try {
    // Resolve tracking ID from order_id if needed
    if (!$orderTrackingId && $orderId) {
        $stmt = $pdo->prepare('SELECT p.order_tracking_id, p.status, o.status AS order_status FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.order_id=?');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (!empty($row['order_tracking_id'])) $orderTrackingId = $row['order_tracking_id'];
            $currentLocal = $row['status'] ?? $row['order_status'] ?? 'pending';
            if (!$orderTrackingId) {
                echo json_encode(['ok' => true, 'local_status' => $currentLocal, 'note' => 'no tracking id yet']);
                exit;
            }
        }
    }

    if (!$orderTrackingId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'orderTrackingId required']);
        exit;
    }

    $client = new PesapalClient();
    $token = $client->getToken();
    $resp = $client->getTransactionStatus($token, $orderTrackingId);

    $pesapalStatus = $resp['payment_status_description'] ?? $resp['status'] ?? ($resp['payment_status'] ?? 'PENDING');
    $localStatus = pesapal_map_status($pesapalStatus);

    // Try derive order_id from merchant_reference
    $order_id = $orderId;
    if ($order_id === null && !empty($resp['merchant_reference']) && strpos($resp['merchant_reference'], 'ORD') === 0) {
        $order_id = (int)substr($resp['merchant_reference'], 3);
    }

    if ($order_id) {
        try { $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?")->execute([$localStatus, $order_id]); } catch (Exception $e) { $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$localStatus, $order_id]); }

        $payment_method = $resp['payment_method'] ?? null;
        $payment_account = $resp['payment_account'] ?? null;
        $currency = $resp['currency'] ?? null;
        $status_code = isset($resp['status_code']) ? (int)$resp['status_code'] : null;
        $confirmation_code = $resp['confirmation_code'] ?? null;
        $gateway_message = $resp['description'] ?? ($resp['message'] ?? null);
        $created_date = $resp['created_date'] ?? null;
        $paid_at = null;
        if ($created_date && in_array($localStatus, ['completed','reversed','failed','cancelled'])) {
            $paid_at = date('Y-m-d H:i:s', strtotime($created_date));
        }
        $gateway_raw = json_encode($resp);

        $sql = "UPDATE payments SET status=?, order_tracking_id=?, confirmation_code=?, payment_method=?, payment_account=?, currency=?, status_code=?, gateway_message=?, gateway_raw=?, paid_at=?, updated_at=NOW() WHERE order_id=?";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$localStatus, $orderTrackingId, $confirmation_code, $payment_method, $payment_account, $currency, $status_code, $gateway_message, $gateway_raw, $paid_at, $order_id]);
        } catch (Exception $e) {
            $pdo->prepare("UPDATE payments SET status=?, updated_at=NOW() WHERE order_id=?")->execute([$localStatus, $order_id]);
        }
    }

    echo json_encode(['ok' => true, 'local_status' => $localStatus, 'gateway' => $resp]);
} catch (Exception $e) {
    if (function_exists('pesapal_log')) pesapal_log('status_api error', ['error'=>$e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
