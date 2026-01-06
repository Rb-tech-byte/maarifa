<?php
session_start();
require 'config.php';
require_once __DIR__ . '/download_helper.php';

// Read course ID safely
$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
if ($course_id <= 0) {
    http_response_code(400);
    echo 'Invalid course.';
    exit;
}

// Require authenticated user
$sessionUid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($sessionUid <= 0) {
    // Lookup slug for redirect back to course details
    require_once __DIR__ . '/includes/db_config.php';
    $slugStmt = $pdo->prepare('SELECT slug FROM courses WHERE id = ? LIMIT 1');
    $slugStmt->execute([$course_id]);
    $slugRow = $slugStmt->fetch(PDO::FETCH_ASSOC);
    $redir = 'index.php';
    if ($slugRow && !empty($slugRow['slug'])) {
        $redir = 'course-details.php?slug=' . rawurlencode((string)$slugRow['slug']);
    }
    header('Location: login.php?redirect=' . urlencode($redir));
    exit;
}

// Resolve user (must exist)
$stmt = $pdo->prepare('SELECT id, name, email, is_verified FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$sessionUid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$row) {
    header('Location: login.php');
    exit;
}
$user_id = (int)$row['id'];
$email = (string)($row['email'] ?? '');
// Email verification is optional for payments now; proceed regardless of is_verified

// Remember buyer email for convenience if provided
if (!empty($email)) {
    setcookie('buyer_email', $email, time() + 60*60*24*365, '/', '', false, true);
}

// Determine course price (only for published courses)
$pstmt = $pdo->prepare('SELECT id, price FROM courses WHERE id = ? AND visibility = ? LIMIT 1');
$pstmt->execute([$course_id, 'published']);
$prow = $pstmt->fetch(PDO::FETCH_ASSOC);
$price = $prow ? (float)$prow['price'] : null;

if ($price === null) {
    http_response_code(404);
    echo 'Course not found or not published.';
    exit;
}

// Clean up any expired payment intents (older than 24 hours)
if (isset($_SESSION['payment_intent']) && time() - $_SESSION['payment_intent']['created_at'] > 86400) {
    unset($_SESSION['payment_intent']);
}

// Free course path: auto-complete order and payment, then generate token
if ($price <= 0) {
    // Create completed order
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, course_id, status, created_at) VALUES (?,?, 'completed', NOW())");
    $stmt->execute([$user_id, $course_id]);
    $order_id = (int)$pdo->lastInsertId();

    // Create completed zero-amount payment for consistency
    $stmt = $pdo->prepare("INSERT INTO payments (order_id, amount, status, payment_method, created_at, paid_at) VALUES (?, 0, 'completed', 'free', NOW(), NOW())");
    $stmt->execute([$order_id]);

    // Generate a signed token and redirect to download
    $token = function_exists('generate_download_token') ? generate_download_token($order_id, 60*24) : '';
    if ($token === '') {
        // Fallback to orders page if token generation failed
        header('Location: orders.php?order_id=' . $order_id);
        exit;
    }
    header('Location: downloads.php?token=' . urlencode($token));
    exit;
}

// Paid course path: Create temporary payment session then redirect to Pesapal
// Store payment intent in session instead of creating database records
$_SESSION['payment_intent'] = [
    'user_id' => $user_id,
    'course_id' => $course_id,
    'amount' => $price,
    'email' => $email,
    'created_at' => time()
];

header('Location: pay_pesapal.php?intent=1');
exit;
