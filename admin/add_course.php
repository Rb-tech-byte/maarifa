<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();

// Fetch categories from real table: course_categories
$categories = $pdo->query(
    "SELECT id, name, parent_id
     FROM course_categories
     ORDER BY COALESCE(parent_id, id), name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $name            = trim($_POST['name']);
    $slug            = trim($_POST['slug']) ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug            = trim($slug, '-');
    $description     = trim($_POST['description']);
    $short_description = trim($_POST['short_description'] ?? '');
    $price           = floatval($_POST['price']);
    $file_type       = trim($_POST['file_type']);
    $file_url        = trim($_POST['file_url']);
    $license         = trim($_POST['license']) ?: 'Commercial';
    $file_size       = trim($_POST['file_size']) ?: '120 MB';
    $publisher       = trim($_POST['publisher']) ?: 'AK23STUDIOKITS';
    $password_hint   = trim($_POST['password_hint']) ?: 'ak23studiokits.com';
    $promo_video     = isset($_POST['promo_video']) ? trim($_POST['promo_video']) : '';
    $original_source = isset($_POST['original_source']) ? trim($_POST['original_source']) : '';
    $demo_audio_url  = isset($_POST['demo_audio_url']) ? trim($_POST['demo_audio_url']) : '';
    $level           = trim($_POST['level'] ?? 'beginner');
    $duration        = trim($_POST['duration'] ?? '');
    $language        = trim($_POST['language'] ?? 'English');
    $visibility      = trim($_POST['visibility'] ?? 'draft');
    $is_featured     = isset($_POST['is_featured']) ? 1 : 0;
    $publish_date    = !empty($_POST['publish_date']) ? trim($_POST['publish_date']) : null;

    // Multiple categories (from course_categories)
    $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids'])
        ? array_values(array_unique(array_map('intval', $_POST['category_ids'])))
        : [];
    $category_id = !empty($category_ids) ? (int)$category_ids[0] : null;

    if (!$name) {
        $error = 'Course name is required.';
    }
    if (!$category_id) {
        $error .= ($error ? ' ' : '') . 'Please choose at least one category.';
    }

    $file_path      = '';
    $thumbnail_path = '';

    // Course file upload (optional)
    if (isset($_FILES['course_file']) && $_FILES['course_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir  = '../uploads/files/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName   = uniqid() . '_' . basename($_FILES['course_file']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['course_file']['tmp_name'], $targetPath)) {
            $file_path = 'uploads/files/' . $fileName;
        } else {
            $error = 'Failed to upload course file.';
        }
    }

    // Thumbnail upload -> courses.cover_image
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $thumbDir  = '../uploads/thumbnails/';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }
        $thumbName = uniqid() . '_' . basename($_FILES['thumbnail']['name']);
        $thumbPath = $thumbDir . $thumbName;
        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbPath)) {
            $thumbnail_path = 'uploads/thumbnails/' . $thumbName;
        } else {
            $error .= ' Failed to upload thumbnail.';
        }
    }

    if (!$error) {
        try {
            // Ensure unique slug in courses
            if ($slug === '') {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', uniqid('p-')));
            }
            $baseSlug = $slug;
            $i = 1;
            while (true) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE slug = ?");
                $chk->execute([$slug]);
                if ($chk->fetchColumn() == 0) {
                    break;
                }
                $slug = $baseSlug . '-' . (++$i);
            }

            // URL validation
            if ($original_source !== '' && !filter_var($original_source, FILTER_VALIDATE_URL)) {
                $error = 'Invalid Original Source URL.';
            }
            if ($demo_audio_url !== '' && !filter_var($demo_audio_url, FILTER_VALIDATE_URL)) {
                $error = 'Invalid Demo Audio URL.';
            }
            if ($error) {
                throw new Exception($error);
            }

            // Map to real courses schema
            $title = $name;
            if (empty($short_description) && $description !== '') {
                $plain = trim(strip_tags($description));
                if ($plain !== '') {
                    $short_description = mb_substr($plain, 0, 200);
                }
            }

            $featured           = $is_featured ? 1 : 0;
            $created_by         = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
            $course_category_id = null; // keep FK nullable, use course_category_links

            // Handle scheduled publishing
            $created_at = $publish_date ? date('Y-m-d H:i:s', strtotime($publish_date)) : date('Y-m-d H:i:s');
            if ($visibility === 'scheduled' && $publish_date) {
                $visibility = 'draft'; // Will be published when date arrives
            }

            $stmt = $pdo->prepare("\
                INSERT INTO courses\
                    (title, slug, short_description, description,\
                     price, currency, cover_image, promo_video,\
                     visibility, featured, category_id, created_by, level, duration, language, created_at)\
                VALUES\
                    (?, ?, ?, ?, ?, 'TZS', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\
            ");

            $ok = $stmt->execute([
                $title,
                $slug,
                $short_description,
                $description,
                $price,
                $thumbnail_path,
                $promo_video,
                $visibility,
                $featured,
                $course_category_id,
                $created_by,
                $level,
                $duration,
                $language,
                $created_at
            ]);

            if ($ok) {
                $newId = (int)$pdo->lastInsertId();

                // Ensure junction table exists
                $pdo->exec("\
                    CREATE TABLE IF NOT EXISTS course_category_links (\
                        course_id  INT NOT NULL,\
                        category_id INT NOT NULL,\
                        PRIMARY KEY (course_id, category_id),\
                        KEY idx_category (category_id)\
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\
                ");

                // Insert category links
                $linkIds = !empty($category_ids)
                    ? $category_ids
                    : ($category_id ? [$category_id] : []);
                if ($linkIds) {
                    $ins = $pdo->prepare('INSERT IGNORE INTO course_category_links (course_id, category_id) VALUES (?, ?)');
                    foreach ($linkIds as $cid) {
                        if ($cid) {
                            $ins->execute([$newId, (int)$cid]);
                        }
                    }
                }

                // Store demo audio, file_url, and original_source into medias
                if ($demo_audio_url !== '') {
                    $ms = $pdo->prepare('\
                        INSERT INTO medias (courses_id, file_type, value, type)\
                        VALUES (?, "wav", ?, "url")\
                    ');
                    $ms->execute([$newId, $demo_audio_url]);
                }
                if ($file_url !== '') {
                    $ms = $pdo->prepare('\
                        INSERT INTO medias (courses_id, file_type, value, type)\
                        VALUES (?, "zip", ?, "url")\
                    ');
                    $ms->execute([$newId, $file_url]);
                }
                if ($original_source !== '') {
                    $ms = $pdo->prepare('\
                        INSERT INTO medias (courses_id, file_type, value, type)\
                        VALUES (?, "pdf", ?, "url")\
                    ');
                    $ms->execute([$newId, $original_source]);
                }

                // Save Course Curriculum (Modules and Lessons)
                if (isset($_POST['modules']) && is_array($_POST['modules'])) {
                    $moduleOrder = 1;
                    foreach ($_POST['modules'] as $moduleData) {
                        if (empty($moduleData['title'])) continue;
                        
                        $moduleStmt = $pdo->prepare("
                            INSERT INTO modules (course_id, title, summary, order_number, created_at)
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $moduleStmt->execute([
                            $newId,
                            trim($moduleData['title']),
                            !empty($moduleData['summary']) ? trim($moduleData['summary']) : null,
                            isset($moduleData['order']) ? (int)$moduleData['order'] : $moduleOrder
                        ]);
                        $moduleId = (int)$pdo->lastInsertId();
                        
                        // Save lessons for this module
                        if (isset($moduleData['lessons']) && is_array($moduleData['lessons'])) {
                            $lessonOrder = 1;
                            foreach ($moduleData['lessons'] as $lessonData) {
                                if (empty($lessonData['title'])) continue;
                                
                                $lessonStmt = $pdo->prepare("
                                    INSERT INTO lessons (module_id, title, content_type, video_url, text_content, duration, order_number, is_preview, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                                ");
                                $lessonStmt->execute([
                                    $moduleId,
                                    trim($lessonData['title']),
                                    !empty($lessonData['content_type']) ? trim($lessonData['content_type']) : 'video',
                                    !empty($lessonData['video_url']) ? trim($lessonData['video_url']) : null,
                                    !empty($lessonData['text_content']) ? trim($lessonData['text_content']) : null,
                                    !empty($lessonData['duration']) ? trim($lessonData['duration']) : null,
                                    $lessonOrder,
                                    isset($lessonData['is_preview']) ? 1 : 0
                                ]);
                                $lessonOrder++;
                            }
                        }
                        $moduleOrder++;
                    }
                    
                    // Update course totals
                    $pdo->prepare("
                        UPDATE courses SET 
                            total_modules = (SELECT COUNT(*) FROM modules WHERE course_id = ?),
                            total_lessons = (SELECT COUNT(*) FROM lessons l JOIN modules m ON l.module_id = m.id WHERE m.course_id = ?)
                        WHERE id = ?
                    ")->execute([$newId, $newId, $newId]);
                }

                $success = 'Course added successfully!';
                header("Location: courses.php?success=1");
                exit();
            } else {
                $errInfo = $stmt->errorInfo();
                $error = "Failed to add course to the database." . (!empty($errInfo[2]) ? (' ' . htmlspecialchars($errInfo[2])) : '');
            }
        } catch (Exception $ex) {
            $error = 'Error saving course: ' . htmlspecialchars($ex->getMessage());
        }
    }
}

$title = 'Add Course - AK23 App';
include '../includes/admin_header.php';
?>

<style>
.course-form-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    margin-bottom: 1.5rem;
}
.course-form-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
}
.card-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.25rem 1.5rem;
    border: none;
}
.card-header-modern h5 {
    margin: 0;
    font-weight: 600;
}
.form-section-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}
.preview-thumbnail {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid #e0e0e0;
}
.rich-text-wrapper {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}
.publish-status-badge {
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
}
.category-select-wrapper {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 0.5rem;
}
.category-select-wrapper select {
    border: none;
}
.sticky-publish-card {
    position: sticky;
    top: 20px;
}
</style>

