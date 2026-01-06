<?php
function ak23_get_sms_settings(): array {
  static $cache = null;
  if ($cache !== null) return $cache;
  $d = ['twilio_sid'=>'','twilio_token'=>'','twilio_from'=>'','sms_api_url'=>'','twilio_sms_template'=>''];
  try {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) UNIQUE, setting_value TEXT)");
    $q = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('twilio_sid','twilio_token','twilio_from','sms_api_url','twilio_sms_template')");
    $rows = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rows as $r) { $d[$r['setting_key']] = (string)$r['setting_value']; }
  } catch (Throwable $e) {}
  $cache = $d;
  return $d;
}
function ak23_sms_build_body(string $template, array $vars): string {
  $body = $template;
  foreach ($vars as $k=>$v) { $body = str_replace('{{'.trim((string)$k).'}}', (string)$v, $body); }
  return $body;
}
function ak23_sms_send(string $to, string $message = '', array $vars = []): bool {
  $cfg = ak23_get_sms_settings();
  $sid = $cfg['twilio_sid'] ?? '';
  $token = $cfg['twilio_token'] ?? '';
  $from = $cfg['twilio_from'] ?? '';
  $url = $cfg['sms_api_url'] ?? '';
  $tpl = $cfg['twilio_sms_template'] ?? '';
  if ($message === '' && $tpl !== '') { $message = ak23_sms_build_body($tpl, $vars); }
  if (function_exists('cleanPhoneNumber')) { $to = cleanPhoneNumber($to); $from = $from !== '' ? cleanPhoneNumber($from) : $from; }
  if ($url === '' && $sid !== '') { $url = 'https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/Messages.json'; }
  if ($sid === '' || $token === '' || $from === '' || $url === '' || $to === '' || $message === '') { return false; }
  $fields = http_build_query(['To'=>$to,'From'=>$from,'Body'=>$message]);
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, $sid.':'.$token);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  if (defined('DISABLE_SSL_VERIFICATION') && DISABLE_SSL_VERIFICATION) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  } else {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    if (defined('CA_BUNDLE_FILE') && CA_BUNDLE_FILE && file_exists(CA_BUNDLE_FILE)) {
      curl_setopt($ch, CURLOPT_CAINFO, CA_BUNDLE_FILE);
    }
  }
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($err) { if (function_exists('logError')) { logError('SMS send error', ['error'=>$err]); } return false; }
  if ($code >= 200 && $code < 300) { return true; }
  if (function_exists('logError')) { logError('SMS send failed', ['http_code'=>$code,'response'=>$resp]); }
  return false;
}
