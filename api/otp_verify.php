<?php
header('Content-Type: application/json');
try {
  require_once __DIR__ . '/../includes/db_config.php';
  require_once __DIR__ . '/../includes/functions.php';
  require_once __DIR__ . '/../includes/otp.php';

  $phone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
  $code = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
  if ($phone === '' || $code === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'phone and code are required']);
    exit;
  }
  if (function_exists('cleanPhoneNumber')) { $phone = cleanPhoneNumber($phone); }

  $valid = ak23_otp_verify($phone, $code);
  if (!$valid) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_or_expired']);
    exit;
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
