<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pesapal_helper.php';

// Support legacy GET IPN contract (2012 docs):
// pesapal_notification_type=CHANGE&pesapal_transaction_tracking_id=...&pesapal_merchant_reference=...
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['pesapal_notification_type'])) {
    $notifType = $_GET['pesapal_notification_type'] ?? '';
    $trackingId = $_GET['pesapal_transaction_tracking_id'] ?? '';
    $merchantRef = $_GET['pesapal_merchant_reference'] ?? '';

    // Attempt to query and update status even in GET mode
    try {
        if ($trackingId) {
            $client = new PesapalClient();
            $token = $client->getToken();
            $statusResp = $client->getTransactionStatus($token, $trackingId);
            $pesapalStatus = $statusResp['status'] ?? ($statusResp['payment_status'] ?? 'PENDING');
            $localStatus = pesapal_map_status($pesapalStatus);

            // Derive local order id from merchant ref (e.g., ORD123)
            if (!$merchantRef && !empty($statusResp['merchant_reference'])) {
                $merchantRef = $statusResp['merchant_reference'];
            }
            if ($merchantRef && strpos($merchantRef, 'ORD') === 0) {
                $oid = (int)substr($merchantRef, 3);
                if ($oid) {
                    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$localStatus, $oid]);
                    $pdo->prepare("UPDATE payments SET status=? WHERE order_id=?")->execute([$localStatus, $oid]);
                }
            }
        }
    } catch (Exception $e) {
        // swallow errors for IPN
    }

    // Echo back exactly as required
    header('Content-Type: text/plain');
    echo 'pesapal_notification_type=' . $notifType
        . '&pesapal_transaction_tracking_id=' . $trackingId
        . '&pesapal_merchant_reference=' . $merchantRef;
    exit;
}

// v3 JSON IPN
$raw = file_get_contents('php://input');
if (function_exists('pesapal_log')) { pesapal_log('IPN received', ['rawPreview' => strlen($raw)>800?substr($raw,0,800).'...':$raw]); }
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 500, 'message' => 'Invalid IPN payload']);
    exit;
}

// Pesapal v3 typically posts fields including orderTrackingId and merchant_reference
$orderTrackingId = $payload['orderTrackingId'] ?? $payload['order_tracking_id'] ?? null;
$merchantReference = $payload['merchant_reference'] ?? $payload['reference'] ?? null;
$notificationType = $payload['orderNotificationType'] ?? $payload['OrderNotificationType'] ?? 'IPNCHANGE';

// Derive local order_id from merchant reference if possible (format ORD<id>)
$order_id = null;
if ($merchantReference && strpos($merchantReference, 'ORD') === 0) {
    $order_id = (int)substr($merchantReference, 3);
}
if (function_exists('pesapal_log')) { pesapal_log('IPN parsed', ['orderTrackingId'=>$orderTrackingId,'merchantReference'=>$merchantReference,'order_id'=>$order_id]); }

