<?php
// Create OTP and send via SMS
header('Content-Type: application/json');
try {
  require_once __DIR__ . '/../includes/db_config.php';
  require_once __DIR__ . '/../includes/functions.php';
  require_once __DIR__ . '/../includes/sms_provider.php';
  require_once __DIR__ . '/../includes/otp.php';

  $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
  $phone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
  $ttl = isset($_POST['ttl']) ? max(60, (int)$_POST['ttl']) : 300;
  $digits = isset($_POST['digits']) ? max(4, min(8, (int)$_POST['digits'])) : 6;

  if ($phone === '' && $user_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'phone or user_id is required']);
    exit;
  }

  // Normalize phone for rate checks
  if (function_exists('cleanPhoneNumber') && $phone !== '') { $phone = cleanPhoneNumber($phone); }

  // Rate limit: max 2 OTPs per 5 minutes, max 5 per hour, per phone
  try {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS c5,
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS c60
      FROM otps WHERE phone = ?");
    $stmt->execute([$phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['c5'=>0,'c60'=>0];
    $c5 = (int)$row['c5'];
    $c60 = (int)$row['c60'];
    if ($c5 >= 2 || $c60 >= 5) {
      http_response_code(429);
      echo json_encode(['ok'=>false,'error'=>'rate_limited']);
      exit;
    }
  } catch (Throwable $e) { /* ignore, allow */ }

  $otp = ak23_otp_create_for_user($user_id, $phone, $ttl, $digits);
  $to = $otp['phone'];
  $msg = 'Your verification code is ' . $otp['code'] . '. It expires in ' . (int)round($ttl/60) . ' minutes.';
  $sent = ak23_sms_send($to, $msg, ['code'=>$otp['code']]);

  if (!$sent) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Failed to send SMS']);
    exit;
  }

  echo json_encode(['ok'=>true,'phone'=>$to]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
