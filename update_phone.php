<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Get current user data
$user = getUserById($user_id);
if (!$user) {
    header('Location: login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');

    // Validate phone number using the improved validation function
    $validation_result = validatePhoneNumber($phone);
    if ($validation_result !== true) {
        $error = $validation_result;
    } else {
        // Clean phone number
        $clean_phone = cleanPhoneNumber($phone);

        try {
            $stmt = $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?');
            $stmt->execute([$clean_phone, $user_id]);

            $message = 'Phone number updated successfully!';
            $user['phone'] = $clean_phone;

            // Update session
            $_SESSION['phone'] = $clean_phone;

        } catch (Exception $e) {
            $error = 'Failed to update phone number. Please try again.';
            logError('Phone update failed', ['user_id' => $user_id, 'error' => $e->getMessage()]);
        }
    }
}

// Check if user needs to update phone number
$needs_phone_update = empty($user['phone']) || strpos($user['phone'], 'pending_') === 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Phone Number - AK23 Studio Kits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .phone-form {
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        .current-phone {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="phone-form">
            <h2 class="text-center mb-4">Update Phone Number</h2>

            <?php if ($needs_phone_update): ?>
                <div class="alert alert-warning">
                    <strong>Phone Number Required:</strong> Please provide your phone number to continue using our services.
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="current-phone">
                <strong>Current Phone:</strong>
                <?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?>
            </div>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" id="phone" name="phone"
                           placeholder="e.g., +255 655 991 973 or 0655991973"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                    <div class="form-text">Include country code for international numbers (e.g., +255 for Tanzania)</div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Update Phone Number</button>
                </div>
            </form>

            <?php if (!$needs_phone_update): ?>
                <div class="mt-3 text-center">
                    <a href="user_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
