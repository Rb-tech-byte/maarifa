<?php
// learning_player.php - Video Learning Player with Access Control
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/sanitize_html.php';

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if (!$course_id) {
    header('Location: index.php');
    exit;
}

// Get course details
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    header('Location: index.php');
    exit;
}

// Check access: enrollment OR completed order/payment
$enrolled = false;
$enrollment = null;
$completedOrder = null;
$isFree = ((float)$course['price'] ?? 0) == 0;

if ($user_id) {
    // Check enrollment first
    $enrolled = mz_is_student_enrolled($user_id, $course_id);
    $enrollment = mz_get_enrollment($user_id, $course_id);
    
    // Also check for completed order/payment (for paid courses)
    if (!$enrolled) {
        $orderStmt = $pdo->prepare("
            SELECT o.*, p.status as payment_status 
            FROM orders o 
            LEFT JOIN payments p ON p.order_id = o.id 
            WHERE o.user_id = ? AND o.course_id = ? 
            AND o.status = 'completed' 
            AND (p.status = 'completed' OR o.status = 'completed')
            ORDER BY o.created_at DESC 
            LIMIT 1
        ");
        $orderStmt->execute([$user_id, $course_id]);
        $completedOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        // If order is completed, create enrollment automatically if it doesn't exist
        if ($completedOrder && !$enrollment) {
            try {
                $enrollStmt = $pdo->prepare("
                    INSERT INTO enrollments (user_id, course_id, status, payment_status, created_at)
                    VALUES (?, ?, 'active', 'paid', NOW())
                    ON DUPLICATE KEY UPDATE status = 'active', payment_status = 'paid', updated_at = NOW()
                ");
                $enrollStmt->execute([$user_id, $course_id]);
                $enrollment = mz_get_enrollment($user_id, $course_id);
                $enrolled = true;
            } catch (Exception $e) {
                // Enrollment might already exist, just fetch it
                $enrollment = mz_get_enrollment($user_id, $course_id);
                if ($enrollment) {
                    $enrolled = true;
                }
            }
        }
    }
    
    // User has access if enrolled OR has completed order/payment
    $hasAccess = $enrolled || !empty($completedOrder);
} else {
    // Free courses can be accessed after login
    if ($isFree) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    $hasAccess = false;
}

// For display purposes, use enrolled flag
$enrolled = isset($hasAccess) ? $hasAccess : false;

// Get curriculum
$modules = mz_get_course_curriculum($course_id);

// Get current lesson
$currentLesson = null;
$currentModule = null;

if ($lesson_id) {
    foreach ($modules as $module) {
        foreach ($module['lessons'] as $lesson) {
            if ($lesson['id'] == $lesson_id) {
                $currentLesson = $lesson;
                $currentModule = $module;
                break 2;
            }
        }
    }
}

// If no lesson specified, get first accessible lesson
if (!$currentLesson) {
    foreach ($modules as $module) {
        foreach ($module['lessons'] as $lesson) {
            $isPreview = !empty($lesson['is_preview']);
            $canAccess = $enrolled || !empty($completedOrder) || $isPreview || ($isFree && $user_id > 0);
            if ($canAccess) {
                $currentLesson = $lesson;
                $currentModule = $module;
                break 2;
            }
        }
    }
}

// Check access to current lesson
$canAccessCurrentLesson = false;
if ($currentLesson) {
    $isPreview = !empty($currentLesson['is_preview']);
    // Access if: enrolled, has completed payment, is preview lesson, or course is free (and logged in)
    $canAccessCurrentLesson = $enrolled || !empty($completedOrder) || $isPreview || ($isFree && $user_id > 0);
}

// Helper function to convert video URLs to embed format with enhanced parameters
function convertToEmbedUrl($url, $autoplay = false) {
    if (empty($url)) return '';
    
    // YouTube
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $params = 'rel=0&modestbranding=1&playsinline=1&enablejsapi=1&origin=' . urlencode($_SERVER['HTTP_HOST'] ?? '');
        if ($autoplay) $params .= '&autoplay=1&mute=1';
        return 'https://www.youtube.com/embed/' . $matches[1] . '?' . $params;
    }
    
    // Vimeo
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
        $params = 'responsive=1&title=0&byline=0&portrait=0';
        if ($autoplay) $params .= '&autoplay=1&muted=1';
        return 'https://player.vimeo.com/video/' . $matches[1] . '?' . $params;
    }
    
    // Google Drive
    if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return 'https://drive.google.com/file/d/' . $matches[1] . '/preview';
    }
    
    // Direct video URL
    if (preg_match('/\.(mp4|webm|ogg|mov|m3u8)(\?|$)/i', $url)) {
        return $url;
    }
    
    return $url;
}

