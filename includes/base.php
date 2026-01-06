<?php
// Load security baseline (headers, session flags, CSRF helpers)
$sec = __DIR__ . '/security.php';
if (file_exists($sec)) { require_once $sec; }
// Centralized base path computation for consistent links and assets across the app.
// Usage: require_once __DIR__ . '/base.php'; then use $base in href/src.

if (!isset($base) || $base === '') {
    // Compute the origin (scheme://host[:port]) first
    $origin = '';
    $cfg = __DIR__ . '/../ak23pay/config.php';
    if (file_exists($cfg)) {
        @require_once $cfg;
        if (function_exists('ak23_current_origin')) {
            $origin = rtrim((string)ak23_current_origin(), '/');
        }
    }
    if ($origin === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $origin = $scheme . '://' . $host;
    }

    // Compute the project web path from document root
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
    $projectRootFs = str_replace('\\','/', realpath(__DIR__ . '/..'));
    $webPath = '';
    if ($docRoot && $projectRootFs && str_starts_with($projectRootFs, $docRoot)) {
        $webPath = substr($projectRootFs, strlen($docRoot));
    }
    $webPath = rtrim($webPath, '/');

    // Final base: dynamic origin + project path (works on localhost and courseion)
    $base = rtrim($origin . $webPath, '/');
}