<main class="admin-main container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1" style="color: #2c3e50;">
                <i class="fas fa-plus-circle me-2 text-primary"></i>Add New Course
            </h1>
            <p class="text-muted mb-0">Create and publish a new course with modern features</p>
        </div>
        <a href="courses.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Courses
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="courseForm">
        <div class="row">
            <!-- Left Column - Main Form -->
            <div class="col-lg-8">
                <!-- Basic Information Card -->
                <div class="card course-form-card">
                    <div class="card-header card-header-modern">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Basic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-book me-1 text-primary"></i>Course Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="name" class="form-control form-control-lg" 
                                       placeholder="Enter course title..." required>
                                <small class="text-muted">A clear and descriptive title for your course</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-link me-1 text-primary"></i>URL Slug
                                </label>
                                <input type="text" name="slug" class="form-control" 
                                       placeholder="auto-generated">
                                <small class="text-muted">Leave blank for auto-generation</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-align-left me-1 text-primary"></i>Short Description
                                </label>
                                <textarea name="short_description" id="short_description" class="form-control rte" 
                                          rows="4" placeholder="Brief description (200 characters max)..."></textarea>
                                <small class="text-muted">A brief summary that appears in course listings. Use the rich text editor to format.</small>
        </div>
        <div class="col-12">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-file-alt me-1 text-primary"></i>Full Description <span class="text-danger">*</span>
                                </label>
                                <div class="rich-text-wrapper">
                                    <textarea name="description" id="description" class="form-control rte" 
                                              rows="10" placeholder="Enter detailed course description..."></textarea>
                                </div>
                                <small class="text-muted">Use the rich text editor to format your content with images, links, lists, and more.</small>
                            </div>
                            <script>
                            // Immediate TinyMCE initialization attempt
                            (function() {
                                function initTiny() {
                                    if (typeof tinymce !== 'undefined' && document.getElementById('short_description') && document.getElementById('description')) {
                                        // Short Description
                                        if (!tinymce.get('short_description')) {
                                            tinymce.init({
                                                selector: '#short_description',
                                                menubar: false,
                                                plugins: 'lists link autoresize',
                                                toolbar: 'undo redo | formatselect | bold italic underline | bullist numlist | link | removeformat',
                                                branding: false,
                                                height: 200,
                                                resize: true,
                                                entity_encoding: 'raw',
                                                valid_elements: '*[*]',
                                                extended_valid_elements: '*[*]'
                                            });
                                        }
                                        // Full Description
                                        if (!tinymce.get('description')) {
                                            tinymce.init({
                                                selector: '#description',
                                                menubar: false,
                                                plugins: 'lists link table code autoresize media image',
                                                toolbar: 'undo redo | formatselect | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | outdent indent | link image table media | code | removeformat',
                                                branding: false,
                                                height: 400,
                                                resize: true,
                                                media_live_embeds: true,
                                                entity_encoding: 'raw',
                                                valid_elements: '*[*]',
                                                extended_valid_elements: '*[*]'
                                            });
                                        }
                                    } else {
                                        setTimeout(initTiny, 100);
                                    }
                                }
                                if (document.readyState === 'loading') {
                                    document.addEventListener('DOMContentLoaded', initTiny);
                                } else {
                                    initTiny();
                                }
                            })();
                            </script>
                        </div>
                    </div>
        </div>

                <!-- Course Details Card -->
                <div class="card course-form-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cog me-2"></i>Course Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
        <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-dollar-sign me-1 text-success"></i>Price (TZS) <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" name="price" class="form-control" 
                                       placeholder="0.00" required>
                                <small class="text-muted">Set to 0 for free course</small>
        </div>
        <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-layer-group me-1 text-info"></i>Level
                                </label>
                                <select name="level" class="form-select">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="all">All Levels</option>
                                </select>
        </div>
        <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-clock me-1 text-warning"></i>Duration
                                </label>
                                <input type="text" name="duration" class="form-control" 
                                       placeholder="e.g., 2 hours, 4 weeks">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-language me-1 text-primary"></i>Language
                                </label>
                                <input type="text" name="language" class="form-control" 
                                       value="English" placeholder="English">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-tag me-1 text-secondary"></i>File Type
                                </label>
                                <input type="text" name="file_type" class="form-control" 
                                       placeholder="zip, wav, pdf, etc.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-weight me-1 text-dark"></i>File Size
                                </label>
                                <input type="text" name="file_size" class="form-control" 
                                       placeholder="e.g., 120 MB">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-certificate me-1 text-success"></i>License
                                </label>
                                <input type="text" name="license" class="form-control" 
                                       value="Commercial" placeholder="Commercial, Royalty-Free, etc.">
                            </div>
                        </div>
                    </div>
        </div>

                <!-- Media & Files Card -->
                <div class="card course-form-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-images me-2"></i>Media & Files
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Media Guidelines:</strong> Upload high-quality images and videos. The promo video will be displayed as a trailer on the course details page. Course videos should be added via Modules & Lessons after creating the course.
                        </div>
                        
                        <div class="row g-3">
                            <!-- Thumbnail Image -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-image me-1 text-primary"></i>Course Thumbnail Image <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="thumbnail" accept="image/*" class="form-control" 
                                       onchange="previewThumbnail(this)" required>
                                <div id="thumbnailPreview" class="mt-2"></div>
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>Recommended: 1280x720px (16:9 ratio) or larger. This image appears on course listings and as the trailer thumbnail.
                                </small>
                            </div>
                            
                            <!-- Promo Video (Trailer) -->
        <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-video me-1 text-danger"></i>Course Promo Video (Trailer) <span class="text-danger">*</span>
                                </label>
                                <input type="url" name="promo_video" class="form-control" 
                                       placeholder="https://www.youtube.com/watch?v=... or https://drive.google.com/file/d/..."
                                       required>
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>YouTube, Vimeo, or Google Drive video URL. This will be shown as a "Watch Trailer" button on the course page. Students can preview this before enrolling.
                                </small>
                                <div class="mt-2">
                                    <small class="text-success">
                                        <i class="fas fa-check-circle me-1"></i>Supported: YouTube, Vimeo, Google Drive
                                    </small>
                                </div>
        </div>
                            
                            <!-- Course File Upload -->
        <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-file-archive me-1 text-warning"></i>Course File (Optional)
                                </label>
                                <input type="file" name="course_file" class="form-control" 
                                       accept=".zip,.rar,.7z,.pdf,.wav,.mp3">
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>Upload course materials (ZIP, PDF, audio files). Alternatively, provide a download URL below.
                                </small>
        </div>

                            <!-- Download/Stream Course URL -->
        <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-link me-1 text-info"></i>Download/Stream Course URL (Optional)
                                </label>
                                <input type="url" name="file_url" class="form-control" 
                                       placeholder="https://drive.google.com/file/d/... or https://example.com/course.zip">
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>Direct download link OR streaming URL for course materials. Students can download or stream after enrollment. Supports Google Drive, YouTube, Vimeo, or direct file links.
                                </small>
        </div>
                            
                            <!-- Demo Audio URL -->
        <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-music me-1 text-primary"></i>Demo Audio URL (Optional)
                                </label>
                                <input type="url" name="demo_audio_url" class="form-control" 
                                       placeholder="https://cdn.example.com/demo.mp3">
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>Preview audio sample for audio courses. This appears on the course details page.
                                </small>
        </div>

                            <!-- Original Source URL (Optional - can be removed if not needed) -->
        <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-external-link-alt me-1 text-secondary"></i>Original Source URL (Optional)
                                </label>
                                <input type="url" name="original_source" class="form-control" 
                                       placeholder="https://original-site.example/item">
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>Link to original source/creator if this is a republished course.
                                </small>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Course videos are managed in the "Course Curriculum" section below. The download URL above is for additional course materials (ZIP files, PDFs, etc.).
                        </div>
                    </div>
                </div>

                <!-- Course Curriculum Card -->
                <div class="card course-form-card">
                    <div class="card-header-modern">
                        <h5 class="mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>Course Curriculum
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            <i class="fas fa-lightbulb me-1"></i>
                            Add modules and lessons to structure your course content. Videos can be streamed from YouTube, Vimeo, or Google Drive. Mark lessons as "Preview" to allow free access.
                        </p>
                        
                        <div id="curriculumContainer">
                            <div class="curriculum-module mb-4 p-3 border rounded" data-module-index="0">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0 fw-bold">
                                        <i class="fas fa-folder me-2 text-primary"></i>Module 1
                                    </h6>
                                    <button type="button" class="btn btn-sm btn-danger remove-module" style="display: none;">
                                        <i class="fas fa-trash me-1"></i>Remove Module
                                    </button>
                                </div>
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-md-8">
                                        <label class="form-label small fw-bold">Module Title <span class="text-danger">*</span></label>
                                        <input type="text" name="modules[0][title]" class="form-control form-control-sm" 
                                               placeholder="e.g., Introduction to Audio Mixing" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Order</label>
                                        <input type="number" name="modules[0][order]" class="form-control form-control-sm" 
                                               value="1" min="1">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Module Summary (Optional)</label>
                                    <textarea name="modules[0][summary]" class="form-control form-control-sm rte-curriculum" rows="3" 
                                              placeholder="Brief description of what students will learn in this module"></textarea>
                                </div>
                                
                                <div class="lessons-container">
                                    <div class="lesson-item mb-3 p-3 bg-light rounded" data-lesson-index="0">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 small fw-bold">
                                                <i class="fas fa-play-circle me-1 text-success"></i>Lesson 1
                                            </h6>
                                            <button type="button" class="btn btn-sm btn-danger remove-lesson" style="display: none;">
                                                <i class="fas fa-times"></i>
                                            </button>
        </div>
                                        
                                        <div class="row g-2 mb-2">
        <div class="col-md-6">
                                                <label class="form-label small">Lesson Title <span class="text-danger">*</span></label>
                                                <input type="text" name="modules[0][lessons][0][title]" class="form-control form-control-sm" 
                                                       placeholder="e.g., Getting Started" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small">Content Type</label>
                                                <select name="modules[0][lessons][0][content_type]" class="form-select form-select-sm">
                                                    <option value="video" selected>Video</option>
                                                    <option value="text">Text</option>
                                                    <option value="pdf">PDF</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small">Duration</label>
                                                <input type="text" name="modules[0][lessons][0][duration]" class="form-control form-control-sm" 
                                                       placeholder="e.g., 10:30">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label small">Video URL (YouTube, Vimeo, Google Drive, or direct link)</label>
                                            <input type="url" name="modules[0][lessons][0][video_url]" class="form-control form-control-sm" 
                                                   placeholder="https://www.youtube.com/watch?v=... or https://drive.google.com/file/d/...">
                                            <small class="text-muted">Supports YouTube, Vimeo, Google Drive, or direct video URLs</small>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label small">Text Content (for text lessons)</label>
                                            <textarea name="modules[0][lessons][0][text_content]" class="form-control form-control-sm rte-curriculum" rows="4" 
                                                      placeholder="Lesson content in HTML or plain text"></textarea>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input type="checkbox" name="modules[0][lessons][0][is_preview]" class="form-check-input" id="preview_0_0">
                                            <label class="form-check-label small" for="preview_0_0">
                                                <i class="fas fa-eye me-1"></i>Mark as Preview (free access for non-enrolled students)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-sm btn-outline-primary add-lesson" data-module-index="0">
                                    <i class="fas fa-plus me-1"></i>Add Lesson
                                </button>
                            </div>
        </div>

                        <button type="button" class="btn btn-primary mt-3" id="addModuleBtn">
                            <i class="fas fa-plus me-1"></i>Add Module
                        </button>
                    </div>
                </div>

                <!-- Categories & Metadata Card -->
                <div class="card course-form-card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-folder-open me-2"></i>Categories & Metadata
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
        <div class="col-md-6">
                                <label class="form-label fw-bold d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-tags me-1 text-primary"></i>Categories <span class="text-danger">*</span></span>
                                    <a href="course_categories.php" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-cog me-1"></i> Manage
                                    </a>
            </label>
                                <div class="category-checkbox-wrapper border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                <?php
                                    $parents = [];
                  $childrenMap = [];
                  foreach ($categories as $c) {
                    if (!empty($c['parent_id'])) {
                      $childrenMap[(int)$c['parent_id']][] = $c;
                    } else {
                      $parents[] = $c;
                    }
                  }
                  foreach ($parents as $p) {
                    $kids = $childrenMap[(int)$p['id']] ?? [];
                                        echo '<div class="mb-3">';
                                        echo '<div class="fw-bold text-primary mb-2"><i class="fas fa-folder me-1"></i>' . htmlspecialchars($p['name']) . '</div>';
                    if ($kids) {
                      foreach ($kids as $ch) {
                                                $uniqueId = 'cat_' . $ch['id'];
                                                echo '<div class="form-check ms-3">';
                                                echo '<input type="checkbox" name="category_ids[]" class="form-check-input" id="' . $uniqueId . '" value="' . (int)$ch['id'] . '">';
                                                echo '<label class="form-check-label" for="' . $uniqueId . '">' . htmlspecialchars($ch['name']) . '</label>';
                                                echo '</div>';
                      }
                    } else {
                                            $uniqueId = 'cat_' . $p['id'];
                                            echo '<div class="form-check ms-3">';
                                            echo '<input type="checkbox" name="category_ids[]" class="form-check-input" id="' . $uniqueId . '" value="' . (int)$p['id'] . '">';
                                            echo '<label class="form-check-label" for="' . $uniqueId . '">' . htmlspecialchars($p['name']) . '</label>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                                <small class="text-muted">Select one or more categories</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-building me-1 text-info"></i>Publisher
                                </label>
                                <input type="text" name="publisher" class="form-control" 
                                       value="AK23STUDIOKITS" placeholder="Publisher name">
                                <label class="form-label fw-bold mt-3">
                                    <i class="fas fa-key me-1 text-secondary"></i>Password Hint
                                </label>
                                <input type="text" name="password_hint" class="form-control" 
                                       value="ak23studiokits.com" placeholder="Password hint">
        </div>
        </div>
        </div>
        </div>
            </div>

            <!-- Right Column - Publishing & Actions -->
            <div class="col-lg-4">
                <!-- Publishing Card -->
                <div class="card course-form-card sticky-publish-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-paper-plane me-2"></i>Publishing Options
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Publication Status</label>
                            <select name="visibility" class="form-select" id="visibilitySelect">
                                <option value="draft">Draft - Save for later</option>
                                <option value="published" selected>Published - Make it live</option>
                                <option value="scheduled">Scheduled - Publish later</option>
                            </select>
                        </div>

                        <div class="mb-3" id="scheduleDateGroup" style="display: none;">
                            <label class="form-label fw-bold">Schedule Publish Date</label>
                            <input type="datetime-local" name="publish_date" class="form-control" 
                                   id="publishDate">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_featured" 
                                   id="is_featured">
                            <label class="form-check-label fw-bold" for="is_featured">
                                <i class="fas fa-star me-1 text-warning"></i>Feature this course
                            </label>
                            <small class="text-muted d-block">Featured courses appear prominently on the homepage</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="add_course" value="1" 
                                    class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save & Publish Course
                            </button>
                            <button type="button" class="btn btn-outline-secondary" 
                                    onclick="saveDraft()">
                                <i class="fas fa-file-alt me-2"></i>Save as Draft
                            </button>
                        </div>

                        <hr class="my-3">

                        <div class="alert alert-info mb-0">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Tip:</strong> You can always edit and republish your course later from the courses list.
                            </small>
                        </div>
            </div>
        </div>

                <!-- Quick Stats Preview -->
                <div class="card course-form-card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Course Preview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div id="previewThumbnail" class="mb-2">
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                     style="width: 100%; height: 150px;">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            </div>
                            <h6 id="previewTitle" class="mb-1">Course Title</h6>
                            <p class="text-muted small mb-0" id="previewPrice">TZS 0.00</p>
                        </div>
                        <hr>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Status:</span>
                                <span id="previewStatus" class="badge bg-secondary">Draft</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Level:</span>
                                <span id="previewLevel">Beginner</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Categories:</span>
                                <span id="previewCategories" class="text-end">None selected</span>
                            </div>
                        </div>
            </div>
        </div>
            </div>
        </div>
    </form>
</main>

<script>
// Thumbnail preview
function previewThumbnail(input) {
    const preview = document.getElementById('thumbnailPreview');
    const previewImg = document.getElementById('previewThumbnail');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" class="preview-thumbnail" alt="Preview">';
            if (previewImg) {
                previewImg.innerHTML = '<img src="' + e.target.result + '" class="img-fluid rounded" alt="Preview">';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Visibility change handler
document.getElementById('visibilitySelect').addEventListener('change', function() {
    const scheduleGroup = document.getElementById('scheduleDateGroup');
    if (this.value === 'scheduled') {
        scheduleGroup.style.display = 'block';
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('publishDate').value = now.toISOString().slice(0, 16);
    } else {
        scheduleGroup.style.display = 'none';
    }
    updatePreview();
});

// Save as draft
function saveDraft() {
    document.getElementById('visibilitySelect').value = 'draft';
    document.getElementById('courseForm').submit();
}

// Live preview updates
document.querySelector('input[name="name"]').addEventListener('input', function() {
    document.getElementById('previewTitle').textContent = this.value || 'Course Title';
});

document.querySelector('input[name="price"]').addEventListener('input', function() {
    const price = parseFloat(this.value) || 0;
    document.getElementById('previewPrice').textContent = 'TZS ' + price.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
});

document.querySelector('select[name="level"]').addEventListener('change', function() {
    document.getElementById('previewLevel').textContent = this.options[this.selectedIndex].text;
});

document.getElementById('visibilitySelect').addEventListener('change', updatePreview);

function updatePreview() {
    const status = document.getElementById('visibilitySelect').value;
    const statusBadge = document.getElementById('previewStatus');
    const badges = {
        'draft': { class: 'bg-secondary', text: 'Draft' },
        'published': { class: 'bg-success', text: 'Published' },
        'scheduled': { class: 'bg-warning', text: 'Scheduled' }
    };
    statusBadge.className = 'badge ' + badges[status].class;
    statusBadge.textContent = badges[status].text;
}

// Category selection preview
// Update preview for checkbox categories
document.querySelectorAll('input[name="category_ids[]"]').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const selected = Array.from(document.querySelectorAll('input[name="category_ids[]"]:checked'))
            .map(cb => cb.nextElementSibling.textContent.trim());
        const preview = document.getElementById('previewCategories');
        if (preview) {
            if (selected.length > 0) {
                preview.textContent = selected.slice(0, 2).join(', ') + (selected.length > 2 ? '...' : '');
            } else {
                preview.textContent = 'None selected';
            }
        }
    });
});

// Initialize preview
updatePreview();

// Initialize TinyMCE Editors - Comprehensive Approach
(function() {
  var initCount = 0;
  var maxInits = 3;
  
  function tryInit() {
    initCount++;
    console.log('TinyMCE initialization attempt #' + initCount);
    
    // Check prerequisites
    if (typeof tinymce === 'undefined') {
      console.warn('TinyMCE not loaded yet');
      if (initCount < maxInits) {
        setTimeout(tryInit, 500);
      }
      return;
    }
    
    var shortDesc = document.getElementById('short_description');
    var fullDesc = document.getElementById('description');
    
    if (!shortDesc || !fullDesc) {
      console.warn('Textareas not found yet');
      if (initCount < maxInits) {
        setTimeout(tryInit, 500);
      }
      return;
    }
    
    console.log('All prerequisites met, initializing editors...');
    
    // Initialize Short Description
    if (shortDesc && !tinymce.get('short_description')) {
      try {
        console.log('Initializing short_description editor...');
        tinymce.init({
          selector: '#short_description',
          menubar: false,
          plugins: 'lists link autoresize',
          toolbar: 'undo redo | formatselect | bold italic underline | bullist numlist | link | removeformat',
          branding: false,
          height: 200,
          resize: true,
          statusbar: false,
          entity_encoding: 'raw',
          valid_elements: '*[*]',
          extended_valid_elements: '*[*]',
          setup: function(editor) {
            editor.on('init', function() {
              console.log('✓ Short description editor ready');
            });
          }
        });
      } catch(e) {
        console.error('Error initializing short_description:', e);
      }
    } else {
      console.log('Short description already initialized');
    }
    
    // Initialize Full Description
    if (fullDesc && !tinymce.get('description')) {
      try {
        console.log('Initializing description editor...');
        tinymce.init({
          selector: '#description',
          menubar: false,
          plugins: 'lists link table code autoresize media image',
          toolbar: 'undo redo | formatselect | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | outdent indent | link image table media | code | removeformat',
          branding: false,
          height: 400,
          resize: true,
          statusbar: false,
          media_live_embeds: true,
          entity_encoding: 'raw',
          valid_elements: '*[*]',
          extended_valid_elements: '*[*]',
          setup: function(editor) {
            editor.on('init', function() {
              console.log('✓ Full description editor ready');
            });
          }
        });
      } catch(e) {
        console.error('Error initializing description:', e);
      }
    } else {
      console.log('Full description already initialized');
    }
  }
  
  // Multiple initialization triggers
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tryInit);
  } else {
    tryInit();
  }
  
  window.addEventListener('load', function() {
    setTimeout(tryInit, 300);
  });
  
  // Fallback after 2 seconds
  setTimeout(tryInit, 2000);
})();

// Curriculum Management JavaScript
(function() {
  let moduleIndex = 1;
  let lessonIndices = {0: 1}; // Track lesson index per module
  
  // Add Module
  document.getElementById('addModuleBtn')?.addEventListener('click', function() {
    const container = document.getElementById('curriculumContainer');
    const template = container.querySelector('.curriculum-module').cloneNode(true);
    template.setAttribute('data-module-index', moduleIndex);
    template.querySelector('h6').innerHTML = `<i class="fas fa-folder me-2 text-primary"></i>Module ${moduleIndex + 1}`;
    template.querySelector('.remove-module').style.display = 'block';
    
    // Update all input names
    template.querySelectorAll('input, select, textarea').forEach(el => {
      if (el.name) {
        el.name = el.name.replace(/modules\[\d+\]/, `modules[${moduleIndex}]`);
        if (el.id) el.id = el.id.replace(/\d+/, moduleIndex);
      }
    });
    
    // Reset lesson index for this module
    lessonIndices[moduleIndex] = 1;
    const lessonContainer = template.querySelector('.lessons-container');
    const firstLesson = lessonContainer.querySelector('.lesson-item');
    firstLesson.setAttribute('data-lesson-index', '0');
    firstLesson.querySelector('h6').innerHTML = `<i class="fas fa-play-circle me-1 text-success"></i>Lesson 1`;
    firstLesson.querySelector('.remove-lesson').style.display = 'none';
    
    container.appendChild(template);
    moduleIndex++;
  });
  
  // Remove Module
  document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-module')) {
      const module = e.target.closest('.curriculum-module');
      if (document.querySelectorAll('.curriculum-module').length > 1) {
        module.remove();
      } else {
        alert('At least one module is required.');
      }
    }
  });
  
  // Add Lesson
  document.addEventListener('click', function(e) {
    if (e.target.closest('.add-lesson')) {
      const btn = e.target.closest('.add-lesson');
      const moduleIndex = parseInt(btn.getAttribute('data-module-index'));
      const module = btn.closest('.curriculum-module');
      const lessonsContainer = module.querySelector('.lessons-container');
      const template = lessonsContainer.querySelector('.lesson-item').cloneNode(true);
      
      const lessonIndex = lessonIndices[moduleIndex] || 1;
      template.setAttribute('data-lesson-index', lessonIndex);
      template.querySelector('h6').innerHTML = `<i class="fas fa-play-circle me-1 text-success"></i>Lesson ${lessonIndex + 1}`;
      template.querySelector('.remove-lesson').style.display = 'block';
      
      // Update all input names and values
      template.querySelectorAll('input, select, textarea').forEach(el => {
        if (el.name) {
          el.name = el.name.replace(/lessons\[\d+\]/, `lessons[${lessonIndex}]`);
          if (el.type === 'checkbox') el.checked = false;
          else if (el.type === 'number') el.value = '';
          else el.value = '';
          if (el.id) {
            const newId = el.id.replace(/\d+_\d+/, `${moduleIndex}_${lessonIndex}`);
            el.id = newId;
            if (el.type === 'checkbox' && el.nextElementSibling) {
              el.nextElementSibling.setAttribute('for', newId);
            }
          }
        }
      });
      
      lessonsContainer.appendChild(template);
      lessonIndices[moduleIndex] = lessonIndex + 1;
      
      // Initialize TinyMCE for new lesson text content
      const newTextArea = template.querySelector('textarea.rte-curriculum');
      if (newTextArea && typeof tinymce !== 'undefined') {
        const uniqueId = 'rte_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        if (!newTextArea.id) newTextArea.id = uniqueId;
        
        tinymce.init({
          target: newTextArea,
          menubar: false,
          plugins: 'lists link autoresize',
          toolbar: 'undo redo | formatselect | bold italic underline | bullist numlist | link | removeformat',
          branding: false,
          height: 200,
          resize: true,
          statusbar: false,
          entity_encoding: 'raw',
          valid_elements: '*[*]',
          extended_valid_elements: '*[*]'
        });
      }
    }
  });
  
  // Remove Lesson
  document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-lesson')) {
      const lesson = e.target.closest('.lesson-item');
      const lessonsContainer = lesson.parentElement;
      if (lessonsContainer.querySelectorAll('.lesson-item').length > 1) {
        // Remove TinyMCE instance if exists
        const textArea = lesson.querySelector('textarea.rte-curriculum');
        if (textArea && typeof tinymce !== 'undefined' && textArea.id && tinymce.get(textArea.id)) {
          tinymce.get(textArea.id).remove();
        }
        lesson.remove();
      } else {
        alert('At least one lesson is required per module.');
      }
    }
  });
  
  // Initialize TinyMCE for curriculum textareas
  function initCurriculumEditors() {
    if (typeof tinymce === 'undefined') {
      setTimeout(initCurriculumEditors, 500);
      return;
    }
    
    document.querySelectorAll('textarea.rte-curriculum').forEach(function(textarea) {
      const textareaId = textarea.id || textarea.name;
      if (!textareaId || !tinymce.get(textareaId)) {
        const uniqueId = textarea.id || 'rte_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        if (!textarea.id) textarea.id = uniqueId;
        
        tinymce.init({
          target: textarea,
          menubar: false,
          plugins: 'lists link autoresize',
          toolbar: 'undo redo | formatselect | bold italic underline | bullist numlist | link | removeformat',
          branding: false,
          height: 200,
          resize: true,
          statusbar: false,
          entity_encoding: 'raw',
          valid_elements: '*[*]',
          extended_valid_elements: '*[*]'
        });
      }
    });
  }
  
  // Initialize on page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(initCurriculumEditors, 1000);
    });
  } else {
    setTimeout(initCurriculumEditors, 1000);
  }
  
  window.addEventListener('load', function() {
    setTimeout(initCurriculumEditors, 1500);
  });
})();
</script>

<?php include '../includes/admin_footer.php'; ?>