$pageTitle = $course['title'] . ' - Learning';
include 'includes/header.php';
?>

<style>
:root {
    --primary-color: #007bff;
    --success-color: #28a745;
    --dark-text: #212529;
    --medium-text: #495057;
    --light-text: #6c757d;
    --bg-light: #f8f9fa;
    --bg-white: #ffffff;
    --border-color: #dee2e6;
}

.learning-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
    background: #f5f5f5;
    min-height: calc(100vh - 80px);
}

.player-section {
    background: #000;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 1.5rem;
    position: relative;
    padding-top: 56.25%; /* 16:9 aspect ratio */
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.player-section iframe,
.player-section video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
}

.locked-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    z-index: 10;
    border-radius: 12px;
}

.locked-overlay i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.9;
    color: #ffffff;
}

.locked-overlay h3 {
    color: #ffffff;
    font-weight: 600;
    margin-bottom: 1rem;
}

.locked-overlay p {
    color: #ffffff;
}

.locked-overlay a {
    color: #ffffff !important;
    text-decoration: underline !important;
}

.curriculum-sidebar {
    background: var(--bg-white);
    border-radius: 12px;
    padding: 1.25rem;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.curriculum-sidebar h4 {
    color: var(--dark-text);
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--border-color);
}

.module-item {
    margin-bottom: 0.75rem;
}

.module-header {
    font-weight: 600;
    padding: 0.75rem 1rem;
    background: var(--bg-light);
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--dark-text);
    transition: all 0.2s ease;
    border: 1px solid var(--border-color);
}

.module-header:hover {
    background: #e9ecef;
    border-color: var(--primary-color);
}

.module-header i {
    color: var(--medium-text);
}

.module-header .fa-chevron-down,
.module-header .fa-chevron-up {
    color: var(--light-text);
    font-size: 0.85rem;
}

.lesson-item {
    padding: 0.875rem 1rem;
    margin: 0.25rem 0;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s ease;
    color: var(--dark-text);
    background: transparent;
    border: 1px solid transparent;
}

.lesson-item:hover {
    background: #f0f0f0;
    border-color: var(--border-color);
}

.lesson-item.active {
    background: var(--primary-color);
    color: #ffffff;
    border-color: var(--primary-color);
    box-shadow: 0 2px 6px rgba(0,123,255,0.3);
}

.lesson-item.active i {
    color: #ffffff;
}

.lesson-item.locked {
    opacity: 0.5;
    cursor: not-allowed;
    color: var(--light-text);
}

.lesson-item.locked i {
    color: var(--light-text);
}

.lesson-item.preview {
    border-left: 4px solid var(--success-color);
    padding-left: calc(1rem - 4px);
}

.lesson-item.preview i {
    color: var(--success-color);
}

.lesson-item span {
    color: inherit;
}

.lesson-duration {
    font-size: 0.8rem;
    color: var(--light-text);
    margin-left: auto;
    font-weight: 500;
}

.lesson-item.active .lesson-duration {
    color: rgba(255, 255, 255, 0.9);
}

.lesson-item .badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    font-weight: 600;
}

.course-info-card {
    background: var(--bg-white);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.course-info-card h2,
.course-info-card h3 {
    color: var(--dark-text);
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.course-info-card h2 {
    font-size: 1.5rem;
}

.course-info-card h3 {
    font-size: 1.25rem;
}

.course-info-card p {
    color: var(--medium-text);
    margin-bottom: 0.5rem;
}

.course-info-card .text-muted {
    color: var(--light-text) !important;
}

.course-info-card small {
    color: var(--medium-text);
    font-weight: 600;
}

.progress-bar {
    height: 8px;
    background: var(--bg-light);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 0.75rem;
    border: 1px solid var(--border-color);
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success-color), #34ce57);
    transition: width 0.4s ease;
    border-radius: 4px;
}

/* Text content styling */
.player-section .p-4 {
    background: var(--bg-white);
    color: var(--dark-text);
}

.player-section .p-4 h3 {
    color: var(--dark-text);
    font-weight: 700;
}

.player-section .p-4 .text-muted {
    color: var(--light-text) !important;
}

/* Text content wrapper */
.text-content-wrapper {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--bg-white);
    overflow-y: auto;
    padding: 2rem;
}

