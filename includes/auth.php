<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function checkAdminAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }
}

function checkUserAuth() {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        header("Location: ../login.php");
        exit();
    }

    // Redirect instructors to their dashboard
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'instructor') {
        header("Location: ../instructor_dashboard.php");
        exit();
    }
}

function checkInstructorAuth() {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'instructor') {
        header("Location: ../login.php");
        exit();
    }
}