<?php
// OTP helper functions
function ak23_otp_table_init(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS otps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    phone VARCHAR(32) NOT NULL,
    code VARCHAR(12) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY user_id (user_id),
    KEY phone (phone),
    KEY expires_at (expires_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ak23_otp_generate_code(int $digits = 6): string {
  $digits = max(4, min(8, $digits));
  $min = (int)pow(10, $digits-1);
  $max = (int)pow(10, $digits)-1;
  return (string)random_int($min, $max);
}

function ak23_otp_create_for_user(int $user_id = 0, string $phone = '', int $ttl_seconds = 300, int $digits = 6): array {
  global $pdo;
  ak23_otp_table_init($pdo);
  if (function_exists('cleanPhoneNumber') && $phone !== '') { $phone = cleanPhoneNumber($phone); }
  if ($phone === '' && $user_id > 0 && function_exists('getUserById')) {
    $u = getUserById($user_id); $phone = (string)($u['phone'] ?? '');
    if (function_exists('cleanPhoneNumber') && $phone !== '') { $phone = cleanPhoneNumber($phone); }
  }
  if ($phone === '') { throw new RuntimeException('Phone is required for OTP'); }
  $code = ak23_otp_generate_code($digits);
  $expires = date('Y-m-d H:i:s', time() + max(60, $ttl_seconds));
  // Optionally invalidate previous unused codes for same phone
  try { $pdo->prepare("UPDATE otps SET used_at=NOW() WHERE phone=? AND used_at IS NULL")->execute([$phone]); } catch (Throwable $e) {}
  $stmt = $pdo->prepare("INSERT INTO otps (user_id, phone, code, expires_at) VALUES (?,?,?,?)");
  $stmt->execute([$user_id ?: null, $phone, $code, $expires]);
  return ['phone'=>$phone,'code'=>$code,'expires_at'=>$expires];
}

function ak23_otp_verify(string $phone, string $code): bool {
  global $pdo;
  ak23_otp_table_init($pdo);
  if (function_exists('cleanPhoneNumber')) { $phone = cleanPhoneNumber($phone); }
  $stmt = $pdo->prepare("SELECT id, expires_at, used_at FROM otps WHERE phone=? AND code=? ORDER BY id DESC LIMIT 1");
  $stmt->execute([$phone, $code]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) return false;
  if (!empty($row['used_at'])) return false;
  if (strtotime($row['expires_at']) < time()) return false;
  $pdo->prepare("UPDATE otps SET used_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
  return true;
}

function ak23_otp_cleanup(int $maxAgeDays = 7): void {
  global $pdo;
  ak23_otp_table_init($pdo);
  $pdo->prepare("DELETE FROM otps WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$maxAgeDays]);
}
