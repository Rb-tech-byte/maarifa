<?php
// This file renders the home page content within the header/footer shell
// Assumes variables are already prepared by index.php: $categories, $courses, $featured_courses, $trendingCourses, $recentPosts, $audioLibCatId, $audioLibCourses
require_once __DIR__ . '/../includes/base.php';
?>

<!-- Search Bar -->
<div style="display:flex;justify-content:center;margin-top:0;">
    <form id="course-search-form" action="" method="get" autocomplete="off" style="width:100%;max-width:600px;position:relative;">
        <div class="search-input-wrapper" style="position:relative;display:flex;align-items:center;">
            <i class="bi bi-search" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#FF6600;font-size:1.3rem;"></i>
            <input 
                type="text" 
                id="course-search-input" 
                name="q" 
                class="form-control" 
                placeholder="Search courses..." 
                aria-label="Search courses"
                style="width:100%;padding-left:2.8rem;padding-right:2.5rem;height:2.75rem;border-radius:2rem;border:1px solid rgba(218, 98, 18, 0.91);font-size:1rem;"
            >
            <button 
                type="button" 
                id="search-clear-btn" 
                style="display:none;position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#FF6600;font-size:1.2rem;cursor:pointer;"
                aria-label="Clear search"
            >
                <i class="bi bi-x-circle"></i>
            </button>
            <div id="search-loading-spinner" style="display:none;position:absolute;right:2.5rem;top:50%;transform:translateY(-50%);">
                <span class="spinner-border spinner-border-sm" style="color:#FF6600;"></span>
            </div>
        </div>
        <div id="search-suggestions" class="search-suggestions-dropdown" style="display:none;position:absolute;top:110%;left:0;width:100%;background:#fff;box-shadow:0 4px 24px #FF660033;border-radius:0.7rem;z-index:9999;max-height:340px;overflow-y:auto;"></div>
    </form>
</div>

<script src="<?= $base ?>/assets/js/config.php"></script>
<script src="<?= $base ?>/assets/js/search-suggest.js" defer></script>

