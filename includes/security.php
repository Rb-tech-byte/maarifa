<?php
// Global security bootstrap. Include this as early as possible before any output.
// Loaded via includes/base.php.

// Hide PHP version header
@ini_set('expose_php', '0');

// Strengthen session cookie flags
if (PHP_SAPI !== 'cli') {
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
  $params = session_get_cookie_params();
  $cookieParams = [
    'lifetime' => $params['lifetime'] ?? 0,
    'path' => $params['path'] ?? '/',
    'domain' => $params['domain'] ?? '',
    'secure' => $isHttps ? true : false,
    'httponly' => true,
    'samesite' => 'Lax', // Strict may break some OAuth flows
  ];
  if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
    @session_set_cookie_params($cookieParams);
  }
}

// Validate image upload (MIME + size). Returns [ok, ext] via array or false on failure.
if (!function_exists('ak23_validate_image_upload')) {
  function ak23_validate_image_upload(array $f, int $maxBytes = 2097152, bool $allowSvg = false, ?string &$error = null) {
    $error = null;
    if (!isset($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) { $error = 'Invalid upload.'; return false; }
    if (!empty($f['error']) && (int)$f['error'] !== UPLOAD_ERR_OK) { $error = 'Upload error.'; return false; }
    $size = (int)($f['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) { $error = 'File too large or empty.'; return false; }
    $name = (string)($f['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowedExt = $allowSvg ? ['png','jpg','jpeg','gif','webp','svg'] : ['png','jpg','jpeg','gif','webp'];
    if (!in_array($ext, $allowedExt, true)) { $error = 'Invalid file type.'; return false; }
    $fi = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $fi ? @finfo_file($fi, $f['tmp_name']) : null;
    if ($fi) { @finfo_close($fi); }
    $allowedMime = [
      'png' => 'image/png',
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'gif' => 'image/gif',
      'webp' => 'image/webp',
      'svg' => 'image/svg+xml'
    ];
    if (!$mime || !in_array($mime, $allowSvg ? array_values($allowedMime) : array_values(array_diff_key($allowedMime, ['svg'=>true])), true)) {
      $error = 'Invalid file content.'; return false;
    }
    if ($ext === 'svg' && !$allowSvg) { $error = 'SVG not allowed.'; return false; }
    return ['ok' => true, 'ext' => $ext, 'mime' => $mime];
  }
}

// Start session if not started (safe here; many pages already call session_start)
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

// Security headers (avoid CSP here to not break external CDNs; keep CSP fine-tuning in header.php)
if (!headers_sent()) {
  // Prevent MIME sniffing
  header('X-Content-Type-Options: nosniff');
  // Clickjacking protection
  header('X-Frame-Options: SAMEORIGIN');
  // Strict referrer
  header('Referrer-Policy: strict-origin-when-cross-origin');
  // Basic Permissions Policy (tight but not breaking typical features)
  header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), fullscreen=(self)");
  // HSTS only if HTTPS
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
  if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
  }
}

// CSRF utilities
if (!function_exists('ak23_csrf_token')) {
  function ak23_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
  }
}
if (!function_exists('ak23_csrf_input')) {
  function ak23_csrf_input(): string {
    $t = ak23_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
  }
}
if (!function_exists('ak23_csrf_validate')) {
  function ak23_csrf_validate(): bool {
    $sent = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    $valid = isset($_SESSION['csrf_token']) && is_string($sent) && hash_equals($_SESSION['csrf_token'], (string)$sent);
    return $valid;
  }
}

// Basic rate-limit helper (per IP + key) using APCu/file cache
if (!function_exists('ak23_rate_limit')) {
  function ak23_rate_limit(string $key, int $max, int $perSeconds): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $k = 'rl_' . sha1($ip . '|' . $key);
    // APCu branch
    if (function_exists('apcu_inc')) {
      $v = apcu_fetch($k, $ok);
      if (!$ok) { apcu_store($k, 1, $perSeconds); return true; }
      if ($v >= $max) { return false; }
      apcu_inc($k); return true;
    }
    // File fallback
    $f = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $k . '.txt';
    $now = time(); $cnt = 0; $exp = $now + $perSeconds;
    if (is_file($f)) {
      $data = @json_decode((string)@file_get_contents($f), true) ?: [];
      $cnt = (int)($data['cnt'] ?? 0); $until = (int)($data['exp'] ?? 0);
      if ($until < $now) { $cnt = 0; $exp = $now + $perSeconds; } else { $exp = $until; }
    }
    if ($cnt >= $max) return false;
    $cnt++;
    @file_put_contents($f, json_encode(['cnt'=>$cnt,'exp'=>$exp]));
    return true;
  }
}
