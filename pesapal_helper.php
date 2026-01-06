<?php
require_once __DIR__ . '/config.php';

class PesapalClient {
    private $baseUrl;
    private $consumerKey;
    private $consumerSecret;
    private $ipnType;

    public function __construct() {
        $this->baseUrl = rtrim(PESAPAL_BASE_URL, '/');
        $this->consumerKey = PESAPAL_CONSUMER_KEY;
        $this->consumerSecret = PESAPAL_CONSUMER_SECRET;
        // Allow switching between POST/GET; default POST as safer
        $this->ipnType = defined('PESAPAL_IPN_TYPE') ? PESAPAL_IPN_TYPE : 'POST';
    }

    private function httpRequest($method, $url, $headers = [], $body = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // SSL Verification - disable for local development if needed
        if (defined('DISABLE_SSL_VERIFICATION') && DISABLE_SSL_VERIFICATION) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        } else {
            // Enable SSL verification with CA bundle
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            // Prefer project-configured CA bundle if provided
            if (defined('CA_BUNDLE_FILE') && CA_BUNDLE_FILE && file_exists(CA_BUNDLE_FILE)) {
                curl_setopt($ch, CURLOPT_CAINFO, CA_BUNDLE_FILE);
            } else {
                // Fallback to common system paths
                if (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
                    curl_setopt($ch, CURLOPT_CAINFO, '/etc/ssl/certs/ca-certificates.crt');
                } elseif (file_exists('/etc/pki/tls/certs/ca-bundle.crt')) {
                    curl_setopt($ch, CURLOPT_CAINFO, '/etc/pki/tls/certs/ca-bundle.crt');
                }
            }
        }
        
        $defaultHeaders = ['Content-Type: application/json'];
        if (!empty($headers)) {
            $defaultHeaders = array_merge($defaultHeaders, $headers);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);
        
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        
        if ($response === false) {
            if (function_exists('pesapal_log')) { pesapal_log('HTTP request error', ['method'=>$method,'url'=>$url,'errno'=>$errno,'error'=>$error]); }
            throw new Exception('cURL error (' . $errno . '): ' . $error);
        }
        