// Query Pesapal for authoritative status
try {
    $client = new PesapalClient();
    $token = $client->getToken();
    if ($orderTrackingId) {
        $statusResp = $client->getTransactionStatus($token, $orderTrackingId);
        $pesapalStatus = $statusResp['payment_status_description']
            ?? $statusResp['status']
            ?? ($statusResp['payment_status'] ?? 'PENDING');
        $localStatus = pesapal_map_status($pesapalStatus);
        if (function_exists('pesapal_log')) { pesapal_log('Status fetched', ['pesapalStatus'=>$pesapalStatus,'localStatus'=>$localStatus]); }

        if ($order_id === null && !empty($statusResp['merchant_reference']) && strpos($statusResp['merchant_reference'], 'ORD') === 0) {
            $order_id = (int)substr($statusResp['merchant_reference'], 3);
        }

        // Fallback: if order_id not resolved, try by payments.order_tracking_id
        if (!$order_id && $orderTrackingId) {
            try {
                $pRow = $pdo->prepare("SELECT order_id FROM payments WHERE order_tracking_id = ? LIMIT 1");
                $pRow->execute([$orderTrackingId]);
                $oid = (int)($pRow->fetchColumn() ?: 0);
                if ($oid) { $order_id = $oid; }
            } catch (Exception $e) { /* ignore */ }
        }

        if ($order_id) {
            // Extract payment details first
            $payment_method = $statusResp['payment_method'] ?? null;
            $payment_account = $statusResp['payment_account'] ?? null;
            $currency = $statusResp['currency'] ?? null;
            $status_code = isset($statusResp['status_code']) ? (int)$statusResp['status_code'] : null;
            $confirmation_code = $statusResp['confirmation_code'] ?? null;
            $gateway_message = $statusResp['description'] ?? ($statusResp['message'] ?? null);
            $created_date = $statusResp['created_date'] ?? null;

            // Guard: require confirmation_code only for card payments
            $isCard = is_string($payment_method ?? null) && preg_match('/visa|mastercard|amex|card/i', (string)$payment_method);
            if ($localStatus === 'completed' && empty($confirmation_code) && $isCard) {
                $localStatus = 'pending';
            }

            $paid_at = null;
            if ($created_date && in_array($localStatus, ['completed','reversed','failed','cancelled'])) {
                $paid_at = date('Y-m-d H:i:s', strtotime($created_date));
            }
            $gateway_raw = json_encode($statusResp);

            // Security: ensure a payment row exists for this order
            $paymentRowCount = 0;
            try {
                $pc = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE order_id=?");
                $pc->execute([$order_id]);
                $paymentRowCount = (int)($pc->fetchColumn() ?: 0);
            } catch (Exception $e) { /* ignore */ }

            if ($paymentRowCount === 0) {
                if (function_exists('pesapal_log')) { pesapal_log('IPN blocked: no payment row for order', ['order_id'=>$order_id]); }
                // Do not update orders without a payments row
                $localStatus = 'pending';
            }

            // Restrict status transitions to safe states only
            try {
                $cs = $pdo->prepare("SELECT status FROM orders WHERE id=?");
                $cs->execute([$order_id]);
                $current = strtolower((string)($cs->fetchColumn() ?: ''));
            } catch (Exception $e) { $current = ''; }

            if (!in_array($current, ['pending','failed','cancelled','reversed','unknown',''])) {
                // Don't overwrite terminal success with anything else
                if (function_exists('pesapal_log')) { pesapal_log('IPN not updating terminal order', ['order_id'=>$order_id,'current'=>$current,'incoming'=>$localStatus]); }
            } else {
                // Update order status
                try { $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?")->execute([$localStatus, $order_id]); } catch (Exception $e) { if (function_exists('pesapal_log')) { pesapal_log('Orders update fallback', ['error'=>$e->getMessage()]); } $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$localStatus, $order_id]); }
            }

            // Update payments row with detailed gateway data
            // Primary update by order_id; if no row affected, try by order_tracking_id
            try {
                $stmt = $pdo->prepare("UPDATE payments SET status=?, order_tracking_id=?, confirmation_code=?, payment_method=?, payment_account=?, currency=?, status_code=?, gateway_message=?, gateway_raw=?, paid_at=?, updated_at=NOW() WHERE order_id=?");
                $stmt->execute([$localStatus,$orderTrackingId,$confirmation_code,$payment_method,$payment_account,$currency,$status_code,$gateway_message,$gateway_raw,$paid_at,$order_id]);
                if ($stmt->rowCount() === 0 && $orderTrackingId) {
                    $stmt2 = $pdo->prepare("UPDATE payments SET status=?, confirmation_code=?, payment_method=?, payment_account=?, currency=?, status_code=?, gateway_message=?, gateway_raw=?, paid_at=?, updated_at=NOW() WHERE order_tracking_id=?");
                    $stmt2->execute([$localStatus,$confirmation_code,$payment_method,$payment_account,$currency,$status_code,$gateway_message,$gateway_raw,$paid_at,$orderTrackingId]);
                }
                if (function_exists('pesapal_log')) { pesapal_log('Payments updated', ['order_id'=>$order_id,'status'=>$localStatus,'by'=>'order_id-or-tracking']); }
            } catch (Exception $e) {
                if (function_exists('pesapal_log')) { pesapal_log('Payments update fallback', ['error'=>$e->getMessage()]); }
                try {
                    $pdo->prepare("UPDATE payments SET status=?, updated_at=NOW() WHERE order_id=?")->execute([$localStatus, $order_id]);
                } catch (Exception $e2) {
                    if ($orderTrackingId) { $pdo->prepare("UPDATE payments SET status=?, updated_at=NOW() WHERE order_tracking_id=?")->execute([$localStatus, $orderTrackingId]); }
                }
            }
        }
    }
} catch (Exception $e) {
    if (function_exists('pesapal_log')) { pesapal_log('IPN error', ['error'=>$e->getMessage()]); }
}

// Respond with expected JSON acknowledgement per docs
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'orderNotificationType' => $notificationType,
    'orderTrackingId' => $orderTrackingId,
    'orderMerchantReference' => $merchantReference,
    'status' => 200
]);
