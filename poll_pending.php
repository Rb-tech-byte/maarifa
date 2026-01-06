<?php
// CLI/HTTP worker that polls pending/invalid payments and updates their status.
// Recommended to run every 1-2 minutes via Task Scheduler or a background service.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pesapal_helper.php';

header('Content-Type: text/plain');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$limit = $limit > 0 && $limit <= 500 ? $limit : 50;

try {
    $client = new PesapalClient();
    $token = $client->getToken();

    // Fetch payments that are not in terminal state and have a tracking id
    $sql = "SELECT p.order_id, p.order_tracking_id
            FROM payments p
            WHERE p.status IN ('pending')
              AND p.order_tracking_id IS NOT NULL AND p.order_tracking_id <> ''
            ORDER BY p.updated_at ASC
            LIMIT $limit";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($rows as $row) {
        $orderId = (int)$row['order_id'];
        $tracking = $row['order_tracking_id'];
        try {
            $resp = $client->getTransactionStatus($token, $tracking);
            $pesapalStatus = $resp['payment_status_description'] ?? $resp['status'] ?? ($resp['payment_status'] ?? 'PENDING');
            $localStatus = pesapal_map_status($pesapalStatus);

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

            $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?")->execute([$localStatus, $orderId]);
            $stmt = $pdo->prepare("UPDATE payments SET status=?, confirmation_code=?, payment_method=?, payment_account=?, currency=?, status_code=?, gateway_message=?, gateway_raw=?, paid_at=?, updated_at=NOW() WHERE order_id=?");
            $stmt->execute([$localStatus, $confirmation_code, $payment_method, $payment_account, $currency, $status_code, $gateway_message, $gateway_raw, $paid_at, $orderId]);

            $count++;
            if (function_exists('pesapal_log')) pesapal_log('worker poll update', ['order_id'=>$orderId,'status'=>$localStatus]);
        } catch (Exception $e) {
            if (function_exists('pesapal_log')) pesapal_log('worker poll error', ['order_id'=>$orderId,'error'=>$e->getMessage()]);
        }
    }
    echo "Polled and updated $count payments\n";
} catch (Exception $e) {
    if (function_exists('pesapal_log')) pesapal_log('worker init error', ['error'=>$e->getMessage()]);
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
