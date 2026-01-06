<?php
// course-details.php -- Modern LMS Course Details Page
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/sanitize_html.php';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$course = null;
$modules = [];
$instructor = null;
$enrolled = false;
$enrollment = null;
$error = '';
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($slug) {
    $course = mz_get_course_by_slug($slug, true);
    if ($course) {
        $modules = mz_get_course_curriculum($course['id']);
        // Instructor info (if assigned)
        if (!empty($course['created_by'])) {
        $stmt = $pdo->prepare('SELECT i.*, u.name AS user_name, u.avatar FROM instructors i JOIN users u ON i.user_id = u.id WHERE i.id = ?');
        $stmt->execute([$course['created_by']]);
        $instructor = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        // Check access: enrollment OR completed order/payment
        $hasAccess = false;
        $enrollment = null;
        $completedOrder = null;
        
        if ($user_id) {
            // Check enrollment first
            $enrolled = mz_is_student_enrolled($user_id, $course['id']);
            $enrollment = mz_get_enrollment($user_id, $course['id']);
            
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
                $orderStmt->execute([$user_id, $course['id']]);
                $completedOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);
                
                // If order is completed, create enrollment automatically if it doesn't exist
                if ($completedOrder && !$enrollment) {
                    try {
                        $enrollStmt = $pdo->prepare("
                            INSERT INTO enrollments (user_id, course_id, status, payment_status, created_at)
                            VALUES (?, ?, 'active', 'paid', NOW())
                            ON DUPLICATE KEY UPDATE status = 'active', payment_status = 'paid', updated_at = NOW()
                        ");
                        $enrollStmt->execute([$user_id, $course['id']]);
                        $enrollment = mz_get_enrollment($user_id, $course['id']);
                        $enrolled = true;
                    } catch (Exception $e) {
                        // Enrollment might already exist, just fetch it
                        $enrollment = mz_get_enrollment($user_id, $course['id']);
                        if ($enrollment) {
                            $enrolled = true;
                        }
                    }
                }
            }
            
            // User has access if enrolled OR has completed order/payment
            $hasAccess = $enrolled || !empty($completedOrder);
        }
        
        // For display purposes, use enrolled flag
        $enrolled = $hasAccess;
        
        // Helper function to convert YouTube/Vimeo/Google Drive URLs to embed format
        function convertToEmbedUrl($url) {
            if (empty($url)) return '';
            // YouTube
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
                return 'https://www.youtube.com/embed/' . $matches[1] . '?rel=0&modestbranding=1';
            }
            // Vimeo
            if (preg_match('/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/', $url, $matches)) {
                return 'https://player.vimeo.com/video/' . $matches[1] . '?title=0&byline=0&portrait=0';
            }
            // Google Drive
            if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
                return 'https://drive.google.com/file/d/' . $matches[1] . '/preview';
            }
            // Already an embed URL
            return $url;
        }
        
        $promo_video_embed = !empty($course['promo_video']) ? convertToEmbedUrl($course['promo_video']) : '';
        
        // Get additional course data from medias table
        $demo_audio_url = '';
        $file_url = '';
        $original_source = '';
        
        try {
            $mediaStmt = $pdo->prepare("SELECT file_type, value FROM medias WHERE courses_id = ? AND type = 'url' AND deleted_at IS NULL");
            $mediaStmt->execute([$course['id']]);
            $medias = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($medias as $media) {
                $ft = strtolower($media['file_type'] ?? '');
                $val = $media['value'] ?? '';
                if ($ft === 'wav' || $ft === 'audio') {
                    $demo_audio_url = $val;
                } elseif ($ft === 'zip') {
                    $file_url = $val;
                } elseif ($ft === 'pdf') {
                    $original_source = $val;
                }
            }
        } catch (Throwable $e) {
            // Continue without media data
        }
        
        // Calculate total course duration
        $total_duration = 0;
        foreach ($modules as $module) {
            foreach ($module['lessons'] as $lesson) {
                if (!empty($lesson['duration'])) {
                    $dur = preg_replace('/[^0-9]/', '', $lesson['duration']);
                    $total_duration += (int)$dur;
                }
            }
        }
    } else {
        $error = 'Course not found.';
    }
} else {
    $error = 'No course specified.';
}

