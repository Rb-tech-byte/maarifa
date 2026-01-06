<?php
// Admin auth guard: include from any admin/*.php to protect the page
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$logged = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$logged) {
    // Redirect to root login page
    header('Location: ../login.php');
    exit();
}
