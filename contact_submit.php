<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

// Rate limiting - max 3 submissions per hour per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_key = "contact_form_{$ip}";
$last_submission = $_SESSION[$rate_limit_key] ?? 0;
$current_time = time();

if ($current_time - $last_submission < 3600) { // 1 hour
    $submissions = $_SESSION[$rate_limit_key . '_count'] ?? 0;
    if ($submissions >= 3) {
        $_SESSION['contact_error'] = 'Too many submissions. Please try again later.';
        header('Location: contact_us.php');
        exit();
    }
    $_SESSION[$rate_limit_key . '_count'] = $submissions + 1;
} else {
    $_SESSION[$rate_limit_key] = $current_time;
    $_SESSION[$rate_limit_key . '_count'] = 1;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact_us.php');
    exit();
}

// CSRF protection
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['contact_error'] = 'Security error. Please try again.';
    header('Location: contact_us.php');
    exit();
}

// Honeypot check - if filled, it's likely a bot
if (!empty($_POST['website'])) {
    $_SESSION['contact_error'] = 'Spam detected. Please try again.';
    header('Location: contact_us.php');
    exit();
}

// Generate captcha numbers if not set
if (!isset($_SESSION['captcha_num1'])) {
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
}

// Validate captcha
$user_captcha = (int)($_POST['captcha'] ?? 0);
$expected_sum = ($_SESSION['captcha_num1'] ?? 5) + ($_SESSION['captcha_num2'] ?? 3);
if ($user_captcha !== $expected_sum) {
    $_SESSION['contact_error'] = 'Incorrect security answer. Please try again.';
    header('Location: contact_us.php');
    exit();
}

// Regenerate captcha for next use
$_SESSION['captcha_num1'] = rand(1, 10);
$_SESSION['captcha_num2'] = rand(1, 10);

// Sanitize and validate inputs
function sanitizeString($input) {
    return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
}

$name = sanitizeString($_POST['name'] ?? '');
$email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$phone = sanitizeString($_POST['phone'] ?? '');
$subject = sanitizeString($_POST['subject'] ?? '');
$category = sanitizeString($_POST['category'] ?? '');
$message = sanitizeString($_POST['message'] ?? '');
$priority = sanitizeString($_POST['priority'] ?? 'normal');

// Validation
$errors = [];

if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    $errors[] = 'Name must be between 2 and 100 characters';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
}

if (!empty($phone) && !preg_match('/^[\+]?[1-9][\d\s\-\(\)]{0,19}$/', $phone)) {
    $errors[] = 'Please enter a valid phone number';
}

if (empty($subject) || strlen($subject) < 5 || strlen($subject) > 255) {
    $errors[] = 'Subject must be between 5 and 255 characters';
}

$valid_categories = ['technical', 'billing', 'account', 'course', 'feature', 'partnership', 'other'];
if (empty($category) || !in_array($category, $valid_categories)) {
    $errors[] = 'Please select a valid category';
}

if (empty($message) || strlen($message) < 10 || strlen($message) > 2000) {
    $errors[] = 'Message must be between 10 and 2000 characters';
}

$valid_priorities = ['low', 'normal', 'high'];
if (!in_array($priority, $valid_priorities)) {
    $priority = 'normal';
}

if (!empty($errors)) {
    $_SESSION['contact_error'] = implode('<br>', $errors);
    header('Location: contact_us.php');
    exit();
}

// Check if user is logged in
$user_id = null;
$user_info = null;
if (isset($_SESSION['user_logged_in']) && isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user_info) {
            $user_id = null; // Invalid user, treat as guest
        }
    } catch (Exception $e) {
        $user_id = null;
    }
}

// Insert into contact_messages table (create if needed) or use tickets table
try {
    // Create contact_messages table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NULL,
        subject VARCHAR(255) NOT NULL,
        category VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_category (category)
    )");

    $stmt = $pdo->prepare("INSERT INTO contact_messages
        (user_id, name, email, phone, subject, category, message, priority, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $user_id,
        $name,
        $email,
        $phone ?: null,
        $subject,
        $category,
        $message,
        $priority,
        $ip,
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    // Send email notification to admin
    $adminEmail = 'admin@akdownloads.com';
    $emailSubject = "New Contact Form Submission: $subject";

    $emailBody = "New contact form submission\n\n";
    $emailBody .= "From: $name ($email)\n";
    if (!empty($phone)) $emailBody .= "Phone: $phone\n";
    $emailBody .= "Category: " . ucfirst($category) . "\n";
    $emailBody .= "Priority: " . ucfirst($priority) . "\n";
    $emailBody .= "IP Address: $ip\n";
    if ($user_id) $emailBody .= "Registered User ID: $user_id\n";
    $emailBody .= "\nSubject: $subject\n\n";
    $emailBody .= "Message:\n$message\n\n";
    $emailBody .= "---\nThis message was sent via the contact form.";

    // Send email if function exists
    if (function_exists('sendMail')) {
        sendMail($adminEmail, $emailSubject, $emailBody);
    }

    // Regenerate CSRF token for security
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $_SESSION['contact_success'] = true;

} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    $_SESSION['contact_error'] = 'Failed to send message. Please try again later.';
}

header('Location: contact_us.php');
exit();
?>
