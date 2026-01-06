<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    header('Location: users.php');
    exit();
}

// Fetch target user
$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: users.php');
    exit();
}

// Establish user session alongside admin session (do not remove admin session)
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = (string)($user['name'] ?? '');
$_SESSION['user_logged_in'] = true;
$_SESSION['role'] = 'user';
$_SESSION['impersonated_by_admin'] = true;
$_SESSION['impersonated_at'] = date('Y-m-d H:i:s');

// Redirect to user dashboard/panel
header('Location: ../user_index.php');
exit();
