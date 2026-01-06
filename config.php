<?php
$host = 'localhost';

// MySQL database settings
$db = 'maarifazone';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$pdo = new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// Pesapal v3 configuration
// Base host: 'https://pay.pesapal.com' for live or 'https://cybqa.pesapal.com' for sandbox/testing
// API prefix: '/v3' for live or '/pesapalv3' for sandbox
define('PESAPAL_BASE_URL', 'https://pay.pesapal.com');
define('PESAPAL_API_PREFIX', '/v3');

define('PESAPAL_CONSUMER_KEY', 'ZXWnlROk2zpkzRE1cT0D7gWcE83RbgYD');
define('PESAPAL_CONSUMER_SECRET', 'RxSVYst70cYujL/k8QgpTjPSNO4=');

// Your publicly accessible IPN URL (should map to ipn.php)
// IMPORTANT: Ensure this URL is accessible from Pesapal servers.
define('PESAPAL_IPN_URL', 'https://www.akdownloads.com/ipn.php');

// Default currency for your transactions
define('PESAPAL_CURRENCY', 'TZS');

// Optional: If Pesapal has already provisioned your Notification ID, set it here to skip re-registering IPN each time.
// define('PESAPAL_NOTIFICATION_ID', 'YOUR_PESAPAL_NOTIFICATION_ID');

// Callback URL the customer is redirected to after payment on Pesapal
define('PESAPAL_CALLBACK_URL', 'https://www.akdownloads.com/callback.php');

// IPN type: 'POST' (recommended) or 'GET' for legacy compatibility
define('PESAPAL_IPN_TYPE', 'POST');

// Enforce SSL verification in courseion
define('DISABLE_SSL_VERIFICATION', true);

// Secure download settings
// Generate a strong random string in courseion and keep it private
define('DOWNLOAD_TOKEN_SECRET', 'change-this-to-a-long-random-secret');
define('MAX_DOWNLOADS_PER_ORDER', 5);

// Logging
define('PESAPAL_LOG_FILE', __DIR__ . '/logs/pesapal.log');

// Token cache
define('PESAPAL_TOKEN_CACHE_FILE', __DIR__ . '/cache/pesapal_token.json');

// Minimal admin protection token for refund/cancel script (change in courseion)
define('ADMIN_ACTION_TOKEN', 'change-this-admin-token');

// Google OAuth 2.0 (set your values)
define('GOOGLE_CLIENT_ID', '489239194759-r57b8q75ku4cii5d0044v25tf88hr7om.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');

// Canonical base URL for building absolute redirects (use your preferred host, including www)
// This ensures OAuth redirect_uri always matches what is registered in Google Cloud Console.
define('APP_BASE_URL', 'https://www.akdownloads.com/');



// SMTP settings (Secure SSL/TLS)
define('SMTP_HOST', 'mail.wolinet.com');
define('SMTP_PORT', 587); // Submission port with STARTTLS (TLS)
define('SMTP_USERNAME', 'ak@wolinet.com');
define('SMTP_PASSWORD', '6aO{l;xm{mwaHK@y');
define('SMTP_SECURE', 'tls'); // use 'tls' for 587; use 'ssl' only for 465
define('SMTP_FROM_EMAIL', 'ak@wolinet.com');
define('SMTP_FROM_NAME', 'AK23 Studio Kits');
// 0 (off), 1 (client msgs), 2 (client/server msgs). Use >0 for debugging only
define('SMTP_DEBUG', 0);


