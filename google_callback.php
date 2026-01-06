<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/functions.php';

// Helper: parse state safely
function parse_state($raw) {
  $dec = base64_decode((string)$raw, true);
  if ($dec === false) return ['csrf' => '', 'redirect' => ''];
  $obj = json_decode($dec, true);
  if (!is_array($obj)) return ['csrf' => '', 'redirect' => ''];
  return [
    'csrf' => isset($obj['csrf']) ? (string)$obj['csrf'] : '',
    'redirect' => isset($obj['redirect']) ? (string)$obj['redirect'] : '',
  ];
}

$stateRaw = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if ($error !== '') {
  http_response_code(400);
  echo 'Google authorization error: ' . htmlspecialchars($error);
  exit;
}

if ($code === '') {
  http_response_code(400);
  echo 'Missing authorization code.';
  exit;
}

list('csrf' => $csrf, 'redirect' => $redirect) = parse_state($stateRaw);
if (!isset($_SESSION['google_oauth_state']) || $_SESSION['google_oauth_state'] !== $stateRaw) {
  http_response_code(400);
  echo 'Invalid OAuth state.';
  exit;
}
// Only allow relative redirect
// Use regex to detect absolute URLs (http/https) for PHP 7 compatibility
if ($redirect !== '' && preg_match('/^https?:\/\//i', $redirect)) {
  $redirect = '';
}

// Build callback to match what we used earlier
if (!empty($_SESSION['google_oauth_redirect_uri'])) {
  // Use the exact same redirect_uri that initiated the flow
  $callback = (string)$_SESSION['google_oauth_redirect_uri'];
} else {
  // Fallback: compute environment-aware callback (honor APP_BASE_URL and subdirectory)
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
  $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
  $scriptDir = str_replace('\\', '/', $scriptDir);
  $scriptDir = rtrim($scriptDir, '/');
  $base = (defined('APP_BASE_URL') && APP_BASE_URL !== '')
    ? rtrim(APP_BASE_URL, '/')
    : ($scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir));
  $callback = $base . '/google_callback.php';
}

$clientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$clientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
if ($clientId === '' || $clientSecret === '') {
  http_response_code(500);
  echo 'Google OAuth is not configured (missing client credentials).';
  exit;
}

// Exchange code for tokens
$tokenUrl = 'https://oauth2.googleapis.com/token';
$post = [
  'code' => $code,
  'client_id' => $clientId,
  'client_secret' => $clientSecret,
  'redirect_uri' => $callback,
  'grant_type' => 'authorization_code',
];
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
// Use CA bundle if configured
if (defined('CA_BUNDLE_FILE') && is_file(CA_BUNDLE_FILE)) {
  curl_setopt($ch, CURLOPT_CAINFO, CA_BUNDLE_FILE);
}
// Disable SSL verification for localhost development
if (defined('DISABLE_SSL_VERIFICATION') && DISABLE_SSL_VERIFICATION) {
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}
$tokenResp = curl_exec($ch);
if ($tokenResp === false) {
  logError('Google token exchange failed', ['curl_error' => curl_error($ch)]);
  http_response_code(502);
  echo 'Failed to exchange token.';
  exit;
}
$codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$tok = json_decode($tokenResp, true);
if ($codeHttp >= 400 || !is_array($tok)) {
  logError('Google token exchange bad response', ['http' => $codeHttp, 'body' => $tokenResp]);
  http_response_code(502);
  $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
  $isLocal = ($host === 'localhost' || $host === '127.0.0.1');
  if ($isLocal) {
    echo 'Invalid token response (HTTP ' . (int)$codeHttp . '): ' . htmlspecialchars((string)$tokenResp);
  } else {
    echo 'Invalid token response.';
  }
  exit;
}
$accessToken = $tok['access_token'] ?? '';
$idToken = $tok['id_token'] ?? '';
if ($accessToken === '' && $idToken === '') {
  http_response_code(502);
  echo 'No token received.';
  exit;
}

