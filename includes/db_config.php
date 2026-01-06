<?php
// Database connection config
// Adjust as needed for your environment
// Allow credentials to be pre-set by an including file (e.g., config.php).
// If not set, use safe defaults for local dev.
if (!isset($host)) { $host = 'localhost'; }
if (!isset($db))   { $db   = 'maarifazone'; }
if (!isset($user)) { $user = 'root'; }
if (!isset($pass)) { $pass = ''; }

// Create PDO connection
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $dsn = 'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }
}