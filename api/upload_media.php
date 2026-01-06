<?php
// Secure media upload endpoint for TinyMCE file picker
// Accepts audio/video files, validates size and MIME, stores under uploads/media/YYYY/MM

// Basic bootstrap
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow logged in users (admin or user). Adjust to match your auth variables.
$allowed = false;
if (!empty($_SESSION['admin_logged_in'])) { $allowed = true; }
if (!empty($_SESSION['user_logged_in'])) { $allowed = true; }
if (!empty($_SESSION['user_id'])) { $allowed = true; }

if (!$allowed) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([ 'error' => 'Unauthorized' ]);
    exit;
}

// Limits
$maxBytes = 50 * 1024 * 1024; // 50 MB
$allowedMimes = [
    // Audio
    'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-wav', 'audio/flac',
    // Video
    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'
];

// Helper to build a safe path
function ensure_dir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

// Validate file input
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([ 'error' => 'No file uploaded' ]);
    exit;
}

$file = $_FILES['file'];
if (!empty($file['error'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([ 'error' => 'Upload error: ' . $file['error'] ]);
    exit;
}

if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([ 'error' => 'Invalid upload' ]);
    exit;
}

if ($file['size'] > $maxBytes) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode([ 'error' => 'File too large. Max 50MB' ]);
    exit;
}

// Validate MIME using finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(415);
    header('Content-Type: application/json');
    echo json_encode([ 'error' => 'Unsupported media type: ' . $mime ]);
    exit;
}

// Map extension by MIME
$extMap = [
    'audio/mpeg' => 'mp3',
    'audio/mp3' => 'mp3',
    'audio/mp4' => 'm4a',
    'audio/aac' => 'aac',
    'audio/ogg' => 'ogg',
    'audio/webm' => 'webm',
    'audio/wav' => 'wav',
    'audio/x-wav' => 'wav',
    'audio/flac' => 'flac',
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
    'video/ogg' => 'ogv',
    'video/quicktime' => 'mov',
];
$ext = isset($extMap[$mime]) ? $extMap[$mime] : pathinfo($file['name'], PATHINFO_EXTENSION);
$ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext));
if ($ext === '') { $ext = 'bin'; }

$ym = date('Y/m');
$baseDir = dirname(__DIR__);
$targetDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $ym;
ensure_dir($targetDir);

// Optional: drop a .htaccess to prevent PHP execution in uploads (Apache environments)
$htaccessDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads';
if (!file_exists($htaccessDir . DIRECTORY_SEPARATOR . '.htaccess')) {
    @file_put_contents($htaccessDir . DIRECTORY_SEPARATOR . '.htaccess', "Options -Indexes\n<FilesMatch \.ph(p[0-9]?|t|tml)$>\n  Deny from all\n</FilesMatch>\n");
}

$filename = bin2hex(random_bytes(12)) . '.' . $ext;
$targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([ 'error' => 'Failed to save file' ]);
    exit;
}

// Build public URL
require_once __DIR__ . '/../includes/base.php'; // to get $base
$publicUrl = rtrim($base, '/') . '/uploads/media/' . $ym . '/' . $filename;

header('Content-Type: application/json');
echo json_encode([ 'location' => $publicUrl, 'mime' => $mime ]);
