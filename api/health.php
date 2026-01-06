<?php
// Health check for callback and IPN endpoints
// Returns JSON with reachability and configuration info

header('Content-Type: application/json');

$results = [
    'status' => 'ok',
    'env' => null,
    'base' => null,
    'callback_url' => null,
    'ipn_url' => null,
    'checks' => []
];

try {
    require_once __DIR__ . '/../ak23pay/config.php';
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'error' => 'Failed to load config: ' . $e->getMessage()
    ]);
    exit;
}

$env = (defined('AK23_ENV') ? AK23_ENV : 'unknown');
$results['env'] = $env;

// Base URLs
$base = function_exists('ak23_get_base_url') ? ak23_get_base_url() : null;
$callback_base = function_exists('ak23_get_callback_base_url') ? ak23_get_callback_base_url() : $base;
$ipn_url = defined('PESAPAL_IPN_URL') ? PESAPAL_IPN_URL : null;

$results['base'] = $base;
$results['callback_url'] = rtrim((string)$callback_base, '/') . '/redirect.php';
$results['ipn_url'] = $ipn_url;

function http_check($url, $method = 'HEAD') {
    $out = [ 'url' => $url, 'ok' => false, 'http_code' => null, 'error' => null, 'dns_ms' => null, 'ssl' => null ];
    if (!$url) { $out['error'] = 'empty-url'; return $out; }
    if (!function_exists('curl_init')) { $out['error'] = 'curl-not-installed'; return $out; }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    if (strtoupper($method) === 'HEAD') {
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
    }
    $resp = curl_exec($ch);
    if ($resp === false) {
        $out['error'] = curl_error($ch);
    }
    $out['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $out['dns_ms'] = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME) * 1000.0;
    $cert = curl_getinfo($ch, CURLINFO_CERTINFO);
    if ($cert && is_array($cert)) {
        $out['ssl'] = ['certinfo' => 'available'];
    } else {
        $out['ssl'] = ['certinfo' => 'unavailable'];
    }
    curl_close($ch);
    // Consider 2xx and 3xx as OK for reachability
    if ($out['http_code'] >= 200 && $out['http_code'] < 400) { $out['ok'] = true; }
    return $out;
}

// Perform checks (callback is browser redirect endpoint – should be reachable)
$results['checks']['callback_head'] = http_check($results['callback_url'], 'HEAD');

// IPN may reject GET/HEAD but we can still test TLS/host reachability with HEAD
if ($ipn_url) {
    $head = http_check($ipn_url, 'HEAD');
    if (!$head['ok']) {
        // Try a safe GET fallback (without side-effects) to improve signal
        $get = http_check($ipn_url, 'GET');
        $results['checks']['ipn_head'] = $head;
        $results['checks']['ipn_get'] = $get;
    } else {
        $results['checks']['ipn_head'] = $head;
    }
}

// Overall status
foreach ($results['checks'] as $c) {
    if (!$c['ok']) { $results['status'] = 'warning'; break; }
}

echo json_encode($results, JSON_PRETTY_PRINT);
