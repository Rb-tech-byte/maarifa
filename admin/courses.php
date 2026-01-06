<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

checkAdminAuth();
$title = 'courses - AK23 App';
include '../includes/admin_header.php';
// Search and pagination
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause (courses table does not use deleted_at; show all)
$where = 'WHERE 1=1';
$params = [];
if ($q !== '') {
  // Use distinct placeholders to avoid HY093 on some PDO drivers
  // courses table uses title and short_description instead of name/description
  $where .= ' AND (title LIKE :q1 OR slug LIKE :q2 OR short_description LIKE :q3)';
  $like = '%' . $q . '%';
  $params[':q1'] = $like;
  $params[':q2'] = $like;
  $params[':q3'] = $like;
}

// Count total
$cntSql = "SELECT COUNT(*) FROM courses $where";
$cntStmt = $pdo->prepare($cntSql);
foreach ($params as $k => $v) {
  $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
}
$cntStmt->execute();
$total = (int)$cntStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $limit));

// Fetch page (title is the correct column; alias as name for UI compatibility)
$sql = "SELECT id, title AS name, slug, price, promo_video, created_at FROM courses $where ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
.form-label, .form-control, .form-select, .form-check-label {
  color: #000 !important;
}
</style>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success">course saved successfully.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">course deleted successfully.</div><?php endif; ?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
  <h1 class="h3" style="color:#000;">courses</h1>
  <div class="d-flex flex-column flex-sm-row gap-2">
    <form method="get" class="d-flex gap-2">
      <input type="text" name="q" class="form-control" placeholder="Search name, slug or description..." value="<?= htmlspecialchars($q) ?>">
      <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-search"></i> Search</button>
      <?php if ($q !== ''): ?><a href="courses.php" class="btn btn-outline-dark">Reset</a><?php endif; ?>
    </form>
    <a href="add_course.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add course</a>
  </div>
</div>
<form method="post" action="bulk_courses.php" id="bulkForm">
  <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2">
    <div class="small text-muted">Bulk actions on selected courses</div>
    <div class="d-flex gap-2 align-items-center">
      <select name="action" id="bulkAction" class="form-select form-select-sm" style="width:auto;">
        <option value="duplicate">Duplicate</option>
        <option value="activate">Activate</option>
        <option value="deactivate">Deactivate</option>
        <option value="feature">Feature</option>
        <option value="unfeature">Unfeature</option>
        <option value="delete">Delete</option>
      </select>
      <button type="submit" class="btn btn-outline-secondary btn-sm" id="bulkSubmitBtn" disabled>
        Apply
      </button>
    </div>
  </div>
  <div class="table-responsive">
  <table class="table table-striped table-bordered align-middle">
    <thead class="table-dark">
      <tr>
        <th style="width:36px"><input type="checkbox" id="checkAll"></th>
        <th>ID</th>
        <th>Name</th>
        <th>Promo</th>
        <th>Price</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($courses as $course): ?>
        <?php
          // Compute embed preview if promo_video present
          $embed = '';
          if (!empty($course['promo_video'])) {
            $url = (string)$course['promo_video'];
            $parts = @parse_url($url);
            if ($parts && !empty($parts['host'])) {
              $host = strtolower($parts['host']);
              if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtu.be') !== false) {
                $query = [];
                if (!empty($parts['query'])) parse_str($parts['query'], $query);
                $vid = isset($query['v']) ? $query['v'] : '';
                if ($vid === '' && !empty($parts['path']) && preg_match('~^/([A-Za-z0-9_-]{6,})~', $parts['path'], $m)) { $vid = $m[1]; }
                if ($vid !== '') {
                  unset($query['v']);
                  $qs = http_build_query($query);
                  $embed = 'https://www.youtube.com/embed/' . $vid . ($qs ? ('?' . $qs) : '');
                }
              } elseif (strpos($host, 'vimeo.com') !== false) {
                if (!empty($parts['path']) && preg_match('~^/(?:video/)?([0-9]+)~', $parts['path'], $m)) {
                  $embed = 'https://player.vimeo.com/video/' . $m[1];
                }
              }
            }
          }
        ?>
        <tr>
          <td><input type="checkbox" class="row-check" name="ids[]" value="<?= (int)$course['id'] ?>"></td>
          <td><?= $course['id'] ?></td>
          <td><?= htmlspecialchars($course['name']) ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($embed !== ''): ?>
                <span class="badge bg-success promo-badge" data-embed="<?= htmlspecialchars($embed) ?>">Has Video</span>
              <?php else: ?>
                <span class="badge bg-secondary">None</span>
              <?php endif; ?>
              <button type="button" class="btn btn-sm btn-outline-primary attach-video-btn" data-course-id="<?= (int)$course['id'] ?>">+ Attach Video</button>
            </div>
          </td>
          <td>
            <?= ((float)$course['price'] <= 0)
              ? '<span class="badge bg-success">FREE</span>'
              : 'TZS ' . number_format((float)$course['price'], 0) ?>
          </td>
          <td><?= htmlspecialchars($course['created_at']) ?></td>
          <td>
            <a href="edit_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</a>
            <a href="duplicate_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Duplicate this course"><i class="fas fa-copy"></i> Duplicate</a>
            <a href="delete_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this course?');"><i class="fas fa-trash"></i> Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
  // Pagination controls
  if ($total_pages > 1) {
    // Preserve q in links
    $baseQS = [];
    if ($q !== '') { $baseQS['q'] = $q; }
    echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    $prev = max(1, $page - 1);
    $next = min($total_pages, $page + 1);
    $qsPrev = http_build_query($baseQS + ['page' => $prev]);
    $qsNext = http_build_query($baseQS + ['page' => $next]);
    echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="?'. $qsPrev .'">Previous</a></li>';
    for ($i = 1; $i <= $total_pages; $i++) {
      $qsI = http_build_query($baseQS + ['page' => $i]);
      $active = ($i === $page) ? ' active' : '';
      echo '<li class="page-item'. $active .'"><a class="page-link" href="?' . $qsI . '">' . $i . '</a></li>';
    }
    echo '<li class="page-item ' . ($page >= $total_pages ? 'disabled' : '') . '"><a class="page-link" href="?'. $qsNext .'">Next</a></li>';
    echo '</ul></nav>';
  }
