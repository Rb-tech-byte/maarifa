<?php
// SMTP configuration loader for PHPMailer.
// Reads settings saved via admin/mails_smtp.php (table: settings) and falls back to defaults.
// This file returns an associative array.

require_once __DIR__ . '/db_config.php';

$defaults = [
  'driver'      => 'smtp',             // smtp | mail
  'host'        => 'smtp.yourhost.com',
  'port'        => 587,
  'username'    => 'no-reply@akdownloads.com',
  'password'    => 'REPLACE_WITH_STRONG_PASSWORD',
  'encryption'  => 'tls',              // tls | ssl | ''
  'from_email'  => 'no-reply@akdownloads.com',
  'from_name'   => 'AK23 Downloads',
  'reply_to'    => 'support@akdownloads.com',
];

// Ensure settings table exists (no-op if present)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {
  // ignore; will use defaults
}

$cfg = $defaults;
try {
  // Fetch smtp_* and optional from_* keys if present
  $keys = [
    'smtp_host','smtp_user','smtp_pass','smtp_port','smtp_secure',
    'from_email','from_name','reply_to'
  ];
  $in  = implode(',', array_fill(0, count($keys), '?'));
  $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($in)");
  $stmt->execute($keys);
  $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

  if ($rows) {
    if (!empty($rows['smtp_host']))  { $cfg['host'] = $rows['smtp_host']; }
    if (!empty($rows['smtp_user']))  { $cfg['username'] = $rows['smtp_user']; }
    if (isset($rows['smtp_pass']))   { $cfg['password'] = $rows['smtp_pass']; }
    if (!empty($rows['smtp_port']))  { $cfg['port'] = (int)$rows['smtp_port']; }
    if (isset($rows['smtp_secure'])) { $cfg['encryption'] = strtolower(trim((string)$rows['smtp_secure'])); }
    if (!empty($rows['from_email'])) { $cfg['from_email'] = $rows['from_email']; }
    if (!empty($rows['from_name']))  { $cfg['from_name']  = $rows['from_name']; }
    if (!empty($rows['reply_to']))   { $cfg['reply_to']   = $rows['reply_to']; }
  }
} catch (Throwable $e) {
  // use defaults silently
}

return $cfg;