$title = $course ? htmlspecialchars($course['title']) . ' - Course Details' : 'Course Details';
include 'includes/header.php';
?>

<style>
/* Compact Course Details Styles - Fit Screen, No Scroll */
* {
    box-sizing: border-box;
}

body {
    overflow-x: hidden;
}

.course-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem 0;
    position: relative;
    overflow: hidden;
    min-height: auto;
}

.course-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('<?= htmlspecialchars($course['cover_image'] ?? 'assets/images/ak.png') ?>') center/cover;
    opacity: 0.2;
    z-index: 0;
}

.course-hero-content {
    position: relative;
    z-index: 1;
}

.trailer-video-container {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    background: #000;
    height: 100%;
    min-height: 200px;
}

.trailer-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    z-index: 2;
}

.trailer-overlay:hover {
    background: rgba(0,0,0,0.2);
}

.trailer-overlay.hidden {
    display: none;
}

.play-button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #667eea;
    transition: transform 0.3s;
}

.trailer-overlay:hover .play-button {
    transform: scale(1.1);
}

.course-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin: 0.75rem 0;
    font-size: 0.85rem;
}

.course-stat-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.course-content-section {
    padding: 1rem 0;
}

.curriculum-module {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    overflow: hidden;
    transition: box-shadow 0.3s;
}

.curriculum-module:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.module-header {
    background: #f8f9fa;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e0e0e0;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.module-header:hover {
    background: #f0f0f0;
}

.module-content {
    max-height: 300px;
    overflow-y: auto;
}

.lesson-item {
    padding: 0.6rem 1rem;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: background 0.2s;
    font-size: 0.85rem;
}

.lesson-item:hover {
    background: #f8f9fa;
}

.lesson-item:last-child {
    border-bottom: none;
}

.lesson-locked {
    position: relative;
    opacity: 0.7;
}

.lock-icon {
    color: #999;
    font-size: 1rem;
}

.preview-badge {
    background: #28a745;
    color: white;
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    margin-left: auto;
}