?>
<style>
.promo-preview-box {
  position: fixed; z-index: 2000; width: 260px; height: 146px; /* ~16:9 */
  background: #000; border-radius: 8px; overflow: hidden; box-shadow: 0 6px 22px rgba(0,0,0,0.25);
  pointer-events: none; opacity: 0; transition: opacity .12s ease;
}
.promo-preview-box iframe { width: 100%; height: 100%; border: 0; }
</style>
<script>
(function(){
  // --- Promo video hover preview ---
  var preview;
  function showPreview(embedUrl, x, y){
    if (!preview){
      preview = document.createElement('div');
      preview.className = 'promo-preview-box';
      var ifr = document.createElement('iframe');
      ifr.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      ifr.referrerPolicy = 'strict-origin-when-cross-origin';
      ifr.allowFullscreen = true;
      preview.appendChild(ifr);
      document.body.appendChild(preview);
    }
    preview.querySelector('iframe').src = embedUrl;
    preview.style.left = (x + 12) + 'px';
    preview.style.top = (y + 12) + 'px';
    preview.style.opacity = '1';
  }
  function hidePreview(){ if (preview){ preview.style.opacity = '0'; } }

  document.addEventListener('mouseover', function(e){
    var t = e.target;
    if (t && t.classList.contains('promo-badge')){
      var url = t.getAttribute('data-embed');
      if (url){ showPreview(url, e.clientX, e.clientY); }
    }
  });
  document.addEventListener('mousemove', function(e){
    if (preview && preview.style.opacity === '1'){
      preview.style.left = (e.clientX + 12) + 'px';
      preview.style.top = (e.clientY + 12) + 'px';
    }
  });
  document.addEventListener('mouseout', function(e){
    var t = e.target;
    if (t && t.classList.contains('promo-badge')){ hidePreview(); }
  });

  // --- Bulk selection enable/disable ---
  var checkAll = document.getElementById('checkAll');
  var bulkBtn = document.getElementById('bulkSubmitBtn');
  function updateState(){
    var anyChecked = !!document.querySelector('.row-check:checked');
    if (bulkBtn) bulkBtn.disabled = !anyChecked;
  }
  document.addEventListener('change', function(e){
    if (e.target && e.target.id === 'checkAll'){
      var rows = document.querySelectorAll('.row-check');
      rows.forEach(function(cb){ cb.checked = checkAll.checked; });
      updateState();
    }
    if (e.target && e.target.classList && e.target.classList.contains('row-check')){
      updateState();
    }
  });
  updateState();

  // --- Attach video via AJAX ---
  function attachVideo(courseId, url){
    var fd = new FormData();
    fd.append('action', 'attach_promo_video');
    fd.append('course_id', courseId);
    fd.append('url', url);
    fetch('ajax_handler.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(j){ if (j && j.success) { location.reload(); } else { alert((j && j.message) ? j.message : 'Failed to attach video.'); } })
      .catch(function(){ alert('Network error while attaching video.'); });
  }
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.attach-video-btn');
    if (btn){
      var pid = btn.getAttribute('data-course-id');