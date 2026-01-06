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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: contact_messages.php');
    exit();
}

$message_id = intval($_GET['id']);

// Delete the message
$stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
$result = $stmt->execute([$message_id]);

if ($result) {
    header('Location: contact_messages.php?deleted=1');
} else {
    header('Location: contact_messages.php?error=1');
}
exit();
?>