.enroll-card {
    position: sticky;
    top: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.price-display {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0.5rem 0;
}

.instructor-card {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.instructor-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.card {
    margin-bottom: 1rem;
}

.card-body {
    padding: 1rem;
}

.course-description {
    font-size: 0.9rem;
    line-height: 1.5;
    max-height: 200px;
    overflow-y: auto;
}

.lead {
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.display-4 {
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
}

.h4 {
    font-size: 1.1rem;
    margin-bottom: 0.75rem;
}

.btn-lg {
    padding: 0.5rem 1.5rem;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .course-hero {
        padding: 1rem 0;
    }
    
    .course-stats {
        gap: 0.5rem;
        font-size: 0.75rem;
    }
    
    .price-display {
        font-size: 1.5rem;
    }
    
    .display-4 {
        font-size: 1.5rem;
    }
    
    .trailer-video-container {
        min-height: 180px;
    }
    
    .play-button {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
    
    .course-content-section {
        padding: 0.5rem 0;
    }
    
    .enroll-card {
        position: relative;
        top: 0;
        margin-top: 1rem;
    }
}

@media (max-width: 576px) {
    .course-hero {
        padding: 0.75rem 0;
    }
    
    .display-4 {
        font-size: 1.3rem;
    }
    
    .lead {
        font-size: 0.9rem;
    }
    
    .course-stats {
        font-size: 0.7rem;
    }
}
</style>

<?php if ($error): ?>
    <div class="container py-2">
        <div class="alert alert-danger text-center mb-0"><?= htmlspecialchars($error) ?></div>
    </div>
<?php elseif (!$course): ?>
    <div class="container py-2">
        <div class="alert alert-warning text-center mb-0">Course not found.</div>
    </div>
<?php else: ?>
    <!-- Hero Section with Trailer -->
    <section class="course-hero">
        <div class="container-fluid course-hero-content">
            <div class="container">
                <div class="row align-items-center g-2">
                    <div class="col-lg-7 col-md-6">
                        <div class="mb-2">
                            <?php
                            $catName = $course['category_name'] ?? '';
                            if ($catName !== ''):
                            ?>
                                <span class="badge bg-light text-dark me-1 mb-1"><?= htmlspecialchars($catName) ?></span>
                            <?php endif; ?>
                            <span class="badge bg-warning text-dark me-1 mb-1"><?= ucfirst($course['level']) ?></span>
                            <?php if (!empty($course['featured'])): ?>
                                <span class="badge bg-danger mb-1">Featured</span>
                            <?php endif; ?>
                        </div>
                        <h1 class="display-4 fw-bold mb-2"><?= htmlspecialchars($course['title']) ?></h1>
                        <?php if (!empty($course['short_description'])): ?>
                            <div class="lead mb-2"><?= sanitize_html($course['short_description']) ?></div>
                        <?php endif; ?>
                        
                        <div class="course-stats">
                            <?php if (!empty($course['total_modules']) || !empty($modules)): ?>
                                <div class="course-stat-item">
                                    <i class="fas fa-book-open"></i>
                                    <span><?= count($modules) ?> Module<?= count($modules) !== 1 ? 's' : '' ?></span>
                                </div>
                            <?php endif; ?>
                            <?php 
                            $totalLessons = 0;
                            foreach ($modules as $m) {
                                $totalLessons += count($m['lessons']);
                            }
                            if ($totalLessons > 0):
                            ?>
                                <div class="course-stat-item">
                                    <i class="fas fa-play-circle"></i>
                                    <span><?= $totalLessons ?> Lesson<?= $totalLessons !== 1 ? 's' : '' ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($course['duration']) || $total_duration > 0): ?>
                                <div class="course-stat-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?= !empty($course['duration']) ? htmlspecialchars($course['duration']) : ($total_duration > 0 ? $total_duration . ' min' : '') ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($course['language'])): ?>
                                <div class="course-stat-item">
                                    <i class="fas fa-language"></i>
                                    <span><?= htmlspecialchars($course['language']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-5 col-md-6">
                        <?php if (!empty($promo_video_embed)): ?>
                            <div class="trailer-video-container ratio ratio-16x9">
                                <div class="trailer-overlay" id="trailerOverlay" onclick="playTrailer()">
                                    <div class="play-button">
                                        <i class="fas fa-play"></i>
                                    </div>
                                </div>
                                <iframe id="trailerVideo" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="display: none;"></iframe>
                                <img src="<?= htmlspecialchars($course['cover_image'] ?? 'assets/images/ak.png') ?>" alt="Course Trailer" class="w-100 h-100" style="object-fit: cover;" id="trailerThumbnail">
                            </div>
                        <?php else: ?>
                            <div class="trailer-video-container ratio ratio-16x9">
                                <img src="<?= htmlspecialchars($course['cover_image'] ?? 'assets/images/ak.png') ?>" alt="<?= htmlspecialchars($course['title']) ?>" class="w-100 h-100" style="object-fit: cover;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content Section -->
    <section class="course-content-section">
        <div class="container-fluid">
            <div class="container">
                <div class="row g-2">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <!-- Course Description -->
                        <?php if (!empty($course['description'])): ?>
                            <div class="card border-0 shadow-sm mb-2">
                                <div class="card-body">
                                    <h2 class="h4 fw-bold mb-2">
                                        <i class="fas fa-info-circle me-2 text-primary"></i>About This Course
                                    </h2>
                                    <div class="course-description">
                                        <?= sanitize_html($course['description']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Course Curriculum -->
                        <div class="card border-0 shadow-sm mb-2">
                            <div class="card-body">
                                <h2 class="h4 fw-bold mb-2">
                                    <i class="fas fa-list-ul me-2 text-primary"></i>Course Curriculum
                                </h2>
                                <?php if (empty($modules)): ?>
                                    <div class="alert alert-info py-2 mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Course content is being prepared. Check back soon!
                                    </div>
                                <?php else: ?>
                                    <div class="curriculum-list">
                                        <?php 
                                        $lessonNumber = 1;
                                        foreach ($modules as $module): 
                                            $moduleLessons = $module['lessons'] ?? [];
                                        ?>
                                            <div class="curriculum-module">
                                                <div class="module-header" onclick="toggleModule(this)">
                                                    <div>
                                                        <strong><?= htmlspecialchars($module['title']) ?></strong>
                                                        <span class="text-muted small ms-2">
                                                            <?= count($moduleLessons) ?> lesson<?= count($moduleLessons) !== 1 ? 's' : '' ?>
                                                        </span>
                                                    </div>
                                                    <i class="fas fa-chevron-down"></i>
                                                </div>
                                                <div class="module-content" style="display: none;">
                                                    <?php foreach ($moduleLessons as $lesson): ?>
                                                        <?php
                                                        $isPreview = !empty($lesson['is_preview']);
                                                        $isFree = ((float)$course['price'] ?? 0) == 0;
                                                        // Access if: enrolled, has completed payment, is preview lesson, or course is free
                                                        $canAccess = $enrolled || !empty($completedOrder) || $isPreview || ($isFree && $user_id > 0);
                                                        $lessonVideoUrl = !empty($lesson['video_url']) ? convertToEmbedUrl($lesson['video_url']) : '';
                                                        ?>
                                                        <div class="lesson-item <?= !$canAccess ? 'lesson-locked' : '' ?>" 
                                                             <?php if ($canAccess): ?>onclick="window.location.href='learning_player.php?course_id=<?= (int)$course['id'] ?>&lesson_id=<?= (int)$lesson['id'] ?>'" style="cursor: pointer;"<?php endif; ?>>
                                                            <div class="flex-shrink-0">
                                                                <?php if ($canAccess): ?>
                                                                    <i class="fas fa-play-circle text-primary"></i>
                                                                <?php else: ?>
                                                                    <i class="fas fa-lock lock-icon"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <div class="fw-semibold"><?= htmlspecialchars($lesson['title']) ?></div>
                                                                <div class="text-muted small">
                                                                    <?php if (!empty($lesson['duration'])): ?>
                                                                        <i class="fas fa-clock me-1"></i><?= htmlspecialchars($lesson['duration']) ?>
                                                                    <?php endif; ?>
                                                                    <span class="ms-2">
                                                                        <i class="fas fa-<?= $lesson['content_type'] === 'video' ? 'video' : ($lesson['content_type'] === 'pdf' ? 'file-pdf' : 'file-alt') ?> me-1"></i>
                                                                        <?= ucfirst($lesson['content_type']) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <?php if ($isPreview): ?>
                                                                <span class="preview-badge">Preview</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php 
                                                    $lessonNumber++;
                                                    endforeach; 
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Instructor Section -->
                        <?php if ($instructor): ?>
                            <div class="card border-0 shadow-sm mb-2">
                                <div class="card-body">
                                    <h2 class="h4 fw-bold mb-2">
                                        <i class="fas fa-chalkboard-teacher me-2 text-primary"></i>Your Instructor
                                    </h2>
                                    <div class="d-flex gap-3 flex-wrap align-items-center">
                                        <div class="flex-shrink-0">
                                            <img src="<?= htmlspecialchars($instructor['avatar'] ?? 'assets/images/ak.png') ?>" 
                                                 alt="<?= htmlspecialchars($instructor['user_name']) ?>" 
                                                 class="instructor-avatar">
                                        </div>
                                        <div class="flex-grow-1">
                                            <h3 class="h6 fw-bold mb-1"><?= htmlspecialchars($instructor['user_name']) ?></h3>
                                            <?php if (!empty($instructor['bio'])): ?>
                                                <p class="text-muted small mb-2"><?= htmlspecialchars(mb_substr($instructor['bio'], 0, 100)) ?><?= mb_strlen($instructor['bio']) > 100 ? '...' : '' ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($instructor['social_links'])): ?>
                                                <div class="d-flex gap-1">
                                                    <?php 
                                                    $socialLinks = json_decode($instructor['social_links'], true) ?? [];
                                                    foreach ($socialLinks as $platform => $link): 
                                                    ?>
                                                        <a href="<?= htmlspecialchars($link) ?>" target="_blank" 
                                                           class="btn btn-outline-primary btn-sm">
                                                            <i class="fab fa-<?= strtolower($platform) ?>"></i>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar: Enrollment Card -->
                    <div class="col-lg-4">
                        <div class="enroll-card card border-0 shadow-lg">
                            <div class="card-body">
                                <?php if ($enrolled): ?>
                                    <div class="text-center mb-3">
                                        <div class="mb-2">
                                            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                        </div>
                                        <h3 class="h6 fw-bold mb-2">You're Enrolled!</h3>
                                        <?php if ($enrollment): ?>
                                            <div class="mb-2">
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?= min(100, max(0, (float)$enrollment['progress'])) ?>%">
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?= number_format($enrollment['progress'], 1) ?>% Complete</small>
                                            </div>
                                        <?php endif; ?>
                                        <a href="learning_player.php?course_id=<?= (int)$course['id'] ?>" 
                                           class="btn btn-success btn-lg w-100">
                                            <i class="fas fa-play me-2"></i>Continue Learning
                                        </a>
                                        <a href="learning_player.php?course_id=<?= (int)$course['id'] ?>" 
                                           class="btn btn-outline-primary btn-sm w-100 mt-2">
                                            <i class="fas fa-list me-2"></i>View Curriculum
                                        </a>
                                    </div>
                                <?php elseif (!empty($completedOrder)): ?>
                                    <!-- User has completed payment but no enrollment yet -->
                                    <div class="text-center mb-3">
                                        <div class="mb-2">
                                            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                        </div>
                                        <h3 class="h6 fw-bold mb-2">Payment Completed!</h3>
                                        <p class="text-muted small mb-3">You can now access this course.</p>
                                        <a href="learning_player.php?course_id=<?= (int)$course['id'] ?>" 
                                           class="btn btn-success btn-lg w-100">
                                            <i class="fas fa-play me-2"></i>Start Learning
                                        </a>
                                        <a href="learning_player.php?course_id=<?= (int)$course['id'] ?>" 
                                           class="btn btn-outline-primary btn-sm w-100 mt-2">
                                            <i class="fas fa-list me-2"></i>View Curriculum
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center mb-3">
                                        <?php if ((float)$course['price'] <= 0): ?>
                                            <div class="price-display text-success">FREE</div>
                                        <?php else: ?>
                                            <div class="price-display">TZS <?= number_format((float)$course['price'], 0) ?></div>
                                            <small class="text-muted">One-time payment</small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <h4 class="h6 fw-bold mb-2">This course includes:</h4>
                                        <ul class="list-unstyled small">
                                            <?php if ($totalLessons > 0): ?>
                                                <li class="mb-1">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    <?= $totalLessons ?> video lesson<?= $totalLessons !== 1 ? 's' : '' ?>
                                                </li>
                                            <?php endif; ?>
                                            <?php if (count($modules) > 0): ?>
                                                <li class="mb-1">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    <?= count($modules) ?> module<?= count($modules) !== 1 ? 's' : '' ?>
                                                </li>
                                            <?php endif; ?>
                                            <li class="mb-1">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Lifetime access
                                            </li>
                                            <?php if (!empty($file_url)): ?>
                                                <li class="mb-1">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    Downloads
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>

                                    <?php if ($user_id): ?>
                                        <form action="initiate_payment.php" method="POST">
                                            <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-2">
                                                <?= ((float)$course['price'] <= 0) ? '<i class="fas fa-gift me-2"></i>Enroll Free' : '<i class="fas fa-shopping-cart me-2"></i>Enroll Now' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="login.php?redirect=<?= urlencode('course-details.php?slug=' . $slug) ?>" 
                                           class="btn btn-primary btn-lg w-100 mb-2">
                                            <i class="fas fa-sign-in-alt me-2"></i>Sign in to Enroll
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="text-center mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-shield-alt me-1"></i>
                                            30-day guarantee
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<script>
// Trailer Video Play Function
function playTrailer() {
    const overlay = document.getElementById('trailerOverlay');
    const video = document.getElementById('trailerVideo');
    const thumbnail = document.getElementById('trailerThumbnail');
    
    if (overlay && video && thumbnail) {
        const videoSrc = '<?= !empty($promo_video_embed) ? htmlspecialchars($promo_video_embed, ENT_QUOTES) : '' ?>';
        if (videoSrc) {
            video.src = videoSrc + (videoSrc.includes('?') ? '&' : '?') + 'autoplay=1';
            video.style.display = 'block';
            thumbnail.style.display = 'none';
            overlay.classList.add('hidden');
        }
    }
}

// Toggle Module Content
function toggleModule(header) {
    const content = header.nextElementSibling;
    const icon = header.querySelector('.fa-chevron-down, .fa-chevron-up');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        content.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

// Auto-expand first module
document.addEventListener('DOMContentLoaded', function() {
    const firstModule = document.querySelector('.module-header');
    if (firstModule) {
        toggleModule(firstModule);
    }
});
</script>

</div>
<?php include 'includes/footer.php'; ?>
