<?php
function getCategoryTreeCached(): array {
  // Bypass cache so categories always reflect current course_categories
  global $pdo;
  $rows = [];
  try {
    $q = $pdo->query("SELECT id, parent_id, name, slug FROM course_categories ORDER BY name");
    $rows = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
  } catch (Throwable $e) {
    $rows = [];
  }

  $tree = [];
  foreach ($rows as $r) {
    $pid = (int)($r['parent_id'] ?? 0);
    if (!isset($tree[$pid])) {
      $tree[$pid] = [];
    }
    $tree[$pid][] = $r;
  }

  return $tree;
}

require_once __DIR__ . '/mails_smtp.php';
require_once __DIR__ . '/sms_provider.php';
require_once __DIR__ . '/cache.php';
/**
 * Validate phone number format
 */
function validatePhoneNumber($phone) {
  if (empty($phone)) {
    return 'Phone number is required.';
  }

  // Keep digits and optional leading +
  $clean_phone = preg_replace('/[^0-9+]/', '', $phone);

  // Only one + allowed and it must be the first character if present
  if (substr_count($clean_phone, '+') > 1 || (strpos($clean_phone, '+') > 0)) {
    return 'Phone number can only contain digits and an optional leading + for country code.';
  }

  // Validate total digits count (exclude +) to be 10-15
  $digits_only = preg_replace('/\D/', '', $clean_phone);
  $len = strlen($digits_only);
  if ($len < 10 || $len > 15) {
    return 'Phone number must contain 10 to 15 digits.';
  }

  // Final shape check: optional + followed by 10-15 digits
  if (!preg_match('/^\+?\d{10,15}$/', $clean_phone)) {
    return 'Please enter a valid phone number (optional leading +, 10–15 digits).';
  }

  return true;
}

/**
 * Clean and format phone number
 */
function cleanPhoneNumber($phone) {
  if (empty($phone)) {
    return '';
  }
  // Strip spaces, dashes, parentheses, etc.
  $num = preg_replace('/[^0-9+]/', '', $phone);
  // Convert 00 international prefix to +
  if (strpos($num, '00') === 0) {
    $num = '+' . substr($num, 2);
  }
  // Collapse multiple leading +'s into one
  $num = preg_replace('/^\++/', '+', $num);
  // Ensure + only at the start; remove any internal +
  if (strpos($num, '+') > 0) {
    $num = substr($num, 0, 1) . str_replace('+', '', substr($num, 1));
  }
  return $num;
}


// Try to load Composer autoloader for PHPMailer if available
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// --- Password Reset Email Helper ---
function sendPasswordResetEmail(string $to, string $token): bool {
    // Determine base URL
    if (function_exists('ak23_get_base_url')) {
        $base = rtrim((string)ak23_get_base_url(), '/');
    } elseif (function_exists('ak23_current_origin')) {
        $base = rtrim((string)ak23_current_origin(), '/');
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $base = $scheme . '://' . $host;
    }

    $reset_link = $base . '/reset_password.php?token=' . urlencode($token);
    $subject = 'Reset your password - AK23 Studio Kits';
    $html = '<p>You requested a password reset.</p>' .
            '<p>Click the link below to set a new password (valid for 60 minutes):</p>' .
            '<p><a href="' . htmlspecialchars($reset_link) . '">Reset Password</a></p>' .
            '<p>If you did not request this, you can ignore this email.</p>';

    // Fire-and-forget SMS alert to phone if we can resolve the user
    try {
        if (function_exists('getUserByEmail')) {
            $u = getUserByEmail($to);
            $phone = $u['phone'] ?? '';
            if (!empty($phone)) {
                @ak23_sms_send($phone, 'Password reset requested. Reset link: ' . $reset_link);
            }
        }
    } catch (Throwable $e) { /* ignore SMS failures */ }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) { return false; }
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $cfg = ak23_get_smtp_config();
        ak23_configure_mailer($mail, $cfg);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        return $mail->send();
    } catch (Throwable $e) {
        logError('PHPMailer sendPasswordResetEmail failed', ['error' => $e->getMessage()]);
        return false;
    }
}
// Import PHPMailer classes if present (no fatal if missing)
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    // ok
}

