<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

require_once '../includes/db_config.php';

// Action dispatcher
$action = $_POST['action'] ?? '';

if ($action === 'attach_promo_video') {
    header('Content-Type: application/json');
    $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $url = isset($_POST['url']) ? trim((string)$_POST['url']) : '';
    if ($course_id <= 0 || $url === '') {
        echo json_encode(['success' => false, 'message' => 'Missing course or URL.']);
        exit();
    }
    // Normalize YouTube/Vimeo URL to embed
    $normalized = $url;
    $host = '';
    $parts = @parse_url($url);
    if ($parts && !empty($parts['host'])) { $host = strtolower($parts['host']); }
    $allowed = false;
    if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtu.be') !== false) {
        // Extract video id and keep remaining params (except v)
        $query = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $query);
        $vid = '';
        if (isset($query['v'])) { $vid = $query['v']; unset($query['v']); }
        elseif (!empty($parts['path']) && preg_match('~^/([A-Za-z0-9_-]{6,})~', $parts['path'], $m)) { $vid = $m[1]; }
        if ($vid !== '') {
            $normalized = 'https://www.youtube.com/embed/' . $vid;
            $qs = http_build_query($query);
            if ($qs !== '') $normalized .= '?' . $qs;
            $allowed = true;
        }
    } elseif (strpos($host, 'vimeo.com') !== false) {
        // Accept already-embed vimeo or convert player url if needed
        if (!empty($parts['path']) && preg_match('~^/(?:video/)?([0-9]+)~', $parts['path'], $m)) {
            $vid = $m[1];
            $normalized = 'https://player.vimeo.com/video/' . $vid;
            $allowed = true;
        } elseif (strpos($host, 'player.vimeo.com') !== false) {
            $allowed = true;
        }
    }
    if (!$allowed) {
        echo json_encode(['success' => false, 'message' => 'Only YouTube or Vimeo URLs are allowed.']);
        exit();
    }
    try {
        // Insert into medias
        $stmt = $pdo->prepare("INSERT INTO medias (courses_id, file_type, value) VALUES (?, 'video', ?)");
        $stmt->execute([$course_id, $normalized]);
        // Update course promo_video if empty
        $pdo->prepare("UPDATE courses SET promo_video = COALESCE(NULLIF(promo_video,''), ?) WHERE id = ?")
            ->execute([$normalized, $course_id]);
        echo json_encode(['success' => true, 'embed' => $normalized]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    }
    exit();
}

// Helper function to generate a share link URL
function getShareLink($token) {
    return ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/downloads.php?share_token=' . urlencode($token);
}

// Fallback continues below

if ($action === 'generate_share_link') {
    $media_id = isset($_POST['media_id']) ? intval($_POST['media_id']) : 0;
    if ($media_id > 0) {
        $token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        $stmt = $pdo->prepare("INSERT INTO media_share_tokens (media_id, token, expires_at) VALUES (?, ?, ?)");
        if ($stmt->execute([$media_id, $token, $expires_at])) {
            echo json_encode(['success' => true, 'link' => getShareLink($token)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to generate link in database.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid media ID.']);
    }
    exit();
}

if ($action === 'delete_file') {
    $id = $_POST['id'] ?? null;
    $type = $_POST['type'] ?? '';

    if ($type === 'db') {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE id = ? AND type = 'media'");
        $stmt->execute([$id]);
        $file_path = $stmt->fetchColumn();
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        $stmt = $pdo->prepare("DELETE FROM settings WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } elseif ($type === 'local') {
        $uploads_dir = realpath(__DIR__ . '/../uploads');
        $file_path = realpath($uploads_dir . '/' . basename($id));
        if ($file_path && strpos($file_path, $uploads_dir) === 0 && file_exists($file_path)) {
            unlink($file_path);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found or invalid path.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    }
    exit();
}

if ($action === 'rename_file') {
    $id = $_POST['id'] ?? null;
    $type = $_POST['type'] ?? '';
    $new_name = $_POST['new_name'] ?? '';

    if (empty($new_name)) {
        echo json_encode(['success' => false, 'message' => 'New name cannot be empty.']);
        exit();
    }

    if ($type === 'db') {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE id = ? AND type = 'media'");
        $stmt->execute([$id]);
        $old_path = $stmt->fetchColumn();
        if ($old_path) {
            $new_path = dirname($old_path) . '/' . basename($new_name);
            if (rename($old_path, $new_path)) {
                $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE id = ?");
                $stmt->execute([$new_path, $id]);
                echo json_encode(['success' => true, 'newName' => basename($new_name)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to rename file on server.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found in database.']);
        }
    } elseif ($type === 'local') {
        $uploads_dir = realpath(__DIR__ . '/../uploads');
        $old_path = realpath($uploads_dir . '/' . basename($id));
        if ($old_path && strpos($old_path, $uploads_dir) === 0 && file_exists($old_path)) {
            $new_path = dirname($old_path) . '/' . basename($new_name);
            if (rename($old_path, $new_path)) {
                echo json_encode(['success' => true, 'newName' => basename($new_name)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to rename file.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found or invalid path.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    }
    exit();
}

// Fallback for invalid actions
http_response_code(400);
echo json_encode(['error' => 'Invalid action specified.']);