.text-content-header {
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}

.text-content-header h3 {
    color: var(--dark-text);
    font-weight: 700;
    font-size: 1.5rem;
    margin: 0;
}

.text-content-body {
    color: var(--dark-text);
    line-height: 1.8;
    font-size: 1rem;
}

.text-content-body p {
    margin-bottom: 1rem;
    color: var(--dark-text);
}

.text-content-body h1,
.text-content-body h2,
.text-content-body h3,
.text-content-body h4 {
    color: var(--dark-text);
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

/* No content overlay */
.no-content-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-white);
    color: var(--medium-text);
}

.no-content-overlay i {
    font-size: 4rem;
    color: var(--light-text);
    margin-bottom: 1rem;
}

.no-content-overlay h4 {
    color: var(--dark-text);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.no-content-overlay p {
    color: var(--light-text);
    margin: 0;
}

/* Locked overlay improvements */
.locked-actions {
    margin-top: 1rem;
}

.locked-actions .btn {
    min-width: 200px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    transition: all 0.3s ease;
}

.locked-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,123,255,0.4);
}

/* Video player enhancements */
.video-iframe,
.video-player {
    border-radius: 12px;
}

.video-player {
    background: #000;
}

/* Loading state */
.player-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #ffffff;
    z-index: 5;
}

.player-loading .spinner-border {
    width: 3rem;
    height: 3rem;
    border-width: 0.3rem;
}

/* Scrollbar styling */
.curriculum-sidebar::-webkit-scrollbar {
    width: 8px;
}

.curriculum-sidebar::-webkit-scrollbar-track {
    background: var(--bg-light);
    border-radius: 4px;
}

.curriculum-sidebar::-webkit-scrollbar-thumb {
    background: var(--light-text);
    border-radius: 4px;
}

.curriculum-sidebar::-webkit-scrollbar-thumb:hover {
    background: var(--medium-text);
}

/* Ensure all text is visible */
.curriculum-sidebar * {
    color: inherit;
}

.module-header span {
    color: var(--dark-text);
}

.lesson-item .flex-grow-1 {
    color: inherit;
    font-weight: 500;
}