function getFeaturedcourses() {
    global $pdo;
    $sql = "SELECT * FROM courses WHERE is_featured = 1 LIMIT 4";
    $stmt = $pdo->query($sql);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $courses;
}

// --- Email Verification Helper ---
function sendVerificationEmail(string $to, string $token): bool {
    // Determine base URL
    $base = null;
    if (function_exists('ak23_get_base_url')) {
        $base = rtrim((string)ak23_get_base_url(), '/');
    } elseif (function_exists('ak23_current_origin')) {
        $base = rtrim((string)ak23_current_origin(), '/');
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $base = $scheme . '://' . $host;
    }

    $verify_link = $base . '/verify_email.php?token=' . urlencode($token);
    $subject = 'Verify your email - AK23 Studio Kits';
    $html = '<p>Welcome to AK23 Studio Kits!</p>' .
            '<p>Please verify your email by clicking the link below:</p>' .
            '<p><a href="' . htmlspecialchars($verify_link) . '">Verify Email</a></p>' .
            '<p>If you did not create an account, you can ignore this message.</p>';

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) { return false; }
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $cfg = ak23_get_smtp_config();
        ak23_configure_mailer($mail, $cfg);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        return $mail->send();
    } catch (Throwable $e) {
        logError('PHPMailer sendVerificationEmail failed', ['error' => $e->getMessage()]);
        return false;
    }
}


function getcourseById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- LMS Helpers for course details / curriculum / enrollment ---
if (!function_exists('mz_get_course_by_slug')) {
    /**
     * Fetch a single course by slug, including basic metadata.
     * For now we join only the legacy categories table if category_id is present.
     */
    function mz_get_course_by_slug(string $slug, bool $withMeta = true): ?array {
        global $pdo;
        if ($slug === '') return null;

        // Base select from courses
        $sql = "SELECT c.*
                FROM courses c
                WHERE c.slug = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$slug]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) return null;

        if ($withMeta) {
            // Attach category name if possible (legacy categories table)
            if (!empty($course['category_id'])) {
                try {
                    $cstmt = $pdo->prepare('SELECT name, slug FROM categories WHERE id = ?');
                    $cstmt->execute([(int)$course['category_id']]);
                    if ($cat = $cstmt->fetch(PDO::FETCH_ASSOC)) {
                        $course['category_name'] = $cat['name'];
                        $course['category_slug'] = $cat['slug'] ?? '';
                    }
                } catch (Throwable $e) {
                    // ignore, keep course data only
                }
            }
        }
        return $course;
    }
}

if (!function_exists('mz_get_course_curriculum')) {
    /**
     * Return modules with their lessons for a course.
     */
    function mz_get_course_curriculum(int $course_id): array {
        global $pdo;
        if ($course_id <= 0) return [];

        // Fetch modules
        $mstmt = $pdo->prepare("SELECT * FROM modules WHERE course_id = ? ORDER BY order_number, id");
        $mstmt->execute([$course_id]);
        $modules = $mstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$modules) return [];

        $moduleIds = array_map('intval', array_column($modules, 'id'));
        if (empty($moduleIds)) return [];

        // Fetch lessons for all modules
        $in = implode(',', array_fill(0, count($moduleIds), '?'));
        $lstmt = $pdo->prepare("SELECT * FROM lessons WHERE module_id IN ($in) ORDER BY module_id, order_number, id");
        $lstmt->execute($moduleIds);
        $lessons = $lstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Group lessons by module_id
        $byModule = [];
        foreach ($lessons as $l) {
            $mid = (int)$l['module_id'];
            if (!isset($byModule[$mid])) $byModule[$mid] = [];
            $byModule[$mid][] = $l;
        }

        // Attach lessons to modules
        foreach ($modules as &$m) {
            $mid = (int)$m['id'];
            $m['lessons'] = $byModule[$mid] ?? [];
        }
        unset($m);

        return $modules;
    }
}

