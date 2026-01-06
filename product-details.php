<?php
session_start();
require 'config.php';
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/sanitize_html.php';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$course = null;
$categories = [];
$recommended = [];
$trending = [];
$error = '';
// Remove legacy hasPaid; we will detect purchases via ps_* when possible

// Fetch categories for navigation (if needed)
try {
    $stmt = $pdo->query("SELECT id, name, slug FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Fetch course details
if ($slug) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name, c.slug AS category_slug 
            FROM courses p
            JOIN categories c ON p.category_id = c.id
            WHERE p.slug = ? AND p.is_active = 1 AND p.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($course) {
            // ps_* already purchased detection (by user_id or email + course name)
            $alreadyPurchased = false;
            $downloadSignedUrl = '';
            try {
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                $uemail = '';
                if ($uid > 0) {
                    $u = getUserById($uid);
                    $uemail = $u['email'] ?? '';
                } elseif (!empty($_SESSION['pay_email'])) {
                    $uemail = (string)$_SESSION['pay_email'];
                }

                // Check for a completed purchase in the new schema
                if ($uid > 0) {
                    $q = $pdo->prepare(
                        "SELECT 1 FROM orders o " .
                        "JOIN payments p ON o.id = p.order_id " .
                        "WHERE o.user_id = :uid AND o.course_id = :pid AND p.status = 'completed' LIMIT 1"
                    );
                    $q->execute([':uid' => $uid, ':pid' => $course['id']]);
                    $alreadyPurchased = (bool)$q->fetchColumn();
                }

                if ($alreadyPurchased) {
                    // Fetch the latest completed order for this user and course
                    $ord = $pdo->prepare(
                        "SELECT o.id AS order_id, p.file_url AS file_url\n" .
                        "FROM orders o\n" .
                        "JOIN payments pay ON pay.order_id = o.id\n" .
                        "JOIN courses p ON p.id = o.course_id\n" .
                        "WHERE o.user_id = ? AND o.course_id = ?\n" .
                        "AND o.status = 'completed' AND pay.status = 'completed'\n" .
                        "ORDER BY o.created_at DESC, o.id DESC LIMIT 1"
                    );
                    $ord->execute([$uid, (int)$course['id']]);
                    $or = $ord->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($or && !empty($or['order_id'])) {
                        $orderId = (int)$or['order_id'];
                        $dlUrl = (string)($or['file_url'] ?? '');
                        if ($orderId > 0 && $dlUrl !== '') {
                            $payload = [
                                'order_id' => $orderId,
                                'course_id' => (int)$course['id'],
                                'url' => $dlUrl,
                            ];
                            $token = ak23_sign($payload);
                            if ($token !== '') {
                                $downloadSignedUrl = 'downloads.php?token=' . urlencode($token);
                            } else {
                                // Fallback: send user to downloads page listing
                                $downloadSignedUrl = 'downloads.php';
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // Do not block page; simply fall back to normal pay button
            }
            // Legacy 'has paid' check removed: new flow uses ps_* tables and signed tokens on return

            // Fetch course images
            $stmt = $pdo->prepare("
                SELECT value, type 
                FROM medias 
                WHERE courses_id = ? AND file_type = 'image' AND deleted_at IS NULL
            ");
            $stmt->execute([$course['id']]);
            $course['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch course audio demos (if any)
            try {
                $stmt = $pdo->prepare("
                    SELECT value, type 
                    FROM medias 
                    WHERE courses_id = ? AND file_type = 'audio' AND deleted_at IS NULL
                ");
                $stmt->execute([$course['id']]);
                $course['audios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $course['audios'] = [];
            }

            // Fetch recommended courses
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.slug, p.price, p.thumbnail,
                       c.name AS category_name, c.slug AS category_slug 
                FROM courses p
                JOIN categories c ON p.category_id = c.id
                WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
                ORDER BY p.created_at DESC LIMIT 3
            ");
            $stmt->execute([$course['category_id'], $course['id']]);
            $recommended = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add images to recommended courses
// Only include image if present; otherwise leave empty to let UI render a placeholder
foreach ($recommended as &$rec) {
    $thumb = !empty($rec['thumbnail']) ? (string)$rec['thumbnail'] : '';
    $rec['images'] = $thumb !== '' ? [[ 'type' => 'url', 'value' => $thumb ]] : [];
}
unset($rec);


            // Fetch trending courses
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.slug, p.price, p.thumbnail,
                       c.name AS category_name, c.slug AS category_slug 
                FROM courses p
                JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1 AND p.id != ?
                ORDER BY p.created_at DESC LIMIT 6
            ");
            $stmt->execute([$course['id']]);
            $trendingRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // De-duplicate by slug and limit to 3
            $seen = [];
            $trending = [];
            foreach ($trendingRaw as $row) {
                $slugKey = $row['slug'] ?? '';
                if ($slugKey !== '' && !isset($seen[$slugKey])) {
                    $seen[$slugKey] = true;
                    $trending[] = $row;
                }
                if (count($trending) >= 3) break;
            }

            // Add images to trending courses
            foreach ($trending as &$trend) {
                if (!empty($trend['thumbnail'])) {
                    $trend['images'] = [[ 'type' => 'url', 'value' => $trend['thumbnail'] ]];
                } else {
                    $stmt = $pdo->prepare("
                        SELECT value, type 
                        FROM medias 
                        WHERE courses_id = ? AND file_type = 'image' AND deleted_at IS NULL
                        ORDER BY id ASC LIMIT 1
                    ");
                    $stmt->execute([$trend['id']]);
                    $imgRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $trend['images'] = !empty($imgRows) ? $imgRows : [];
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

$title = $course ? htmlspecialchars($course['name']) . ' - AK23 App' : 'Course Details - AK23 App';
// Prepare SEO/OpenGraph/Twitter meta for header.php
if ($course) {
    // Derive description (plain text, trimmed)
    $rawDesc = (string)($course['description'] ?? '');
    $plainDesc = trim(mb_strimwidth(trim(strip_tags($rawDesc)), 0, 200, '...'));
    // Derive primary image (thumbnail or first image)
    $imgCandidate = '';
    if (!empty($course['thumbnail'])) {
        $imgCandidate = (string)$course['thumbnail'];
    } elseif (!empty($course['images'][0]['value'])) {
        $imgCandidate = (string)$course['images'][0]['value'];
    }
    // Normalize image to absolute URL
    $metaImage = '';
    if ($imgCandidate !== '') {
        if (str_starts_with($imgCandidate, 'http://') || str_starts_with($imgCandidate, 'https://')) {
            $metaImage = $imgCandidate;
        } else {
            $path = '/' . ltrim($imgCandidate, '/');
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $metaImage = ($host !== '') ? ($scheme . '://' . $host . $path) : $path; // avoid $base before header include
        }
    }
    $metaTitle = (string)$course['name'];
    $metaDescription = $plainDesc;
    $metaCategory = (string)($course['category_name'] ?? '');
}
// Expose current category slug for active pill highlight in header
$currentCategorySlug = isset($course['category_slug']) ? (string)$course['category_slug'] : '';
include 'includes/header.php';

$user_email = '';
$user_phone = '';
if (isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
    $user_email = $user['email'] ?? '';
    $user_phone = $user['phone'] ?? '';
}
// Prefill helpers for guests (from previous attempts if stored)
$prefill_name = '';
$prefill_email = '';
if (!isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['pay_name'])) { $prefill_name = (string)$_SESSION['pay_name']; }
    if (!empty($_SESSION['pay_email'])) { $prefill_email = (string)$_SESSION['pay_email']; }
}
?>

<div class="container py-3 px-1 px-md-2">
<?php if (isset($_SESSION['user_id'])): ?>
    <?php $isVerifiedFlag = 1; if (!empty($user) && isset($user['is_verified'])) { $isVerifiedFlag = (int)$user['is_verified']; } ?>
    <?php if ($isVerifiedFlag !== 1): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 my-3" role="alert">
        <i class="bi bi-envelope-exclamation-fill"></i>
        <div>
            Your email isn’t verified yet. Verifying helps secure your account and improves delivery of download emails.
            <a class="alert-link" href="verify_notice.php?email=<?= urlencode((string)($user['email'] ?? '')) ?>&redirect=<?= urlencode('course-details.php?slug=' . (string)$slug) ?>">Verify now</a>
            or <a class="alert-link" href="resend_verification.php?email=<?= urlencode((string)($user['email'] ?? '')) ?>&redirect=<?= urlencode('course-details.php?slug=' . (string)$slug) ?>">resend link</a>.
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger text-center my-4"><?= htmlspecialchars($error) ?></div>
<?php elseif (!$course): ?>
    <div class="alert alert-warning text-center my-4">
        Course not found. <?= $slug ? 'Slug: ' . htmlspecialchars($slug) : '' ?>
    </div>
<?php else: ?>
    <!-- Breadcrumb -->
    <nav class="breadcrumb mb-3 small d-none d-md-flex">
        <a class="breadcrumb-item" href="index.php">Home</a>
        <a class="breadcrumb-item" href="categories.php?slug=<?= rawurlencode((string)($course['category_slug'] ?? '')) ?>">
            <?= htmlspecialchars($course['category_name']) ?>
        </a>
        <span class="breadcrumb-item active"><?= htmlspecialchars($course['name']) ?></span>
    </nav>

    <div class="row g-4 flex-lg-row-reverse">
        <!-- Sidebar: Recommended/Trending (desktop) -->
        <aside class="col-lg-4 d-none d-lg-block">
            <div class="mb-4">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white border-0 pb-0">
                        <h3 class="h6 mb-0">Recommended for You</h3>
                    </div>
                    <div class="card-body pt-2">
                        <?php if (empty($recommended)): ?>
                            <p class="text-muted small">No recommended courses found.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($recommended as $rec): ?>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php $ri = (string)($rec['images'][0]['value'] ?? ''); $ri = ($ri !== '' && (str_starts_with($ri,'http') || str_starts_with($ri,'/'))) ? $ri : ($base . '/' . ltrim($ri,'/')); ?>
                                        <img src="<?= htmlspecialchars($ri) ?>" class="rounded border" alt="<?= htmlspecialchars($rec['name']) ?>" style="width: 64px; height: 64px; object-fit: cover;">
                                        <div class="flex-grow-1">
                                            <a href="course-details.php?slug=<?= rawurlencode((string)($rec['slug'] ?? '')) ?>" class="fw-semibold text-decoration-none text-dark d-block" style="font-size: 0.98rem; line-height:1.25;">
                                                <?= htmlspecialchars($rec['name']) ?>
                                            </a>
                                            <span class="text-muted" style="font-size: 0.9rem;">TZS <?= number_format($rec['price'], 0) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 pb-0">
                        <h3 class="h6 mb-0">Trending Today</h3>
                    </div>
                    <div class="card-body pt-2">
                        <?php if (empty($trending)): ?>
                            <p class="text-muted small">No trending courses found.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($trending as $trend): ?>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php $ti = (string)($trend['images'][0]['value'] ?? ''); $ti = ($ti !== '' && (str_starts_with($ti,'http') || str_starts_with($ti,'/'))) ? $ti : ($base . '/' . ltrim($ti,'/')); ?>
                                        <img src="<?= htmlspecialchars($ti) ?>" class="rounded border" alt="<?= htmlspecialchars($trend['name']) ?>" style="width: 64px; height: 64px; object-fit: cover;">
                                        <a href="course-details.php?slug=<?= rawurlencode((string)($trend['slug'] ?? '')) ?>" class="text-decoration-none text-dark" style="font-size: 0.98rem; line-height:1.25;">
                                            <?= htmlspecialchars($trend['name']) ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main course Section -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="row g-0 flex-column flex-md-row">
                    <div class="col-md-5 text-center p-3">
                        <?php if (!empty($course['thumbnail'])): ?>
                            <?php $mainImg = (string)$course['thumbnail']; $mainImg = (str_starts_with($mainImg,'http') || str_starts_with($mainImg,'/')) ? $mainImg : ($base . '/' . ltrim($mainImg,'/')); ?>
                            <img src="<?= htmlspecialchars($mainImg) ?>"
                                 alt="<?= htmlspecialchars($course['name']) ?>"
                                 class="img-fluid rounded shadow-sm mb-2 w-100" style="max-height:320px; object-fit:cover;">
                        <?php elseif (!empty($course['images'])): ?>
                            <?php $mainImg2 = (string)$course['images'][0]['value']; $mainImg2 = (str_starts_with($mainImg2,'http') || str_starts_with($mainImg2,'/')) ? $mainImg2 : ($base . '/' . ltrim($mainImg2,'/')); ?>
                            <img src="<?= htmlspecialchars($mainImg2) ?>"
                                 alt="<?= htmlspecialchars($course['name']) ?>"
                                 class="img-fluid rounded shadow-sm mb-2 w-100" style="max-height:320px; object-fit:cover;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center rounded" style="height: 180px;">
                                <span class="text-muted">No image available</span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 justify-content-center flex-wrap mt-2">
                            <?php if (!empty($course['images'])): ?>
                                <?php foreach ($course['images'] as $image): ?>
                                    <?php $gi = (string)($image['value'] ?? ''); $gi = ($gi !== '' && (str_starts_with($gi,'http') || str_starts_with($gi,'/'))) ? $gi : ($base . '/' . ltrim($gi,'/')); ?>
                                    <img src="<?= htmlspecialchars($gi) ?>" class="rounded border" alt="course Image" style="width:48px; height:48px; object-fit:cover;">
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php
                          // Determine a playable audio URL with the following priority:
                          // 1) courses.demo_audio_url (new explicit field)
                          // 2) first medias entry with file_type='audio'
                          // 3) courses.file_url if it's a direct audio file
                          $audioUrl = '';
                          if (!empty($course['demo_audio_url'])) {
                              $audioUrl = (string)$course['demo_audio_url'];
                          }
                          if ($audioUrl === '' && !empty($course['audios'][0]['value'])) {
                              $audioUrl = (string)$course['audios'][0]['value'];
                          }
                          if ($audioUrl === '' && !empty($course['file_url'])) {
                              $ext = strtolower(pathinfo(parse_url((string)$course['file_url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
                              if (in_array($ext, ['mp3','wav','ogg','m4a','aac'], true)) { $audioUrl = (string)$course['file_url']; }
                          }
                          // Build audio URL: always direct (no proxy)
                          $audioSrc = '';
                          if ($audioUrl !== '') {
                              if (str_starts_with($audioUrl, 'http://') || str_starts_with($audioUrl, 'https://')) {
                                  // Absolute URL: use as-is
                                  $audioSrc = $audioUrl;
                              } else {
                                  // Relative URL: prefix with base
                                  $audioSrc = (str_starts_with($audioUrl,'/')) ? ($base . $audioUrl) : ($base . '/' . ltrim($audioUrl,'/'));
                              }
                          }
                        ?>
                        <?php if (!empty($audioSrc)): ?>
                          <?php
                            $mime = 'audio/mpeg';
                            $ext = strtolower(pathinfo(parse_url($audioUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
                            if ($ext === 'mp3') { $mime = 'audio/mpeg'; }
                            elseif ($ext === 'wav') { $mime = 'audio/wav'; }
                            elseif ($ext === 'ogg' || $ext === 'oga') { $mime = 'audio/ogg'; }
                            elseif ($ext === 'm4a' || $ext === 'aac') { $mime = 'audio/aac'; }
                          ?>
                          <div class="mt-3">
                            <audio controls preload="none" style="width:100%; max-width:100%;">
                              <source src="<?= htmlspecialchars($audioSrc) ?>" type="<?= htmlspecialchars($mime) ?>">
                              Your browser does not support the audio element.
                            </audio>
                          </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-7 p-3 d-flex flex-column justify-content-between">
                        <div>
                            <h1 class="h4 fw-bold mb-2 text-break text-md-start text-center"><?= htmlspecialchars(trim(strip_tags($course['name']))) ?></h1>
                            <div class="mb-2 d-flex flex-wrap gap-2 align-items-center justify-content-md-start justify-content-center">
                                <span class="badge bg-warning text-dark">Course Category: <?= htmlspecialchars($course['category_name']) ?></span>
                                <span class="badge bg-dark text-warning">Size: <?= htmlspecialchars($course['file_size'] ?? '120 MB') ?></span>
                                <span class="badge bg-secondary">Type: <?= htmlspecialchars($course['file_type']) ?></span>
                                <?php if (!empty($course['license'])): ?>
                                    <span class="badge bg-info text-dark">License: <?= htmlspecialchars($course['license']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($course['publisher'])): ?>
                                    <span class="badge bg-light text-dark border">Publisher: <?= htmlspecialchars($course['publisher']) ?></span>
                                <?php endif; ?>
                            </div>
                            <ul class="list-unstyled small text-muted mb-2">
                                <li><strong>Release:</strong> <?= date('M d, Y', strtotime($course['created_at'])) ?></li>
                                <li><strong>Password:</strong> <span class="font-mono bg-light px-2 rounded"> <?= htmlspecialchars($course['password_hint'] ?? 'ak23studiokits.com') ?> </span></li>
                            </ul>
                            <?php if (!empty($course['original_source_url'])): ?>
                                <?php
                                  $srcUrl = (string)$course['original_source_url'];
                                  $srcHost = parse_url($srcUrl, PHP_URL_HOST);
                                  if (!$srcHost) { $srcHost = $srcUrl; }
                                ?>
                                <div class="mb-2">
                                  <a href="<?= htmlspecialchars($srcUrl) ?>" target="_blank" rel="noopener nofollow ugc" class="small text-decoration-none">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Original source: <?= htmlspecialchars($srcHost) ?>
                                  </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex flex-column flex-md-row align-items-center gap-2">
                                <?php if ((float)$course['price'] <= 0): ?>
                                    <span class="fs-5 fw-bold text-success">FREE</span>
                                <?php else: ?>
                                    <span class="fs-5 fw-bold text-warning">TZS <?= number_format($course['price'], 0) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($downloadSignedUrl)): ?>
                                    <a href="<?= htmlspecialchars($downloadSignedUrl) ?>" class="btn btn-success btn-lg w-100 w-md-auto">Download Now</a>
                                <?php else: ?>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <form action="initiate_payment.php" method="POST" class="d-inline w-100">
                                            <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
                                            <button type="submit" class="btn btn-<?= ((float)$course['price'] <= 0) ? 'success' : 'primary' ?> btn-lg w-100 w-md-auto">
                                                <?= ((float)$course['price'] <= 0) ? 'Get Free Download' : 'Pay & Unlock Download' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <?php
                                          $redir = 'course-details.php?slug=' . rawurlencode((string)$course['slug']);
                                          // Single CTA: clicking prompts login/registration flow after redirect
                                          $btnClass = ((float)$course['price'] <= 0) ? 'success' : 'primary';
                                          $btnLabel = ((float)$course['price'] <= 0) ? 'Get Free Download' : 'Pay & Unlock Download';
                                        ?>
                                        <a class="btn btn-<?= $btnClass ?> btn-lg w-100" href="login.php?redirect=<?= urlencode($redir) ?>"><?= $btnLabel ?></a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 text-sm text-muted text-center text-md-start">
                                <?php if ((float)$course['price'] <= 0): ?>
                                    <i class="bi bi-gift me-1"></i> Free courses are available instantly after sign-in (or provide your email)
                                <?php else: ?>
                                    <i class="bi bi-lock me-1"></i> Download links are available after payment
                                <?php endif; ?>
                            </div>
                            <!-- Share Bar -->
                            <?php
                              $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                              $pageUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
                              $shareTitle = (string)($course['name'] ?? '');
                              $wa = 'https://api.whatsapp.com/send?text=' . rawurlencode($shareTitle . ' - ' . $pageUrl);
                              $tw = 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareTitle) . '&url=' . rawurlencode($pageUrl);
                              $fb = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($pageUrl);
                              $tg = 'https://t.me/share/url?url=' . rawurlencode($pageUrl) . '&text=' . rawurlencode($shareTitle);
                            ?>
                            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center justify-content-center justify-content-md-start">
                                <span class="text-muted small">Share:</span>
                                <a href="<?= htmlspecialchars($wa) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i></a>
                                <a href="<?= htmlspecialchars($tw) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark"><i class="bi bi-twitter-x"></i></a>
                                <a href="<?= htmlspecialchars($fb) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-facebook"></i></a>
                                <a href="<?= htmlspecialchars($tg) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info"><i class="bi bi-telegram"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCopyLink"><i class="bi bi-link-45deg"></i></button>
                            </div>
                            <script>
                              (function(){
                                var b = document.getElementById('btnCopyLink');
                                if (!b) return;
                                b.addEventListener('click', async function(){
                                  try {
                                    await navigator.clipboard.writeText(window.location.href);
                                    b.classList.remove('btn-outline-secondary');
                                    b.classList.add('btn-success');
                                    b.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                                    setTimeout(function(){
                                      b.classList.remove('btn-success');
                                      b.classList.add('btn-outline-secondary');
                                      b.innerHTML = '<i class="bi bi-link-45deg"></i>';
                                    }, 1500);
                                  } catch (e) { alert('Link copy failed'); }
                                });
                              })();
                            </script>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-3 shadow-sm border-0">
                <div class="card-header bg-white border-0 pb-0 d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0">Description</h2>
                </div>
                <div class="card-body pt-2">
                    <?php $desc = $course['description'] ?? ''; ?>
                    <?php if (mb_strlen(strip_tags((string)$desc)) > 120): ?>
                        <div class="text-muted mb-0 desc-anim d-none" id="desc-short">
                            <?= htmlspecialchars(mb_strimwidth(trim(strip_tags((string)$desc)), 0, 120, '...')) ?>
                            <button class="animated-toggle-btn ms-2" id="showMoreDesc" type="button">Show More <i class="bi bi-chevron-down"></i></button>
                        </div>
                        <div class="text-muted mb-0 desc-anim" id="desc-full">
                            <?= sanitize_html($desc) ?>
                            <button class="animated-toggle-btn ms-2" id="showLessDesc" type="button">Show Less <i class="bi bi-chevron-up"></i></button>
                        </div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var showMore = document.getElementById('showMoreDesc');
                            var showLess = document.getElementById('showLessDesc');
                            var shortDesc = document.getElementById('desc-short');
                            var fullDesc = document.getElementById('desc-full');
                            if (showMore && showLess && shortDesc && fullDesc) {
                                showMore.addEventListener('click', function() {
                                    shortDesc.classList.add('d-none');
                                    fullDesc.classList.remove('d-none');
                                });
                                showLess.addEventListener('click', function() {
                                    fullDesc.classList.add('d-none');
                                    shortDesc.classList.remove('d-none');
                                });
                            }
                        });
                        </script>
                    <?php else: ?>
                        <div class="text-muted mb-0">
                            <?= sanitize_html($desc) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Description Modal -->
            <div class="modal fade" id="descModal" tabindex="-1" aria-labelledby="descModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="descModalLabel">Full Description</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="text-muted">
                      <?= sanitize_html($course['description'] ?? '') ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Mobile: Recommended/Trending stacked below -->
            <div class="d-lg-none">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white border-0 pb-0">
                        <h3 class="h6 mb-0">Recommended for You</h3>
                    </div>
                    <div class="card-body pt-2">
                        <?php if (empty($recommended)): ?>
                            <p class="text-muted small">No recommended courses found.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($recommended as $rec): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php $ri2 = (string)($rec['images'][0]['value'] ?? ''); $ri2 = ($ri2 !== '' && (str_starts_with($ri2,'http') || str_starts_with($ri2,'/'))) ? $ri2 : ($base . '/' . ltrim($ri2,'/')); ?>
                                        <img src="<?= htmlspecialchars($ri2) ?>" class="rounded border" alt="<?= htmlspecialchars($rec['name']) ?>" style="width: 44px; height: 44px; object-fit: cover;">
                                        <div>
                                            <a href="course-details.php?slug=<?= rawurlencode((string)($rec['slug'] ?? '')) ?>" class="fw-semibold text-decoration-none text-dark small d-block">
                                                <?= htmlspecialchars($rec['name']) ?>
                                            </a>
                                            <span class="text-xs text-muted">TZS <?= number_format($rec['price'], 0) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white border-0 pb-0">
                        <h3 class="h6 mb-0">Trending Today</h3>
                    </div>
                    <div class="card-body pt-2">
                        <?php if (!empty($trending)): ?>
                          <?php $redir = 'course-details.php?slug=' . rawurlencode((string)$course['slug']); ?>
                          <?php if (isset($_SESSION['user_id'])): ?>
                            <form action="initiate_payment.php" method="POST" class="w-100">
                              <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
                              <button type="submit" class="btn btn-<?= ((float)$course['price'] <= 0) ? 'success' : 'primary' ?> btn-lg w-100">
                                <?= ((float)$course['price'] <= 0) ? 'Download For Free' : 'Pay & Unlock Download' ?>
                              </button>
                            </form>
                          <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                              <a class="btn btn-outline-secondary btn-lg w-100" href="login.php?redirect=<?= urlencode($redir) ?>">Login to Continue</a>
                              <a class="btn btn-<?= ((float)$course['price'] <= 0) ? 'success' : 'primary' ?> btn-lg w-100" href="register.php?redirect=<?= urlencode($redir) ?>">Create Account</a>
                            </div>
                          <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2 text-sm text-muted text-center text-md-start">
                        <?php if ((float)$course['price'] <= 0): ?>
                            <i class="bi bi-gift me-1"></i> Free courses are available instantly after sign-in (or provide your email)
                        <?php else: ?>
                            <i class="bi bi-lock me-1"></i> Download links are available after payment
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    </div>
<?php endif; ?>
</div>

  <?php /* Guest modal removed: we now require login/registration before purchase */ ?>

<style>
.animated-toggle-btn {
    background: #FFD600;
    color: #111;
    font-weight: 700;
    border: none;
    border-radius: 6px;
    padding: 4px 18px;
    box-shadow: 0 1px 4px #0001;
    transition: transform 0.18s cubic-bezier(.4,2,.6,1), box-shadow 0.18s;
    outline: none;
    font-size: 1em;
    display: inline-flex;
    align-items: center;
    gap: 0.4em;
}
.animated-toggle-btn:hover, .animated-toggle-btn:focus {
    transform: scale(1.07);
    box-shadow: 0 4px 16px #FFD60044;
    color: #23272b;
    background: #ffe066;
}
.desc-anim {
    transition: max-height 0.35s cubic-bezier(.4,2,.6,1), opacity 0.25s;
    overflow: hidden;
}
.desc-anim.d-none { max-height: 0; opacity: 0; }
.desc-anim:not(.d-none) { max-height: 1000px; opacity: 1; }
/* Ensure embedded media displays nicely */
.card-body audio, .card-body video {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
    margin: 0.5rem 0;
}
/* Buy Modal enhancements */
#buyModal .modal-dialog { max-width: 480px; margin: 1rem auto; }
#buyModal .modal-content { border-radius: 1rem; }
#buyModal .modal-body { padding: 1rem 1.25rem; }
.btn-pay {
    background: #ffe066;
    color: #23272b;
    border: none;
    transition: transform 0.18s cubic-bezier(.4,2,.6,1), box-shadow 0.18s, background-color 0.18s, color 0.18s;
}
.btn-pay:hover, .btn-pay:focus {
    transform: scale(1.07);
    box-shadow: 0 4px 16px #FFD60044;
    color: #23272b;
    background: #ffe066;
}
</style>