/* Lesson Navigation */
.lesson-navigation {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.btn-nav {
    flex: 1;
    font-weight: 600;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-nav:hover:not(.disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.btn-nav.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsive improvements */
@media (max-width: 991px) {
    .learning-container {
        padding: 0.5rem;
    }
    
    .player-section {
        margin-bottom: 1rem;
    }
    
    .curriculum-sidebar {
        max-height: 500px;
        margin-top: 1rem;
    }
    
    .course-info-card {
        padding: 1rem;
    }
    
    .lesson-navigation {
        flex-direction: column;
    }
    
    .btn-nav {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}

@media (max-width: 576px) {
    .course-info-card h2 {
        font-size: 1.25rem;
    }
    
    .course-info-card h3 {
        font-size: 1.1rem;
    }
    
    .text-content-wrapper {
        padding: 1rem;
    }
    
    .locked-overlay h3 {
        font-size: 1.25rem;
    }
    
    .locked-overlay i {
        font-size: 3rem;
    }
}
</style>

<div class="learning-container">
    <div class="row g-3">
        <!-- Main Player Area -->
        <div class="col-lg-8">
            <!-- Course Info -->
            <div class="course-info-card">
                <h2 class="h4 mb-2"><?= htmlspecialchars($course['title']) ?></h2>
                <?php if ($enrollment): ?>
                    <div class="d-flex align-items-center gap-3">
                        <small class="text-muted">Progress: <?= number_format($enrollment['progress'], 1) ?>%</small>
                        <div class="progress-bar flex-grow-1">
                            <div class="progress-fill" style="width: <?= min(100, max(0, (float)$enrollment['progress'])) ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Video Player -->
            <div class="player-section" id="playerContainer">
                <?php if ($currentLesson && $canAccessCurrentLesson): ?>
                    <?php
                    $videoUrl = convertToEmbedUrl($currentLesson['video_url'] ?? '', true);
                    $isYouTube = strpos($videoUrl, 'youtube.com') !== false;
                    $isVimeo = strpos($videoUrl, 'vimeo.com') !== false;
                    $isGoogleDrive = strpos($videoUrl, 'drive.google.com') !== false;
                    $isDirectVideo = preg_match('/\.(mp4|webm|ogg|mov|m3u8)(\?|$)/i', $currentLesson['video_url'] ?? '');
                    $playerId = 'videoPlayer_' . $currentLesson['id'];
                    ?>
                    
                    <?php if ($isYouTube || $isVimeo || $isGoogleDrive): ?>
                        <iframe id="<?= $playerId ?>" 
                                src="<?= htmlspecialchars($videoUrl) ?>" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" 
                                allowfullscreen
                                loading="eager"
                                class="video-iframe"></iframe>
                    <?php elseif ($isDirectVideo): ?>
                        <video id="<?= $playerId ?>" 
                               controls 
                               autoplay 
                               playsinline
                               preload="metadata"
                               class="video-player">
                            <source src="<?= htmlspecialchars($videoUrl) ?>" type="video/mp4">
                            <source src="<?= htmlspecialchars($videoUrl) ?>" type="video/webm">
                            Your browser does not support the video tag.
                        </video>
                    <?php elseif ($currentLesson['content_type'] === 'text'): ?>
                        <div class="text-content-wrapper">
                            <div class="text-content-header">
                                <h3><?= htmlspecialchars($currentLesson['title']) ?></h3>
                            </div>
                            <div class="text-content-body">
                                <?= sanitize_html($currentLesson['text_content'] ?? '') ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-content-overlay">
                            <i class="fas fa-video"></i>
                            <h4>Content Not Available</h4>
                            <p>This lesson doesn't have any content yet.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="locked-overlay">
                        <i class="fas fa-lock"></i>
                        <h3>This lesson is locked</h3>
                        <p class="text-center px-4 mb-3">
                            <?php if (!$user_id): ?>
                                Please login to access this course content.
                            <?php elseif (!$enrolled && empty($completedOrder)): ?>
                                <?php if ($isFree): ?>
                                    Enroll now to unlock all course content.
                                <?php else: ?>
                                    Purchase this course to unlock all lessons.
                                <?php endif; ?>
                            <?php else: ?>
                                Please complete your enrollment to access this content.
                            <?php endif; ?>
                        </p>
                        <div class="locked-actions">
                            <?php if (!$user_id): ?>
                                <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Continue
                                </a>
                            <?php elseif (!$enrolled && empty($completedOrder)): ?>
                                <a href="course-details.php?slug=<?= htmlspecialchars($course['slug']) ?>" class="btn btn-primary btn-lg">
                                    <i class="fas fa-<?= $isFree ? 'user-plus' : 'shopping-cart' ?> me-2"></i>
                                    <?= $isFree ? 'Enroll for Free' : 'Purchase Course' ?>
                                </a>
                            <?php else: ?>
                                <a href="course-details.php?slug=<?= htmlspecialchars($course['slug']) ?>" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-left me-2"></i>Go to Course Page
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Lesson Info & Navigation -->
            <?php if ($currentLesson): ?>
                <div class="course-info-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="flex-grow-1">
                            <h3 class="h5 mb-2"><?= htmlspecialchars($currentLesson['title']) ?></h3>
                            <?php if ($currentModule): ?>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-folder me-1"></i><?= htmlspecialchars($currentModule['title']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($currentLesson['duration'])): ?>
                                <p class="text-muted small mb-0">
                                    <i class="fas fa-clock me-1"></i><?= htmlspecialchars($currentLesson['duration']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Lesson Navigation -->
                    <div class="lesson-navigation">
                        <?php
                        // Find previous and next lessons
                        $prevLesson = null;
                        $nextLesson = null;
                        $foundCurrent = false;
                        
                        foreach ($modules as $module) {
                            foreach ($module['lessons'] as $idx => $lesson) {
                                $isPreview = !empty($lesson['is_preview']);
                                $canAccess = $enrolled || !empty($completedOrder) || $isPreview || ($isFree && $user_id > 0);
                                
                                if ($lesson['id'] == $currentLesson['id']) {
                                    $foundCurrent = true;
                                    // Find previous
                                    if ($idx > 0) {
                                        $prev = $module['lessons'][$idx - 1];
                                        $prevPreview = !empty($prev['is_preview']);
                                        $prevCanAccess = $enrolled || !empty($completedOrder) || $prevPreview || ($isFree && $user_id > 0);
                                        if ($prevCanAccess) {
                                            $prevLesson = $prev;
                                        }
                                    } else {
                                        // Check previous module
                                        $moduleIdx = array_search($module, $modules);
                                        if ($moduleIdx > 0) {
                                            $prevModule = $modules[$moduleIdx - 1];
                                            if (!empty($prevModule['lessons'])) {
                                                $lastLesson = end($prevModule['lessons']);
                                                $lastPreview = !empty($lastLesson['is_preview']);
                                                $lastCanAccess = $enrolled || !empty($completedOrder) || $lastPreview || ($isFree && $user_id > 0);
                                                if ($lastCanAccess) {
                                                    $prevLesson = $lastLesson;
                                                }
                                            }
                                        }
                                    }
                                } elseif ($foundCurrent && $canAccess) {
                                    $nextLesson = $lesson;
                                    break 2;
                                }
                            }
                        }
                        ?>
                        
                        <div class="d-flex gap-2 justify-content-between">
                            <?php if ($prevLesson): ?>
                                <a href="learning_player.php?course_id=<?= $course_id ?>&lesson_id=<?= $prevLesson['id'] ?>" 
                                   class="btn btn-outline-primary btn-nav">
                                    <i class="fas fa-chevron-left me-2"></i>Previous Lesson
                                </a>
                            <?php else: ?>
                                <span class="btn btn-outline-secondary btn-nav disabled">
                                    <i class="fas fa-chevron-left me-2"></i>Previous Lesson
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($nextLesson): ?>
                                <a href="learning_player.php?course_id=<?= $course_id ?>&lesson_id=<?= $nextLesson['id'] ?>" 
                                   class="btn btn-primary btn-nav">
                                    Next Lesson<i class="fas fa-chevron-right ms-2"></i>
                                </a>
                            <?php else: ?>
                                <span class="btn btn-outline-secondary btn-nav disabled">
                                    Next Lesson<i class="fas fa-chevron-right ms-2"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Curriculum Sidebar -->
        <div class="col-lg-4">
            <div class="curriculum-sidebar">
                <h4 class="h6 mb-3 fw-bold">Course Curriculum</h4>
                
                <?php foreach ($modules as $module): ?>
                    <div class="module-item">
                        <div class="module-header" onclick="toggleModule(this)">
                            <span>
                                <i class="fas fa-folder me-2"></i><?= htmlspecialchars($module['title']) ?>
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="lessons-list" style="display: none;">
                            <?php foreach ($module['lessons'] as $lesson): ?>
                                <?php
                                $isPreview = !empty($lesson['is_preview']);
                                $canAccess = $enrolled || !empty($completedOrder) || $isPreview || ($isFree && $user_id > 0);
                                $isActive = $currentLesson && $currentLesson['id'] == $lesson['id'];
                                ?>
                                <div class="lesson-item <?= $isActive ? 'active' : '' ?> <?= !$canAccess ? 'locked' : '' ?> <?= $isPreview ? 'preview' : '' ?>"
                                     onclick="<?= $canAccess ? "loadLesson({$lesson['id']})" : '' ?>">
                                    <i class="fas fa-<?= $canAccess ? 'play-circle' : 'lock' ?>"></i>
                                    <span class="flex-grow-1"><?= htmlspecialchars($lesson['title']) ?></span>
                                    <?php if (!empty($lesson['duration'])): ?>
                                        <span class="lesson-duration"><?= htmlspecialchars($lesson['duration']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($isPreview): ?>
                                        <span class="badge bg-success ms-2">Preview</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced module toggle with smooth animation
function toggleModule(header) {
    const list = header.nextElementSibling;
    const icon = header.querySelector('.fa-chevron-down, .fa-chevron-up');
    
    if (list.style.display === 'none' || !list.style.display) {
        list.style.display = 'block';
        list.style.opacity = '0';
        list.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            list.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            list.style.opacity = '1';
            list.style.transform = 'translateY(0)';
        }, 10);
        
        if (icon) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }
    } else {
        list.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        list.style.opacity = '0';
        list.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            list.style.display = 'none';
        }, 300);
        
        if (icon) {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }
}

// Load lesson with loading state
function loadLesson(lessonId) {
    const playerContainer = document.getElementById('playerContainer');
    if (playerContainer) {
        // Show loading state
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'player-loading';
        loadingDiv.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
        playerContainer.appendChild(loadingDiv);
    }
    
    window.location.href = 'learning_player.php?course_id=<?= $course_id ?>&lesson_id=' + lessonId;
}

// Video player event handlers for direct video elements
function setupVideoPlayer() {
    const videoPlayer = document.querySelector('.video-player');
    if (videoPlayer) {
        // Track play progress
        videoPlayer.addEventListener('timeupdate', function() {
            const progress = (videoPlayer.currentTime / videoPlayer.duration) * 100;
            // Could send progress to server here for tracking
        });
        
        // Handle video errors
        videoPlayer.addEventListener('error', function(e) {
            console.error('Video playback error:', e);
            // Show error message to user
        });
        
        // Optimize loading
        videoPlayer.addEventListener('canplay', function() {
            // Video is ready to play
        });
    }
}

// Auto-expand module with current lesson
document.addEventListener('DOMContentLoaded', function() {
    // Auto-expand active module
    const activeLesson = document.querySelector('.lesson-item.active');
    if (activeLesson) {
        const module = activeLesson.closest('.module-item');
        if (module) {
            const header = module.querySelector('.module-header');
            if (header) {
                // Expand immediately without animation
                const list = header.nextElementSibling;
                if (list) {
                    list.style.display = 'block';
                    list.style.opacity = '1';
                    list.style.transform = 'translateY(0)';
                    const icon = header.querySelector('.fa-chevron-down, .fa-chevron-up');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                }
            }
        }
    }
    
    // Setup video player
    setupVideoPlayer();
    
    // Smooth scroll to active lesson
    if (activeLesson) {
        setTimeout(() => {
            activeLesson.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 300);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Space bar to play/pause (only when video is focused)
        if (e.code === 'Space' && document.activeElement.tagName === 'VIDEO') {
            e.preventDefault();
            const video = document.activeElement;
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        }
        
        // Arrow keys for navigation (when not in input fields)
        if (!['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            if (e.key === 'ArrowRight') {
                // Next lesson
                const nextLesson = getNextLesson();
                if (nextLesson) loadLesson(nextLesson);
            } else if (e.key === 'ArrowLeft') {
                // Previous lesson
                const prevLesson = getPreviousLesson();
                if (prevLesson) loadLesson(prevLesson);
            }
        }
    });
});

// Get next accessible lesson
function getNextLesson() {
    const activeLesson = document.querySelector('.lesson-item.active');
    if (!activeLesson) return null;
    
    const allLessons = Array.from(document.querySelectorAll('.lesson-item:not(.locked)'));
    const currentIndex = allLessons.indexOf(activeLesson);
    
    if (currentIndex < allLessons.length - 1) {
        const nextLesson = allLessons[currentIndex + 1];
        const lessonId = nextLesson.getAttribute('onclick')?.match(/\d+/)?.[0];
        return lessonId ? parseInt(lessonId) : null;
    }
    
    return null;
}

// Get previous accessible lesson
function getPreviousLesson() {
    const activeLesson = document.querySelector('.lesson-item.active');
    if (!activeLesson) return null;
    
    const allLessons = Array.from(document.querySelectorAll('.lesson-item:not(.locked)'));
    const currentIndex = allLessons.indexOf(activeLesson);
    
    if (currentIndex > 0) {
        const prevLesson = allLessons[currentIndex - 1];
        const lessonId = prevLesson.getAttribute('onclick')?.match(/\d+/)?.[0];
        return lessonId ? parseInt(lessonId) : null;
    }
    
    return null;
}

// Track lesson completion (if needed)
function markLessonComplete(lessonId) {
    // Could implement AJAX call here to mark lesson as complete
    console.log('Lesson completed:', lessonId);
}
</script>

<?php include 'includes/footer.php'; ?>