        $data = json_decode($response, true);
        if (function_exists('pesapal_log')) { pesapal_log('HTTP request', ['method'=>$method,'url'=>$url,'code'=>$httpCode]); }
        return [$httpCode, $response, $data];
    }

    public function getToken() {
        // Try cache first
        $cacheFile = defined('PESAPAL_TOKEN_CACHE_FILE') ? PESAPAL_TOKEN_CACHE_FILE : null;
        if ($cacheFile && is_readable($cacheFile)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['token']) && !empty($cached['expires_at'])) {
                if (time() < (int)$cached['expires_at'] - 15) { // 15s safety margin
                    return $cached['token'];
                }
            }
        }

        $prefix = defined('PESAPAL_API_PREFIX') ? PESAPAL_API_PREFIX : '/v3';
        $url = $this->baseUrl . $prefix . '/api/Auth/RequestToken';
        [$code, $resp, $data] = $this->httpRequest('POST', $url, [], [
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
        ]);
        if ($code >= 200 && $code < 300 && isset($data['token'])) {
            // Cache token with expiry
            if ($cacheFile) {
                $dir = dirname($cacheFile);
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $expiresAt = null;
                if (!empty($data['expiryDate'])) {
                    $expiresAt = strtotime($data['expiryDate']); // UTC
                } else {
                    $expiresAt = time() + 4 * 60; // fallback 4 min if not provided
                }
                @file_put_contents($cacheFile, json_encode([
                    'token' => $data['token'],
                    'expires_at' => $expiresAt,
                    'fetched_at' => time()
                ]));
            }
            return $data['token'];
        }
        throw new Exception('Failed to get Pesapal token: HTTP ' . $code . ' ' . $resp);
    }

    public function getIpnList($token) {
        $prefix = defined('PESAPAL_API_PREFIX') ? PESAPAL_API_PREFIX : '/v3';
        $url = $this->baseUrl . $prefix . '/api/URLSetup/GetIpnList';
        [$code, $resp, $data] = $this->httpRequest('GET', $url, ["Authorization: Bearer {$token}"]);
        if ($code >= 200 && $code < 300 && is_array($data)) {
            return $data;
        }
        throw new Exception('Failed to get IPN list: HTTP ' . $code . ' ' . $resp);
    }

    public function registerIPN($token, $ipnUrl) {
        $prefix = defined('PESAPAL_API_PREFIX') ? PESAPAL_API_PREFIX : '/v3';
        $url = $this->baseUrl . $prefix . '/api/URLSetup/RegisterIPN';
        [$code, $resp, $data] = $this->httpRequest('POST', $url, ["Authorization: Bearer {$token}"], [
            'url' => $ipnUrl,
            'ipn_notification_type' => $this->ipnType
        ]);
        if ($code >= 200 && $code < 300 && isset($data['ipn_id'])) {
            return $data['ipn_id'];
        }
        if (isset($data['notification_id'])) {
            return $data['notification_id'];
        }
        throw new Exception('Failed to register IPN: HTTP ' . $code . ' ' . $resp);
    }

    public function findOrCreateNotificationId($token, $ipnUrl) {
        try {
            $list = $this->getIpnList($token);
            if (is_array($list)) {
                foreach ($list as $item) {
                    $itemUrl = isset($item['url']) ? rtrim($item['url'], '/') : '';
                    if ($itemUrl && strcasecmp($itemUrl, rtrim($ipnUrl, '/')) === 0) {
                        if (!empty($item['ipn_id'])) return $item['ipn_id'];
                        if (!empty($item['notification_id'])) return $item['notification_id'];
                    }
                }
            }
        } catch (Exception $e) {
            // ignore and fallback to register
        }
        return $this->registerIPN($token, $ipnUrl);
    }

    public function submitOrder($token, $payload) {
        $prefix = defined('PESAPAL_API_PREFIX') ? PESAPAL_API_PREFIX : '/v3';
        $url = $this->baseUrl . $prefix . '/api/Transactions/SubmitOrderRequest';
        [$code, $resp, $data] = $this->httpRequest('POST', $url, ["Authorization: Bearer {$token}"], $payload);
        if ($code >= 200 && $code < 300 && isset($data['redirect_url'])) {
            return $data;
        }
        throw new Exception('Failed to submit order: HTTP ' . $code . ' ' . $resp);
    }

    public function getTransactionStatus($token, $orderTrackingId) {
        $prefix = defined('PESAPAL_API_PREFIX') ? PESAPAL_API_PREFIX : '/v3';
        $url = $this->baseUrl . $prefix . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode($orderTrackingId);
        [$code, $resp, $data] = $this->httpRequest('GET', $url, ["Authorization: Bearer {$token}"], null);
        if ($code >= 200 && $code < 300 && is_array($data)) {
            return $data;
        }
        throw new Exception('Failed to get transaction status: HTTP ' . $code . ' ' . $resp);
    }

    public function requestRefund($token, $confirmationCode, $amount, $username, $remarks) {
        $prefix = defined('PESAPAL_API_PREFIX') ? PESAPAL_API_PREFIX : '/v3';
        $url = $this->baseUrl . $prefix . '/api/Transactions/RefundRequest';
        $payload = [
            'confirmation_code' => $confirmationCode,
            'amount' => (string)number_format((float)$amount, 2, '.', ''),
            'username' => $username,
            'remarks' => $remarks
        ];
        [$code, $resp, $data] = $this->httpRequest('POST', $url, ["Authorization: Bearer {$token}"], $payload);
        if ($code >= 200 && $code < 300) {
            return $data;
        }
        throw new Exception('Failed to request refund: HTTP ' . $code . ' ' . $resp);
    }

    public function cancelOrder($token, $orderTrackingId) {
        $prefix = defined('PESAPAL_API_PREFIX') ? PESAPAL_API_PREFIX : '/v3';
        $url = $this->baseUrl . $prefix . '/api/Transactions/CancelOrder';
        $payload = [ 'order_tracking_id' => $orderTrackingId ];
        [$code, $resp, $data] = $this->httpRequest('POST', $url, ["Authorization: Bearer {$token}"], $payload);
        if ($code >= 200 && $code < 300) {
            return $data;
        }
        throw new Exception('Failed to cancel order: HTTP ' . $code . ' ' . $resp);
    }
}

function pesapal_map_status($pesapalStatus) {
    $s = strtoupper((string)$pesapalStatus);
    if ($s === 'COMPLETED' || $s === 'PAID') return 'completed';
    if ($s === 'FAILED') return 'failed';
    if ($s === 'CANCELLED') return 'cancelled';
    if ($s === 'REVERSED') return 'reversed';
    if ($s === 'INVALID') return 'pending'; // Treat invalid as pending until a definitive status is received
    return 'pending';
}

function pesapal_log($message, array $context = []) {
    if (!defined('PESAPAL_LOG_FILE') || !PESAPAL_LOG_FILE) return;
    $file = PESAPAL_LOG_FILE;
    $dir = dirname($file);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $safeCtx = $context;
    // Redact any potential secrets
    foreach (['consumer_key','consumer_secret','authorization'] as $k) { if (isset($safeCtx[$k])) $safeCtx[$k] = '***'; }
    $line = [ 'ts' => gmdate('c'), 'msg' => $message, 'ctx' => $safeCtx ];
    @file_put_contents($file, json_encode($line, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}