<div class="container content-wrap">
  <main class="main-col" style="width:100%">
    <h2 class="section-title text-center w-100" style="text-align:center!important;margin-left:auto!important;margin-right:auto!important;">DOWNLOAD YOUR FAVOURITE MUSIC TOOLS</h2>
    <p class="section-disclaimer text-center" style="font-size:1.1rem; font-weight:400; margin-top:.25rem; margin-bottom:.8rem; color:#6c757d;">
      <i class="bi bi-info-circle-fill" aria-hidden="true" style="margin-right:.5rem; color:#FFAA00;"></i>
      Muhimu! Haununui software, plugin wala Sample yoyote, hivi vyote tunatoa bure kwa madhumuni ya kujifunzia, pesa kidogo unayolipia ni kwa ajili ya gharama za uhifadhi na uendeshaji wa tovuti hii.
    </p>

    <div class="course-grid">
      <?php if (empty($courses)): ?>
        <div class="no-courses-message">
          <p>No courses found. Please try another category.</p>
        </div>
      <?php else: ?>
        <?php foreach ($courses as $course): ?>
          <?php
          $imgSrc = !empty($course['cover_image']) ? $course['cover_image'] : 'assets/images/ak.png';
          $imgOut = (str_starts_with($imgSrc, 'http') || str_starts_with($imgSrc, '/')) ? $imgSrc : $base . '/' . ltrim($imgSrc, '/');
          $courseUrl = $base . '/course-details.php?slug=' . rawurlencode((string)($course['slug'] ?? ''));
          $courseTitle = $course['title'] ?? $course['name'] ?? '';
          $isFree = !(isset($course['price']) && $course['price'] && $course['price'] > 0);
          $priceDisplay = $isFree ? 'FREE' : ('TSH ' . number_format((float)($course['price'] ?? 0), 0));
          $courseTitle = $course['title'] ?? $course['name'] ?? '';
          ?>
          <div class="course-card-wrapper">
            <a href="<?php echo $courseUrl; ?>" class="course-card-link">
              <div class="course-card" tabindex="0">
                <div class="course-image-container">
                  <img src="<?php echo htmlspecialchars($imgOut ?? ''); ?>" alt="<?php echo htmlspecialchars($courseTitle); ?>" class="course-image" loading="lazy">
                </div>
                <div class="course-info">
                  <h3 class="course-title"><?php echo htmlspecialchars($courseTitle); ?></h3>
                  <div class="course-price<?php echo $isFree ? ' free' : ''; ?>"><?php echo $priceDisplay; ?></div>
                  <div class="course-meta"><?php echo htmlspecialchars($course['category_name'] ?? ''); ?> &middot; <?php echo !empty($course['created_at']) ? date('M d, Y', strtotime($course['created_at'])) : ''; ?></div>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if (isset($courses_total_pages) && (int)$courses_total_pages > 1): ?>
      <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center mt-3">
          <li class="page-item <?= ($courses_page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= max(1, (int)$courses_page - 1) ?>">Previous</a>
          </li>
          <?php for ($i = 1; $i <= (int)$courses_total_pages; $i++): ?>
            <li class="page-item <?= ($i === (int)$courses_page) ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= ($courses_page >= (int)$courses_total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= min((int)$courses_total_pages, (int)$courses_page + 1) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>

    <hr class="section-divider">
    <h2 class="section-title text-center w-100" style="text-align:center!important;margin-left:auto!important;margin-right:auto!important;">Trending This Week</h2>
    <div class="course-grid">
      <?php foreach ($trendingcourses as $course): ?>
        <?php
        $imgSrc = $base . '/assets/images/ak.png';
        if (!empty($course['thumbnail'])) { $imgSrc = (string)$course['thumbnail']; }
        elseif (!empty($course['images'])) { $images = $course['images']; if (is_array($images) && !empty($images[0]['value'])) { $imgSrc = (string)$images[0]['value']; } }
        $imgOut = $imgSrc;
        if (str_starts_with($imgOut, 'http')) {
          $hostNow = $_SERVER['HTTP_HOST'] ?? '';
          $httpsNow = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
          $parts = @parse_url($imgOut);
          $imgHost = $parts['host'] ?? '';
          $imgScheme = strtolower($parts['scheme'] ?? '');
          if ($httpsNow && $imgScheme === 'http' && $imgHost !== '' && strcasecmp($imgHost, $hostNow) === 0) {
            $imgOut = preg_replace('#^http://#i', 'https://', $imgOut);
          }
        } elseif (str_starts_with($imgOut, '/')) {
          // leave as absolute path
        } else {
          $imgOut = $base . '/' . ltrim($imgOut, '/');
        }
        $courseUrl = $base . '/course-details.php?slug=' . rawurlencode((string)($course['slug'] ?? ''));
        $isFree = !(isset($course['price']) && $course['price'] && $course['price'] > 0);
        $priceDisplay = $isFree ? 'FREE' : ('TSH ' . number_format((float)($course['price'] ?? 0), 0));
        $courseTitle = $course['title'] ?? $course['name'] ?? '';
        $categoryName = htmlspecialchars($course['category_name'] ?? 'Uncategorized');
        ?>
        <div class="course-card-wrapper">
          <a href="<?php echo $courseUrl; ?>" class="course-card-link">
            <div class="course-card" tabindex="0">
              <div class="course-image-container">
                <?php if (!empty($imgOut)): ?>
                  <img src="<?php echo htmlspecialchars($imgOut ?? ''); ?>" alt="<?php echo htmlspecialchars($courseTitle); ?>" class="course-image" loading="lazy">
                <?php else: ?>
                  <div class="course-image placeholder" aria-hidden="true"></div>
                <?php endif; ?>
                <button class="course-action-btn" title="Download" onclick="event.stopPropagation();"><i class="bi bi-download"></i></button>
              </div>
              <div class="course-info">
                <h3 class="course-title"><?php echo htmlspecialchars($courseTitle); ?></h3>
                <div class="course-price<?php echo $isFree ? ' free' : ''; ?>"><?php echo $priceDisplay; ?></div>
                <div class="course-meta"><?php echo $categoryName; ?></div>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>


    <!-- Audio Library Section -->
    <?php if ($audioLibCatId && !empty($audioLibcourses)): ?>
      <hr class="section-divider">
      <div class="section-title" style="color:#007bff;background:#ffe5b4;padding:0.5rem 1rem;border-radius:8px;">Audio Library</div>
      <div class="course-grid">
        <?php foreach ($audioLibcourses as $course): ?>
          <?php
          if (!empty($course['thumbnail'])) { $imgSrc = (string)$course['thumbnail']; }
          elseif (!empty($course['images'])) { $imgSrc = (string)$course['images'][0]['value']; }
          else { $imgSrc = ''; }
          $imgOut = $imgSrc;
          if ($imgOut !== '') {
            if (str_starts_with($imgOut, 'http')) {
              $hostNow = $_SERVER['HTTP_HOST'] ?? '';
              $httpsNow = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
              $parts = @parse_url($imgOut);
              $imgHost = $parts['host'] ?? '';
              $imgScheme = strtolower($parts['scheme'] ?? '');
              if ($httpsNow && $imgScheme === 'http' && $imgHost !== '' && strcasecmp($imgHost, $hostNow) === 0) {
                $imgOut = preg_replace('#^http://#i', 'https://', $imgOut);
              }
            } elseif (str_starts_with($imgOut, '/')) {
              // leave as absolute path
            } else {
              $imgOut = $base . '/' . ltrim($imgOut, '/');
            }
          }
          $courseUrl = $base . '/course-details.php?slug=' . rawurlencode((string)($course['slug'] ?? ''));
          ?>
          <a href="<?php echo $courseUrl; ?>" class="course-card-link" style="text-decoration:none;color:inherit;">
            <div class="course-card" tabindex="0" style="cursor:pointer; min-height: 340px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; background:#f8f9fa;">
              <button class="course-action-btn" title="Download" onclick="event.stopPropagation();" style="background:#ffe5b4;color:#007bff;"><i class="bi bi-download"></i></button>
              <div class="course-img" style="height: 40%; min-height: 120px; max-height: 160px; width: 100%; display: flex; align-items: center; justify-content: center;">
                <?php if (!empty($imgOut)): ?>
                  <img src="<?php echo htmlspecialchars($imgOut ?? ''); ?>" alt="<?php echo htmlspecialchars($courseTitle); ?>" style="height: 100%; width: auto; max-width: 100%; object-fit: cover;">
                <?php else: ?>
                  <div class="course-image placeholder" style="height:100%;width:100%" aria-hidden="true"></div>
                <?php endif; ?>
              </div>
              <div class="course-title" style="color:#007bff;"><?php echo htmlspecialchars($courseTitle); ?></div>
              <div class="course-price<?php echo $isFree ? ' free' : ''; ?>"><?php echo $isFree ? 'FREE' : ('TSH ' . number_format((float)$course['price'], 0)); ?></div>
              <div class="course-meta" style="color:#FFD700;"><?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized'); ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<style>
