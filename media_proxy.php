<?php
// media_proxy.php
// Simple streaming proxy for external audio/video to mitigate CORS/hotlink issues.
// Usage: /media_proxy.php?url=<encoded absolute URL>

// SECURITY: allowlist hosts
$ALLOWED_HOSTS = [
  'producerloops.com', 'www.producerloops.com',
  'cdn.producerloops.com',
  'soundcloud.com', 'w.soundcloud.com',
  'cdn.soundcloud.com',
  // add more if needed
];

$url = isset($_GET['url']) ? trim($_GET['url']) : '';
if ($url === '' || !preg_match('#^https?://#i', $url)) {
  http_response_code(400);
  header('Content-Type: text/plain');
  echo 'Bad request';
  exit;
}

$parts = @parse_url($url);
$host = $parts && !empty($parts['host']) ? strtolower($parts['host']) : '';
$allowed = false;
foreach ($ALLOWED_HOSTS as $h) {
  if ($host === $h || (substr($host, -strlen('.'.$h)) === '.'.$h)) { $allowed = true; break; }
}
if (!$allowed) {
  http_response_code(403);
  header('Content-Type: text/plain');
  echo 'Forbidden';
  exit;
}

// Proxy fetch via cURL
// Disable output buffering to reduce stalls for streaming
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }

$ch = curl_init($url);
// Forward range header for seeking, if present
$headers = [];
if (isset($_SERVER['HTTP_RANGE'])) {
  $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}
// Some sources require a User-Agent and Accept
$headers[] = 'Accept: */*';
$headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36';
// Forward a plausible Referer and Origin matching the upstream host
if (!empty($host)) {
  $scheme = (!empty($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http','https'], true)) ? $parts['scheme'] : 'https';
  $origin = $scheme . '://' . $host;
  $headers[] = 'Referer: ' . $origin . '/';
  $headers[] = 'Origin: ' . $origin;
}

// Honor HEAD requests (headers only)
$isHead = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';

curl_setopt_array($ch, [
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_RETURNTRANSFER => false, // stream directly to temp
  CURLOPT_HEADER => true,          // we need headers to forward
  CURLOPT_HTTPHEADER => $headers,
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_SSL_VERIFYHOST => 2,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_NOBODY => $isHead,
]);

// Open output buffer to parse headers
$fp = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_FILE, $fp);

$ok = curl_exec($ch);
if ($ok === false) {
  $errno = curl_errno($ch);
  // Retry once without SSL verification on common SSL errors in dev/local envs
  if (in_array($errno, [60, 77], true)) { // CURLE_SSL_CACERT, CURLE_SSL_CACERT_BADFILE
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $ok = curl_exec($ch);
  }
  if ($ok === false) {
    http_response_code(502);
    header('Content-Type: text/plain');
    echo 'Upstream error';
    exit;
  }
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
rewind($fp);
$raw = stream_get_contents($fp);
fclose($fp);

$respHeaders = substr($raw, 0, $headerSize);
$respBody    = substr($raw, $headerSize);

// Parse headers and forward safe ones
$lines = preg_split("/\r?\n/", $respHeaders);
$sentContentType = false;
foreach ($lines as $line) {
  if (stripos($line, 'Content-Type:') === 0) {
    header($line);
    $sentContentType = true;
  } elseif (stripos($line, 'Content-Length:') === 0) {
    header($line);
  } elseif (stripos($line, 'Accept-Ranges:') === 0) {
    header($line);
  } elseif (stripos($line, 'Content-Range:') === 0) {
    header($line);
  } elseif (stripos($line, 'Cache-Control:') === 0 || stripos($line, 'Expires:') === 0 || stripos($line, 'Last-Modified:') === 0) {
    header($line);
  }
}

// Default content-type for common audio if upstream didn't send
if (!$sentContentType) {
  $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
  if ($ext === 'mp3') header('Content-Type: audio/mpeg');
  elseif ($ext === 'wav') header('Content-Type: audio/wav');
  elseif ($ext === 'ogg' || $ext === 'oga') header('Content-Type: audio/ogg');
  elseif ($ext === 'm4a' || $ext === 'aac') header('Content-Type: audio/aac');
}

// CORS to allow playback
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Range, Accept, User-Agent, Origin, Referer');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');

http_response_code($httpCode >= 200 && $httpCode <= 206 ? $httpCode : 200);
if ($isHead) {
  // No body on HEAD
} else {
  echo $respBody;
  if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
}

curl_close($ch);
