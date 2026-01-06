<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

$email = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
if ($redirect !== '' && (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://'))) {
  $redirect = '';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: verify_notice.php');
  exit;
}

try {
  // Ensure columns exist (in case migration hasn't run)
  $checkToken = $pdo->query("SHOW COLUMNS FROM users LIKE 'verification_token'")->fetch();
  if (!$checkToken) {
    $pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) NULL");
  }
  $checkExp = $pdo->query("SHOW COLUMNS FROM users LIKE 'verification_expires'")->fetch();
  if (!$checkExp) {
    $pdo->exec("ALTER TABLE users ADD COLUMN verification_expires DATETIME NULL");
  }
  $checkVer = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->fetch();
  if (!$checkVer) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0");
  }

  $u = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $u->execute([$email]);
  $row = $u->fetch(PDO::FETCH_ASSOC) ?: null;
  if ($row) {
    $token = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + 60*60*24);
    $upd = $pdo->prepare('UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?');
    $upd->execute([$token, $expires, (int)$row['id']]);
    if (function_exists('sendVerificationEmail')) {
      @sendVerificationEmail($email, $token);
    }
  }
} catch (Throwable $e) {
  // swallow to avoid leaking errors
}

header('Location: verify_notice.php?email=' . urlencode($email) . ($redirect !== '' ? ('&redirect=' . urlencode($redirect)) : ''));
exit;
