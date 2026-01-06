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

if (!isset($_GET['id'])) {
    header('Location: manage_payments.php');
    exit();
}
$id = intval($_GET['id']);
$stmt = $pdo->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
$stmt->execute([$id]);
header('Location: manage_payments.php?success=1');
exit(); 