.course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.5rem; margin: 1.5rem 0; }
.course-card-wrapper { position: relative; transition: transform 0.2s; }
.course-card-link { text-decoration: none; color: inherit; display: block; height: 100%; }
.course-card { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1); transition: all 0.2s ease; height:100%; display:flex; flex-direction:column; }
.course-card:hover { transform: translateY(-5px); box-shadow:0 5px 15px rgba(0,0,0,0.15); }
.course-image-container { position:relative; width:100%; padding-top:75%; background:#f5f5f5; overflow:hidden; }
.course-image { position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; padding:10px; background:#fff; border-radius:8px; transition: transform 0.3s; }
.course-card:hover .course-image { transform: scale(1.03); }
.course-action-btn { position:absolute; top:10px; right:10px; width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.9); border:none; color:#FF6600; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:2; transition: all 0.2s; }
.course-action-btn:hover { background:#FF6600; color:white; }
.course-info { padding:1rem; flex-grow:1; display:flex; flex-direction:column; }
.course-title { font-size:1rem; font-weight:600; color:#000; margin:0 0 0.5rem 0; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.course-price { font-weight:700; color:#FFAA00; margin:0.25rem 0; font-size:1.1rem; }
.course-price.free { color:#16a34a !important; }
.course-meta { font-size:0.85rem; color:#555; margin-top:auto; }
.section-title {
  font-size: 1.6rem !important;
  font-weight: 700 !important;
  color: #000 !important;
  margin: 1.5rem auto 1rem auto !important;
  text-align: center !important;
  width: 100% !important;
  line-height: 1.2 !important;
  display: block !important;
}
.section-divider { border: 0; height: 1px; background: #eee; margin: 2rem 0; }
.no-courses-message { grid-column: 1 / -1; text-align: center; padding: 3rem 0; color: #666; }
.sidebar-inline-section { display:flex; gap:2rem; margin:2rem 0; justify-content:center; }
.sidebar-inline-section .card { min-width:220px; max-width:320px; flex:1 1 220px; }
/* Gradient text utility: black -> yellow */
.gradient-text {
  color: #000; /* fallback */
  background: linear-gradient(90deg, #000000 0%, #FFAA00 100%);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
/* Disclaimer pill background */
.disclaimer-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .35rem;
  background: linear-gradient(180deg, #fff7e0 0%, #fff3cc 100%);
  border: 1px solid rgba(255, 170, 0, 0.35);
  border-radius: 999px;
  padding: .5rem .9rem;
  margin-left: auto;
  margin-right: auto;
}
  @media (max-width: 768px) {
    .course-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; align-items: start; }
    .course-card-wrapper, .course-card-link, .course-card { height: auto !important; min-height: 0 !important; }
  }
  @media (max-width: 480px) { .course-grid { grid-template-columns: repeat(2, 1fr); } .course-title { font-size:0.9rem; } .course-price { font-size:1rem; } }
  /* Mobile: hide meta row to reduce clutter */
  @media (max-width: 576px) {
    .course-grid { align-items: start; }
    .course-meta { display: none !important; }
    .course-card-wrapper { height: auto !important; min-height: 0 !important; align-self: start; }
    .course-card-link { height: auto !important; min-height: 0 !important; display:block; }
    .course-card { height: auto !important; min-height: 0 !important; display:flex !important; flex-direction: column; align-items: flex-start; }
    .course-info { flex-grow: 0; min-height: 0; padding: .6rem .65rem .5rem .65rem; }
    .course-title { margin: 0 0 .35rem 0; }
    .course-price { margin: 0; }
    .course-image { padding: 6px; }
    .course-image-container { padding-top: 60% !important; }
    .course-title { -webkit-line-clamp: 1; }
  }
  @media (max-width: 420px) {
    .course-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; }
  }
</style>
