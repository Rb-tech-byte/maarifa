<?php
session_start();
require_once __DIR__ . '/config.php';

// Optional redirect target (relative only)
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
// Avoid PHP 8-only str_starts_with by using a regex for http/https prefix
if ($redirect !== '' && preg_match('/^https?:\/\//i', $redirect)) {
  $redirect = '';
}

// Compute callback URL (honor APP_BASE_URL if set; otherwise include current subdirectory)
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
$scheme = $https ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
$scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
$scriptDir = str_replace('\\', '/', $scriptDir);
$scriptDir = rtrim($scriptDir, '/');
$isLocalHost = in_array($host, ['localhost', '127.0.0.1']) || preg_match('/^localhost(\:\d+)?$/', $host);
$appBase = (defined('APP_BASE_URL') && APP_BASE_URL !== '') ? APP_BASE_URL : '';
// If running on live (not localhost) but APP_BASE_URL points to localhost, ignore it
if (!$isLocalHost && $appBase !== '' && preg_match('/^https?:\/\/localhost/i', $appBase)) {
  $appBase = '';
}
$base = ($appBase !== '') ? rtrim($appBase, '/') : ($scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir));
$callback = $base . '/google_callback.php';

// Persist the exact redirect_uri to ensure the callback uses the same value
$_SESSION['google_oauth_redirect_uri'] = $callback;

$clientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
if ($clientId === '') {
  http_response_code(500);
  echo 'Google OAuth is not configured (missing client ID).';
  exit;
}

// CSRF state param
$state = base64_encode(json_encode([
  'csrf' => bin2hex(random_bytes(16)),
  'redirect' => $redirect,
]));
$_SESSION['google_oauth_state'] = $state;

// Build Google OAuth URL
$params = [
  'client_id' => $clientId,
  'redirect_uri' => $callback,
  'response_type' => 'code',
  'scope' => 'openid email profile',
  'access_type' => 'online',
  'include_granted_scopes' => 'true',
  'state' => $state,
  'prompt' => 'select_account',
];
$authorizeUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $authorizeUrl);
exit;
