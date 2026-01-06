<?php
require_once __DIR__ . '/config.php';

function generate_download_token(int $orderId, int $validMinutes = 60*24): string {
    $exp = time() + ($validMinutes * 60);
    $payload = $orderId . '|' . $exp;
    $sig = base64_encode(hash_hmac('sha256', $payload, DOWNLOAD_TOKEN_SECRET, true));
    return 'ORD' . $orderId . '.' . $exp . '.' . rtrim(strtr($sig, '+/', '-_'), '=');
}

function verify_download_token(string $token): ?array {
    // token format: ORD<id>.<exp>.<sig>
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $ord = $parts[0];
    $exp = (int)$parts[1];
    $sig = $parts[2];
    if (strpos($ord, 'ORD') !== 0) return null;
    $orderId = (int)substr($ord, 3);
    if ($orderId <= 0) return null;
    // No expiry enforcement: allow tokens to remain valid indefinitely

    $payload = $orderId . '|' . $exp;
    $expected = base64_encode(hash_hmac('sha256', $payload, DOWNLOAD_TOKEN_SECRET, true));
    $expected = rtrim(strtr($expected, '+/', '-_'), '=');

    if (!hash_equals($expected, $sig)) return null;

    return ['order_id' => $orderId, 'exp' => $exp];
}
