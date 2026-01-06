<?php
function ak23_get_smtp_config(): array {
  static $cache = null;
  if ($cache !== null) return $cache;
  $cfg = [
    'host' => defined('SMTP_HOST') ? SMTP_HOST : 'localhost',
    'port' => defined('SMTP_PORT') ? (int)SMTP_PORT : 25,
    'secure' => defined('SMTP_SECURE') ? strtolower((string)SMTP_SECURE) : '',
    'username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
    'password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
    'from_email' => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@localhost',
    'from_name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'AK23 Studio Kits',
    'debug' => defined('SMTP_DEBUG') ? (int)SMTP_DEBUG : 0,
    'disable_ssl_verification' => defined('DISABLE_SSL_VERIFICATION') ? (bool)DISABLE_SSL_VERIFICATION : false,
  ];
  try {
    global $pdo;
    $q = $pdo->query("SELECT `key`, `value` FROM smtp_settings");
    $rows = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
    if ($rows) {
      $kv = [];
      foreach ($rows as $r) { $kv[$r['key']] = $r['value']; }
      $cfg['host'] = $kv['host'] ?? $cfg['host'];
      $cfg['port'] = isset($kv['port']) ? (int)$kv['port'] : $cfg['port'];
      $cfg['secure'] = isset($kv['secure']) ? strtolower((string)$kv['secure']) : $cfg['secure'];
      $cfg['username'] = $kv['username'] ?? $cfg['username'];
      $cfg['password'] = $kv['password'] ?? $cfg['password'];
      $cfg['from_email'] = $kv['from_email'] ?? $cfg['from_email'];
      $cfg['from_name'] = $kv['from_name'] ?? $cfg['from_name'];
      $cfg['debug'] = isset($kv['debug']) ? (int)$kv['debug'] : $cfg['debug'];
      $cfg['disable_ssl_verification'] = isset($kv['disable_ssl_verification']) ? (bool)$kv['disable_ssl_verification'] : $cfg['disable_ssl_verification'];
    }
  } catch (Throwable $e) {}
  $cache = $cfg;
  return $cfg;
}

function ak23_configure_mailer($mail, array $cfg): void {
  $mail->isSMTP();
  $mail->Host = $cfg['host'] ?: 'localhost';
  $mail->Port = $cfg['port'] ?: 25;
  $secure = $cfg['secure'] ?? '';
  if ($secure === 'ssl') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; }
  elseif ($secure === 'tls') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
  $mail->SMTPAuth = true;
  if (!empty($cfg['username'])) $mail->Username = $cfg['username'];
  if (!empty($cfg['password'])) $mail->Password = $cfg['password'];
  $mail->CharSet = 'UTF-8';
  if ($secure === 'ssl') { $mail->SMTPAutoTLS = false; }
  $mail->SMTPDebug = (int)($cfg['debug'] ?? 0);
  $mail->Debugoutput = function ($str, $level) { if (function_exists('logError')) { logError('PHPMailer debug', ['level' => $level, 'message' => $str]); } };
  if (!empty($cfg['disable_ssl_verification'])) {
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
  }
  $fromEmail = $cfg['from_email'] ?: 'no-reply@localhost';
  $fromName = $cfg['from_name'] ?: 'AK23 Studio Kits';
  $mail->setFrom($fromEmail, $fromName);
}