if (!function_exists('mz_is_student_enrolled')) {
    /**
     * Check if a user is enrolled in a course (active or completed).
     */
    function mz_is_student_enrolled(int $user_id, int $course_id): bool {
        global $pdo;
        if ($user_id <= 0 || $course_id <= 0) return false;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND course_id = ? AND status IN ('active','completed')");
        $stmt->execute([$user_id, $course_id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('mz_get_enrollment')) {
    /**
     * Fetch enrollment row for a user/course if it exists.
     */
    function mz_get_enrollment(int $user_id, int $course_id): ?array {
        global $pdo;
        if ($user_id <= 0 || $course_id <= 0) return null;
        $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
        $stmt->execute([$user_id, $course_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

function createPayment($order_id, $amount) {
    // Insert a payment linked to an order. Schema: payments(order_id, amount, status, ...)
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO payments (order_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $stmt->execute([$order_id, $amount]);
    return $pdo->lastInsertId();
}

function updatePaymentStatus($payment_id, $status) {
    global $pdo;
    // Validate status against payments enum
    $valid_statuses = ['pending', 'completed', 'failed', 'cancelled', 'reversed'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'pending'; // Default to pending if invalid
    }
    
    // Get current payment details
    $payment = getPaymentById($payment_id);
    if (!$payment) {
        logError("Payment not found for status update", ['payment_id' => $payment_id, 'status' => $status]);
        return false;
    }
    
    // Update payment status
    $stmt = $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $payment_id]);
    
    // Update corresponding order status (mirror valid statuses)
    if ($payment['order_id']) {
        $order_status = $status; // keep same status name for orders
        updateOrderStatus($payment['order_id'], $order_status);
    }
    
    // Set paid_at timestamp for completed payments
    if ($status === 'completed') {
        $stmt = $pdo->prepare("UPDATE payments SET paid_at = NOW() WHERE id = ?");
        $stmt->execute([$payment_id]);
    }
    
    // --- Trigger notification for user and admin ---
    // Derive user/course via the linked order (payments table has no user_id/course_id columns)
    $user_id = null;
    $course_name = 'Unknown course';
    if (!empty($payment['order_id'])) {
        $order = getOrderById((int)$payment['order_id']);
        if ($order) {
            $user_id = $order['user_id'] ?? null;
            $course = getcourseById($order['course_id']);
            if ($course) { $course_name = $course['name']; }
        }
    }
    
    switch ($status) {
        case 'completed':
            createNotification($user_id, 'system', "Payment completed for {$course_name}. Your download is ready!");
            createNotification(1, 'system', "Payment completed: Order #{$payment['order_id']} - {$course_name} (TSH " . number_format($payment['amount'], 0) . ")");
            $u = $user_id ? getUserById($user_id) : null; $phone = $u['phone'] ?? '';
            if ($phone !== '') { @ak23_sms_send($phone, "Payment completed for {$course_name}. Your download is ready!"); }
            break;
        case 'failed':
            createNotification($user_id, 'system', "Payment failed for {$course_name}. Please try again or contact support.");
            createNotification(1, 'system', "Payment failed: Order #{$payment['order_id']} - {$course_name}");
            $u = $user_id ? getUserById($user_id) : null; $phone = $u['phone'] ?? '';
            if ($phone !== '') { @ak23_sms_send($phone, "Payment failed for {$course_name}. Please try again or contact support."); }
            break;
        case 'cancelled':
            createNotification($user_id, 'system', "Payment cancelled for {$course_name}.");
            createNotification(1, 'system', "Payment cancelled: Order #{$payment['order_id']} - {$course_name}");
            $u = $user_id ? getUserById($user_id) : null; $phone = $u['phone'] ?? '';
            if ($phone !== '') { @ak23_sms_send($phone, "Payment cancelled for {$course_name}."); }
            break;
        case 'reversed':
            createNotification($user_id, 'system', "Payment reversed for {$course_name}.");
            createNotification(1, 'system', "Payment reversed: Order #{$payment['order_id']} - {$course_name}");
            $u = $user_id ? getUserById($user_id) : null; $phone = $u['phone'] ?? '';
            if ($phone !== '') { @ak23_sms_send($phone, "Payment reversed for {$course_name}."); }
            break;
        case 'pending':
            createNotification($user_id, 'system', "Payment pending for {$course_name}. Please complete the payment process.");
            $u = $user_id ? getUserById($user_id) : null; $phone = $u['phone'] ?? '';
            if ($phone !== '') { @ak23_sms_send($phone, "Payment pending for {$course_name}. Please complete the payment process."); }
            break;
    }
    
    // Log the status change
    logError("Payment status updated", [
        'payment_id' => $payment_id,
        'old_status' => $payment['status'],
        'new_status' => $status,
        'user_id' => $user_id,
        'order_id' => $payment['order_id'] ?? null
    ]);
    
    return $result;
}

function getPaymentById($payment_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Admin functions
function authenticateAdmin($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

function getDashboardStats() {
    global $pdo;
    $stats = [];
    // Total courses
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM courses");
    $stats['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    // Total sales
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
    $stats['total_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    // Pending payments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
    $stats['pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    return $stats;
}

function addcourse($name, $description, $price, $file_path) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO courses (name, description, price, file_path) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$name, $description, $price, $file_path]);
}

function updatecourse($id, $name, $description, $price) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE courses SET name = ?, description = ?, price = ? WHERE id = ?");
    return $stmt->execute([$name, $description, $price, $id]);
}

function deletecourse($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    return $stmt->execute([$id]);
}

function getAllPayments() {
    global $pdo;
    $sql = "SELECT p.*, pr.title AS course_name, o.user_id, o.course_id
            FROM payments p
            JOIN orders o ON o.id = p.order_id
            JOIN courses pr ON pr.id = o.course_id
            ORDER BY p.created_at DESC";
    $stmt = $pdo->query($sql);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $payments;
}
// --- Email Sending Helper ---
function sendDownloadEmail($to, $course, $token) {
    // Determine dynamic base URL from config/helper
    $base = null;
    if (function_exists('ak23_get_base_url')) {
        $base = rtrim((string)ak23_get_base_url(), '/');
    } elseif (function_exists('ak23_current_origin')) {
        $base = rtrim((string)ak23_current_origin(), '/');
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $base = $scheme . '://' . $host;
    }

    $subject = "Thank you for your purchase: {$course['name']}";
    $download_link = $base . "/downloads.php?token=" . urlencode($token);
    $logo_url = $base . "/assets/images/logo.png";
    $course_img = $base . "/" . ltrim((string)($course['thumbnail'] ?? ''), '/');
    $html = @file_get_contents(__DIR__ . '/../templates/email_template.html');
    if ($html === false) {
        $html = '<p>Thank you for your purchase: ' . htmlspecialchars($course['name']) . '</p>' .
                '<p>Your download link: <a href="' . htmlspecialchars($download_link) . '">' . htmlspecialchars($download_link) . '</a></p>';
    } else {
        $html = str_replace([
            '{{logo_url}}',
            '{{course_name}}',
            '{{course_img}}',
            '{{download_link}}',
            '{{support_email}}'
        ], [
            $logo_url,
            htmlspecialchars($course['name']),
            $course_img,
            $download_link,
            'info@ak23studiokits.com'
        ], $html);
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) { return false; }
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $cfg = ak23_get_smtp_config();
        ak23_configure_mailer($mail, $cfg);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        return $mail->send();
    } catch (Throwable $e) {
        logError('PHPMailer sendDownloadEmail failed', ['error' => $e->getMessage()]);
        return false;
    }
}

function getAllcourses() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM courses ORDER BY id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- User Authentication ---
function authenticateUser($name, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ? OR email = ?");
    $stmt->execute([$name, $name]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

// Fetch user info by user ID
function getUserById($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Password Reset Helpers ---
// Ensure your users table has columns: reset_token VARCHAR(64), reset_token_expires DATETIME

// --- Guest User Creation ---
function getUserByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createGuestUser($email, $phone) {
    global $pdo;
    // Generate a random password for the guest user
    $password = bin2hex(random_bytes(8)); // 16 characters hex
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Use the actual database structure with all required fields
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, created_at) VALUES (?, ?, ?, ?, NOW())");
    $name = 'guest_' . uniqid();
    
    try {
        $stmt->execute([$name, $email, $hashed_password, $phone]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        // If the first attempt fails, try with minimal fields
        error_log("Guest user creation failed with full fields: " . $e->getMessage());
        
        // Try with minimal required fields
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$name, $email, $hashed_password]);
        return $pdo->lastInsertId();
    }
}

function autoLoginUser($user) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['name'];
    $_SESSION['user_logged_in'] = true;
    $_SESSION['role'] = $user['role'] ?? 'user';
}

// --- Order Management ---
function createOrder($user_id, $course_id) {
    // Schema supports: user_id, course_id, status, timestamps
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, course_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $course_id]);
    $orderId = $pdo->lastInsertId();
    // Fire-and-forget SMS to user about order creation (pending)
    try {
        $u = getUserById($user_id);
        $p = getcourseById($course_id);
        $phone = $u['phone'] ?? '';
        if (!empty($phone)) {
            // Build a payment link for convenience
            if (function_exists('ak23_get_base_url')) {
                $base = rtrim((string)ak23_get_base_url(), '/');
            } elseif (function_exists('ak23_current_origin')) {
                $base = rtrim((string)ak23_current_origin(), '/');
            } else {
                $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                $scheme = $https ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $base = $scheme . '://' . $host;
            }
            $payLink = $base . '/pay_pesapal.php?order_id=' . urlencode((string)$orderId);
            $prodName = $p['name'] ?? 'your course';
            @ak23_sms_send($phone, 'Order created for ' . $prodName . '. Complete payment: ' . $payLink);
        }
    } catch (Throwable $e) { /* ignore SMS failures */ }
    return $orderId;
}

function updateOrderStatus($order_id, $status) {
    global $pdo;
    // Validate status against orders enum
    $valid_statuses = ['pending', 'completed', 'failed', 'cancelled', 'reversed'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'pending'; // Default to pending if invalid
    }
    
    // Get current order details
    $order = getOrderById($order_id);
    if (!$order) {
        logError("Order not found for status update", ['order_id' => $order_id, 'status' => $status]);
        return false;
    }
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $order_id]);
    
    // No paid_at/reference/amount columns in orders schema; do not attempt to set them
    
    // --- Trigger notification for user and admin ---
    $user_id = $order['user_id'];
    $course = getcourseById($order['course_id']);
    $course_name = $course ? $course['name'] : 'Unknown course';
    
    switch ($status) {
        case 'completed':
            createNotification($user_id, 'system', "Order completed for {$course_name}. Your download is ready!");
            createNotification(1, 'system', "Order completed: Order #{$order_id} - {$course_name}");
            break;
        case 'failed':
            createNotification($user_id, 'system', "Order failed for {$course_name}. Please try again or contact support.");
            createNotification(1, 'system', "Order failed: Order #{$order_id} - {$course_name}");
            break;
        case 'cancelled':
            createNotification($user_id, 'system', "Order cancelled for {$course_name}.");
            createNotification(1, 'system', "Order cancelled: Order #{$order_id} - {$course_name}");
            break;
        case 'reversed':
            createNotification($user_id, 'system', "Order reversed for {$course_name}.");
            createNotification(1, 'system', "Order reversed: Order #{$order_id} - {$course_name}");
            break;
        case 'pending':
            createNotification($user_id, 'system', "Order pending for {$course_name}. Please complete the payment process.");
            break;
    }
    
    // Log the status change
    logError("Order status updated", [
        'order_id' => $order_id,
        'old_status' => $order['status'],
        'new_status' => $status,
        'user_id' => $user_id,
        'course_id' => $order['course_id']
    ]);
    
    return $result;
}

function getOrderById($order_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getOrderByReference($reference) {
    // Accept formats like ORD<id>. If matches, fetch by ID. Otherwise, not available in schema.
    global $pdo;
    if (preg_match('/^ORD(\d+)$/i', (string)$reference, $m)) {
        return getOrderById((int)$m[1]);
    }
    return null;
}

function getPaymentByReference($reference) {
    // If reference is ORD<id>, fetch payment by order_id. Otherwise, try order_tracking_id.
    global $pdo;
    if (preg_match('/^ORD(\d+)$/i', (string)$reference, $m)) {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ?");
        $stmt->execute([(int)$m[1]]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    // Fallback: treat reference as tracking id
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_tracking_id = ?");
    $stmt->execute([$reference]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Notification Helpers ---
function getUserNotifications($user_id, $limit = 5) {
    global $pdo;
    $limit = (int)$limit;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAdminNotifications($limit = 5) {
    global $pdo;
    $limit = (int)$limit;
    $stmt = $pdo->prepare("SELECT n.*, u.username FROM notifications n JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT $limit");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Notification Creation Helper ---
function createNotification($user_id, $type, $message) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $type, $message]);
}

// --- Admin Profile Management ---
function getAdminById($admin_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$admin_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateAdminProfile($admin_id, $username) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
    return $stmt->execute([$username, $admin_id]);
}

function updateAdminPassword($admin_id, $hashed_password) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
    return $stmt->execute([$hashed_password, $admin_id]);
}

function checkAdminUsernameExists($username, $exclude_id = null) {
    global $pdo;
    if ($exclude_id) {
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $exclude_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
    }
    return $stmt->fetch() ? true : false;
}

function getAdminStats() {
    global $pdo;
    $stats = [];
    
    // Total admin users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_users");
    $stats['total_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Recent admin activity (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $stats;
}

// --- Error Logging and Debugging ---
function logError($message, $context = []) {
    $log_file = __DIR__ . '/../logs/error.log';
    $log_dir = dirname($log_file);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $log_entry = "[$timestamp] $message$context_str\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function logPaymentError($reference, $error, $data = []) {
    $context = [
        'reference' => $reference,
        'error' => $error,
        'data' => $data,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ];
    logError("Payment processing error for reference: $reference", $context);
}

function logCallbackError($reference, $error, $callback_data = []) {
    $context = [
        'reference' => $reference,
        'error' => $error,
        'callback_data' => $callback_data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ];
    logError("Callback processing error for reference: $reference", $context);
}

// --- Download Token Helpers (compat wrapper) ---
// Some pages call ak23_sign([...]) to build a secure download token.
// We delegate to download_helper.php's generate_download_token() so token format stays consistent.
if (!function_exists('ak23_sign')) {
    function ak23_sign(array $payload, int $validMinutes = 60*24): string {
        // Ensure helper is loaded
        if (!function_exists('generate_download_token')) {
            $helper = __DIR__ . '/../download_helper.php';
            if (file_exists($helper)) require_once $helper;
        }
        $orderId = 0;
        if (isset($payload['order_id'])) {
            $orderId = (int)$payload['order_id'];
        } elseif (isset($payload['reference']) && preg_match('/^ORD(\d+)$/i', (string)$payload['reference'], $m)) {
            $orderId = (int)$m[1];
        }
        if ($orderId <= 0) {
            // As a safe fallback, refuse to generate a token
            return '';
        }
        if (function_exists('generate_download_token')) {
            return generate_download_token($orderId, $validMinutes);
        }
        // Inline fallback (should not happen)
        $exp = time() + ($validMinutes * 60);
        $payloadStr = $orderId . '|' . $exp;
        $sig = base64_encode(hash_hmac('sha256', $payloadStr, defined('DOWNLOAD_TOKEN_SECRET')?DOWNLOAD_TOKEN_SECRET:'' , true));
        $sig = rtrim(strtr($sig, '+/', '-_'), '=');
        return 'ORD' . $orderId . '.' . $exp . '.' . $sig;
    }
}

function generateUniqueWalletNumber(PDO $pdo): string {
    do {
        $walletNumber = 'WALLET-' . strtoupper(bin2hex(random_bytes(5)));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM instructor_wallet WHERE wallet_number = ?");
        $stmt->execute([$walletNumber]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    return $walletNumber;
}

function createInstructorWallet(PDO $pdo, int $instructor_id) {
    $walletNumber = generateUniqueWalletNumber($pdo);
    $stmt = $pdo->prepare("INSERT INTO instructor_wallet (instructor_id, wallet_number, balance, total_earned) VALUES (?, ?, 0, 0)");
    $stmt->execute([$instructor_id, $walletNumber]);
}
