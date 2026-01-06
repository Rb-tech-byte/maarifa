<?php
// Pre-header reference extraction and validation
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/debug_callback.log';

// Extract reference from callback data
$reference = null;
$reference_fields = [
    'reference', 'reference_number', 'transaction_id', 'order_id', 'ref', 
    'merchant_reference', 'payment_reference', 'order_reference'
];
foreach ($reference_fields as $field) {
    if (isset($_POST[$field]) && !empty($_POST[$field])) {
        $reference = $_POST[$field];
        break;
    }
    if (isset($_GET[$field]) && !empty($_GET[$field])) {
        $reference = $_GET[$field];
        break;
    }
}
if (!$reference) {
    file_put_contents($log_file, "ERROR: No reference found in callback data\n", FILE_APPEND);
    http_response_code(400);
    echo "ERROR: No reference found";
    exit;
}

$title = 'Debug Callback';
require_once '../includes/admin_header.php';
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// Continue with the rest of the script...

if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/debug_callback.log';

// Log everything for debugging
file_put_contents($log_file, "\n\n=== DEBUG CALLBACK START [" . date('Y-m-d H:i:s') . "] ===\n", FILE_APPEND);
file_put_contents($log_file, "REQUEST METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents($log_file, "POST DATA: " . json_encode($_POST, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
file_put_contents($log_file, "GET DATA: " . json_encode($_GET, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
file_put_contents($log_file, "RAW INPUT: " . file_get_contents('php://input') . "\n", FILE_APPEND);

// Extract reference from various possible sources
$reference = null;
$status = null;
$transaction_id = null;

// Try to get reference from different possible field names
$reference_fields = [
    'reference', 'reference_number', 'transaction_id', 'order_id', 'ref', 
    'merchant_reference', 'payment_reference', 'order_reference'
];

foreach ($reference_fields as $field) {
    if (isset($_POST[$field]) && !empty($_POST[$field])) {
        $reference = $_POST[$field];
        file_put_contents($log_file, "Found reference in POST['$field']: $reference\n", FILE_APPEND);
        break;
    }
    if (isset($_GET[$field]) && !empty($_GET[$field])) {
        $reference = $_GET[$field];
        file_put_contents($log_file, "Found reference in GET['$field']: $reference\n", FILE_APPEND);
        break;
    }
}

// Try to get status from different possible field names
$status_fields = [
    'status', 'payment_status', 'transaction_status', 'result', 'response_code', 
    'code', 'payment_result', 'transaction_result'
];

foreach ($status_fields as $field) {
    if (isset($_POST[$field]) && !empty($_POST[$field])) {
        $status = $_POST[$field];
        file_put_contents($log_file, "Found status in POST['$field']: $status\n", FILE_APPEND);
        break;
    }
    if (isset($_GET[$field]) && !empty($_GET[$field])) {
        $status = $_GET[$field];
        file_put_contents($log_file, "Found status in GET['$field']: $status\n", FILE_APPEND);
        break;
    }
}

// Try to get transaction ID
$transaction_fields = [
    'transaction_id', 'txn_id', 'payment_id', 
    'payment_transaction_id', 'gateway_transaction_id'
];

foreach ($transaction_fields as $field) {
    if (isset($_POST[$field]) && !empty($_POST[$field])) {
        $transaction_id = $_POST[$field];
        file_put_contents($log_file, "Found transaction_id in POST['$field']: $transaction_id\n", FILE_APPEND);
        break;
    }
    if (isset($_GET[$field]) && !empty($_GET[$field])) {
        $transaction_id = $_GET[$field];
        file_put_contents($log_file, "Found transaction_id in GET['$field']: $transaction_id\n", FILE_APPEND);
        break;
    }
}

file_put_contents($log_file, "EXTRACTED DATA:\n", FILE_APPEND);
file_put_contents($log_file, "- Reference: " . ($reference ?: 'NOT FOUND') . "\n", FILE_APPEND);
file_put_contents($log_file, "- Status: " . ($status ?: 'NOT FOUND') . "\n", FILE_APPEND);
file_put_contents($log_file, "- Transaction ID: " . ($transaction_id ?: 'NOT FOUND') . "\n", FILE_APPEND);

if (!$reference) {
    file_put_contents($log_file, "ERROR: No reference found in callback data\n", FILE_APPEND);
    http_response_code(400);
    echo "ERROR: No reference found";
    exit;
}

// Check if payment exists
$payment = getPaymentByReference($reference);
$order = getOrderByReference($reference);

file_put_contents($log_file, "DATABASE LOOKUP:\n", FILE_APPEND);
file_put_contents($log_file, "- Payment found: " . ($payment ? 'YES' : 'NO') . "\n", FILE_APPEND);
file_put_contents($log_file, "- Order found: " . ($order ? 'YES' : 'NO') . "\n", FILE_APPEND);

if ($payment) {
    file_put_contents($log_file, "- Payment ID: " . $payment['id'] . "\n", FILE_APPEND);
    file_put_contents($log_file, "- Current Payment Status: " . $payment['status'] . "\n", FILE_APPEND);
    file_put_contents($log_file, "- Payment Amount: " . $payment['amount'] . "\n", FILE_APPEND);
    file_put_contents($log_file, "- Payment User ID: " . $payment['user_id'] . "\n", FILE_APPEND);
}

if ($order) {
    file_put_contents($log_file, "- Order ID: " . $order['id'] . "\n", FILE_APPEND);
    file_put_contents($log_file, "- Current Order Status: " . $order['status'] . "\n", FILE_APPEND);
    file_put_contents($log_file, "- Order Amount: " . $order['amount'] . "\n", FILE_APPEND);
}

if (!$payment || !$order) {
    file_put_contents($log_file, "ERROR: Payment or order not found for reference: $reference\n", FILE_APPEND);
    http_response_code(404);
    echo "ERROR: Payment or order not found";
    exit;
}

// Determine the status to set based on the received status
$payment_status = 'pending';
$order_status = 'pending';

// Enhanced status mapping
if ($status) {
    $status_lower = strtolower($status);
?>
        </div>
    </div>
</main>
<?php require_once '../includes/admin_footer.php'; ?>
<?php

    file_put_contents($log_file, "PROCESSING STATUS: '$status' (lowercase: '$status_lower')\n", FILE_APPEND);
    
    if (in_array($status_lower, ['success', 'paid', 'completed', 'approved', '200', 'successful'])) {
        $payment_status = 'completed';
        $order_status = 'completed';
        file_put_contents($log_file, "MAPPED TO: completed\n", FILE_APPEND);
    } elseif (in_array($status_lower, ['failed', 'cancelled', 'declined', 'error'])) {
        $payment_status = 'failed';
        $order_status = 'failed';
        file_put_contents($log_file, "MAPPED TO: failed\n", FILE_APPEND);
    } elseif (in_array($status_lower, ['rejected', 'reject'])) {
        $payment_status = 'reject';
        $order_status = 'failed';
        file_put_contents($log_file, "MAPPED TO: reject\n", FILE_APPEND);
    } else {
        $payment_status = 'pending';
        $order_status = 'pending';
        file_put_contents($log_file, "MAPPED TO: pending (unknown status)\n", FILE_APPEND);
    }
} else {
    // If no explicit status, check for success indicators in the entire request
    $all_data = json_encode($_POST) . json_encode($_GET);
    file_put_contents($log_file, "No explicit status found, analyzing all data for indicators\n", FILE_APPEND);
    
    if (stripos($all_data, 'success') !== false || stripos($all_data, 'paid') !== false || 
        stripos($all_data, 'completed') !== false || stripos($all_data, 'approved') !== false) {
        $payment_status = 'completed';
        $order_status = 'completed';
        file_put_contents($log_file, "Found success indicators, mapping to: completed\n", FILE_APPEND);
    } elseif (stripos($all_data, 'failed') !== false || stripos($all_data, 'cancelled') !== false || 
              stripos($all_data, 'declined') !== false || stripos($all_data, 'error') !== false) {
        $payment_status = 'failed';
        $order_status = 'failed';
        file_put_contents($log_file, "Found failure indicators, mapping to: failed\n", FILE_APPEND);
    } else {
        $payment_status = 'pending';
        $order_status = 'pending';
        file_put_contents($log_file, "No clear indicators found, defaulting to: pending\n", FILE_APPEND);
    }
}

file_put_contents($log_file, "FINAL STATUS MAPPING:\n", FILE_APPEND);
file_put_contents($log_file, "- Payment Status: $payment_status\n", FILE_APPEND);
file_put_contents($log_file, "- Order Status: $order_status\n", FILE_APPEND);

// Update payment and order status
try {
    $payment_updated = updatePaymentStatus($payment['id'], $payment_status);
    $order_updated = updateOrderStatus($order['id'], $order_status);
    
    file_put_contents($log_file, "UPDATE RESULTS:\n", FILE_APPEND);
    file_put_contents($log_file, "- Payment Update Success: " . ($payment_updated ? 'YES' : 'NO') . "\n", FILE_APPEND);
    file_put_contents($log_file, "- Order Update Success: " . ($order_updated ? 'YES' : 'NO') . "\n", FILE_APPEND);
    
    // Update transaction ID if provided (generic)
    if ($transaction_id && $payment_updated) {
        try {
            $stmt = $pdo->prepare("UPDATE payments SET transaction_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$transaction_id, $payment['id']]);
            file_put_contents($log_file, "- Transaction ID Updated: YES\n", FILE_APPEND);
        } catch (Exception $ex) {
            file_put_contents($log_file, "- Transaction ID update skipped (column missing)\n", FILE_APPEND);
        }
    }
    
    // Verify the updates
    $updated_payment = getPaymentByReference($reference);
    $updated_order = getOrderByReference($reference);
    
    file_put_contents($log_file, "VERIFICATION:\n", FILE_APPEND);
    file_put_contents($log_file, "- Updated Payment Status: " . $updated_payment['status'] . "\n", FILE_APPEND);
    file_put_contents($log_file, "- Updated Order Status: " . $updated_order['status'] . "\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents($log_file, "ERROR during update: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($log_file, "ERROR TRACE: " . $e->getTraceAsString() . "\n", FILE_APPEND);
}

file_put_contents($log_file, "=== DEBUG CALLBACK END ===\n", FILE_APPEND);

// Return success response
http_response_code(200);
echo "OK";
?> 