// Fetch user info
$userinfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init($userinfoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Use CA bundle if configured
if (defined('CA_BUNDLE_FILE') && is_file(CA_BUNDLE_FILE)) {
  curl_setopt($ch, CURLOPT_CAINFO, CA_BUNDLE_FILE);
}
// Disable SSL verification for localhost development
if (defined('DISABLE_SSL_VERIFICATION') && DISABLE_SSL_VERIFICATION) {
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}
$uiResp = curl_exec($ch);
if ($uiResp === false) {
  logError('Google userinfo failed', ['curl_error' => curl_error($ch)]);
  http_response_code(502);
  echo 'Failed to retrieve profile.';
  exit;
}
$uiHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$userinfo = json_decode($uiResp, true);
if ($uiHttp >= 400 || !is_array($userinfo)) {
  logError('Google userinfo bad response', ['http' => $uiHttp, 'body' => $uiResp]);
  http_response_code(502);
  echo 'Invalid profile response.';
}

$email = (string)($userinfo['email'] ?? '');
if ($email !== '') { $email = function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email); }
$name = (string)($userinfo['name'] ?? ($userinfo['given_name'] ?? 'Google User'));
  // Capture phone number from Google OAuth
  $phone = (string)($userinfo['phoneNumber'] ?? '');
  if (empty($phone)) {
    $phone = (string)($userinfo['phone'] ?? '');
  }
  // Clean phone number (remove spaces, dashes, parentheses)
  $phone = preg_replace('/[^0-9+]/', '', $phone);
  
  if ($email === '') {
    http_response_code(400);
    echo 'Google account did not return an email address.';
    exit;
  }

  // Do NOT require phone for Google logins; save if available
  // Normalize phone if present
  if (!empty($phone) && function_exists('cleanPhoneNumber')) {
    $phone = cleanPhoneNumber($phone);
    $valid = validatePhoneNumber($phone);
    if ($valid !== true) {
      // If Google returned an invalid format, just drop it (user can add later)
      $phone = '';
    }
  }

// Ensure user exists and mark verified
try {
  // Ensure columns exist (idempotent)
  $hasVerified = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->fetch();
  if (!$hasVerified) { $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0"); }
  $hasToken = $pdo->query("SHOW COLUMNS FROM users LIKE 'verification_token'")->fetch();
  if (!$hasToken) { $pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) NULL"); }
  $hasExp = $pdo->query("SHOW COLUMNS FROM users LIKE 'verification_expires'")->fetch();
  if (!$hasExp) { $pdo->exec("ALTER TABLE users ADD COLUMN verification_expires DATETIME NULL"); }

  $sel = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
  $sel->execute([$email]);
  $user = $sel->fetch(PDO::FETCH_ASSOC) ?: null;
  if ($user) {
    $uid = (int)$user['id'];
    // Check if user needs phone number update
    if (empty($user['phone']) && !empty($phone)) {
      $pdo->prepare('UPDATE users SET phone = ?, is_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?')->execute([$phone, $uid]);
    } elseif ((int)($user['is_verified'] ?? 0) !== 1) {
      $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?')->execute([$uid]);
    }
  } else {
    // Create user with phone number (required for new system)
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (name, email, phone, password, is_verified, created_at) VALUES (?, ?, ?, ?, 1, NOW())');
    $ins->execute([$name, $email, $phone, $hash]);
    $uid = (int)$pdo->lastInsertId();
  }

  // Log in
  $_SESSION['user_logged_in'] = true;
  $_SESSION['role'] = 'user';
  $_SESSION['user_id'] = $uid;
  $_SESSION['username'] = $name;
  $_SESSION['auth_provider'] = 'google';

  header('Location: ' . ($redirect !== '' ? $redirect : 'user_dashboard.php'));
  exit;
} catch (Throwable $e) {
  logError('Google login failed', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo 'Failed to sign in with Google.';
  exit;
}
