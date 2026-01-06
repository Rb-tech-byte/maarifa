<?php
// Start PHP block for headers or logic
require_once 'inc/category_functions.php'; // Ensure category_functions.php is included early
require_once __DIR__ . '/../includes/base.php';
?>

<body style="background: #fff; color: #222;">
    <header class="sticky-header">
        <div class="navbar navbar-dark py-2" style="background: #000; box-shadow: 0 2px 12px #0002;">
            <div class="container d-flex align-items-center justify-content-between">
                <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base ?>/index.php">
                    <img src="<?= $base ?>/assets/images/ak.png" alt="Logo" class="logo-img">
                    <span class="logo-text">
                        <h1 style="color:#fff;">AK23DOWNLOADS</h1>
                    </span>
                </a>
                <button class="navbar-toggler d-lg-none ms-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNav" aria-controls="offcanvasNav" aria-label="Toggle navigation"
                    style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem; background: #000; color: #fff; border-radius: 8px; padding: 0.4rem 1rem; border: none; z-index: 1201; position: relative;">
                    <i class="bi bi-list" style="font-size: 1.7rem;"></i>
                    <span style="font-weight: 700; color:#fff; letter-spacing: 1px;">Menu</span>
                </button>
            </div>
        </div>
    </header>
    <nav class="main-nav navbar navbar-expand-lg navbar-dark d-none d-lg-block" style="background: #fff; box-shadow: none; border: none; margin-bottom: 0; padding-bottom: 0;">
        <div class="container d-flex align-items-center justify-content-between" style="padding: 0 1.5rem;">
            <ul class="navbar-nav flex-row gap-3 mx-0 mb-0" style="flex:1;justify-content:center;">
                <?php
                $cats = $categoryTree[0] ?? [];
                foreach ($cats as $cat) {
                    $subcats = $categoryTree[$cat['id']] ?? [];
                    if (!empty($subcats)) {
                        echo '<li class="nav-item dropdown">'
                           . '<a class="nav-link dropdown-toggle px-3 py-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"'
                           . ' style="color:#222;font-weight:700;font-size:1.08rem;border-radius:6px;transition:all 0.18s;background:#fff;">'
                           . htmlspecialchars($cat['name'])
                           . '</a>'
                           . '<ul class="dropdown-menu">';
                        foreach ($subcats as $sc) {
                            $scSlug = rawurlencode((string)($sc['slug'] ?? ''));
                            $scNameRaw = (string)($sc['name'] ?? '');
                            // Remove trailing count e.g. " (12)"
                            $scNameClean = preg_replace('/\s*\(\d+\)\s*$/', '', $scNameRaw);
                            $scName = htmlspecialchars($scNameClean);
                            echo '<li><a class="dropdown-item" href="' . $base . '/categories-details.php?slug=' . $scSlug . '">' . $scName . '</a></li>';
                        }
                        echo '</ul></li>';
                    } else {
                        echo '<li class="nav-item"><a class="nav-link px-3 py-2" style="color:#222;font-weight:700;font-size:1.08rem;border-radius:6px;transition:all 0.18s;background:#fff;"'
                           . ' href="' . $base . '/categories-details.php?slug=' . rawurlencode((string)($cat['slug'] ?? '')) . '">' . htmlspecialchars($cat['name']) . '</a></li>';
                    }
                }
                ?>
            </ul>
        </div>
    </nav>
    <div class="main-nav-accent d-none d-lg-block" style="height:0;margin:0;padding:0;border:0;"></div>
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="offcanvasNav" aria-labelledby="offcanvasNavLabel" style="z-index: 2000; background: #cc7648ff;">
        <div class="offcanvas-header" style="background: #090707ff; border-bottom: 1px solid #ddd; display: flex; align-items: center; justify-content: space-between;">
            <h5 class="offcanvas-title" id="offcanvasNavLabel" style="color:#fff; font-weight: 700;">Menu</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body" style="background:#222;">
            <ul class="navbar-nav">
            <?php
            foreach ($cats as $cat) {
                $subcats = $categoryTree[$cat['id']] ?? [];
                $catName = htmlspecialchars($cat['name'] ?? '');
                if (!empty($subcats)) {
                    echo '<li class="nav-item">'
                       . '<span class="nav-link" style="color:#fff;font-weight:700;font-size:1.08rem;">' . $catName . '</span>'
                       . '<ul class="list-unstyled ms-3 mb-2">';
                    foreach ($subcats as $sc) {
                        $scSlug = rawurlencode((string)($sc['slug'] ?? ''));
                        $scNameRaw = (string)($sc['name'] ?? '');
                        $scNameClean = preg_replace('/\s*\(\d+\)\s*$/', '', $scNameRaw);
                        $scName = htmlspecialchars($scNameClean);
                        echo '<li><a class="nav-link" style="color:#ddd;font-weight:600;font-size:1rem;" href="' . $base . '/categories-details.php?slug=' . $scSlug . '">' . $scName . '</a></li>';
                    }
                    echo '</ul></li>';
                } else {
                    echo '<li class="nav-item"><a class="nav-link" style="color:#fff;font-weight:700;font-size:1.08rem;border-radius:6px;transition:all 0.18s;background:#333;" href="' . $base . '/categories-details.php?slug=' . rawurlencode((string)($cat['slug'] ?? '')) . '">' . $catName . '</a></li>';
                }
            }
            ?>
            </ul>
        </div>
    </div>
<!-- Search bar hidden on desktop (≥992px) to avoid duplication -->
<div class="d-lg-none" style="display:flex;justify-content:center;margin-top:0;">
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
                style="width:100%;padding-left:2.8rem;padding-right:2.5rem;height:3rem;border-radius:0.7rem;border:1px solid rgba(218, 98, 18, 0.91);font-size:1rem;"
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

<script src="<?= $base ?>/assets/js/search-suggest.js" defer></script>

<style>
@media (max-width: 600px) {
    .search-bar-container {
        padding: 0.5rem;
    }
    #course-search-form {
        max-width: 95vw;
    }
    .search-input-wrapper input {
        height: 2.7rem;
    }
    .suggestion-item {
        flex-direction: row;
        gap: 0.7rem;
    }
}
</style><div class="page-wrapper">
  <div class="container content-wrap">
    <main class="main-col">
      <h2 class="section-title text-center w-100" style="text-align:center!important;margin-left:auto!important;margin-right:auto!important;">DOWNLOAD YOUR FAVOURITE TOOLS</h2>
      
      <div class="course-grid">
        <?php if (empty($courses)): ?>
          <div class="no-courses-message">
            <p>No courses found. Please try another category.</p>
          </div>
        <?php else: ?>
          <?php foreach ($courses as $course): ?>
            <?php
            $imgSrc = '';
            if (!empty($course['thumbnail'])) {
              $imgSrc = htmlspecialchars($course['thumbnail']);
            } elseif (!empty($course['images'])) {
              $images = json_decode($course['images'], true);
              if (is_array($images) && !empty($images[0]['value'])) {
                $imgSrc = htmlspecialchars($images[0]['value']);
              }
            }

            $courseUrl = $base . '/course-details.php?slug=' . rawurlencode((string)($course['slug'] ?? ''));
            $priceDisplay = ($course['price'] && $course['price'] > 0)
              ? 'TSH ' . number_format($course['price'], 0)
              : 'FREE';
            $categoryName = htmlspecialchars($course['category_name'] ?? 'Uncategorized');
            ?>
            
            <div class="course-card-wrapper">
              <a href="<?php echo $courseUrl; ?>" class="course-card-link">
                <div class="course-card" tabindex="0">
                  <div class="course-image-container">
                    <?php if (!empty($imgSrc)): ?>
                      <img src="<?php echo (str_starts_with($imgSrc, 'http') || str_starts_with($imgSrc, '/')) ? $imgSrc : $base . '/' . ltrim($imgSrc, '/'); ?>"
                           alt="<?php echo htmlspecialchars($course['name']); ?>"
                           class="course-image"
                           loading="lazy">
                    <?php else: ?>
                      <div class="course-image placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                    <button class="course-action-btn" title="Download" onclick="event.stopPropagation();">
                      <i class="bi bi-download"></i>
                    </button>
                  </div>
                  <div class="course-info">
                    <h3 class="course-title"><?php echo htmlspecialchars($course['name']); ?></h3>
                    <div class="course-price"><?php echo $priceDisplay; ?></div>
                    <div class="course-meta"><?php echo $categoryName; ?></div>
                    <span class="visually-hidden">View details for <?php echo htmlspecialchars($course['name']); ?></span>
                  </div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <hr class="section-divider">

      <h2 class="section-title text-center w-100" style="text-align:center!important;margin-left:auto!important;margin-right:auto!important;">Trending This Week</h2>

      <div class="course-grid">
        <?php foreach ($trendingcourses as $course): ?>
          <?php
          $imgSrc = $base . '/assets/media/various/default_course.png';
          if (!empty($course['thumbnail'])) {
            $imgSrc = htmlspecialchars($course['thumbnail']);
          } elseif (!empty($course['images'])) {
            $images = json_decode($course['images'], true);
            if (is_array($images) && !empty($images[0]['value'])) {
              $imgSrc = htmlspecialchars($images[0]['value']);
            }
          }

          $courseUrl = $base . '/course-details.php?slug=' . rawurlencode((string)($course['slug'] ?? ''));
          $priceDisplay = ($course['price'] && $course['price'] > 0)
            ? 'TSH ' . number_format($course['price'], 0)
            : 'FREE';
          $categoryName = htmlspecialchars($course['category_name'] ?? 'Uncategorized');
          ?>

          <div class="course-card-wrapper">
            <a href="<?php echo $courseUrl; ?>" class="course-card-link">
              <div class="course-card" tabindex="0">
                <div class="course-image-container">
                  <?php if (!empty($imgSrc)): ?>
                    <img src="<?php echo (str_starts_with($imgSrc, 'http') || str_starts_with($imgSrc, '/')) ? $imgSrc : $base . '/' . ltrim($imgSrc, '/'); ?>"
                         alt="<?php echo htmlspecialchars($course['name']); ?>"
                         class="course-image"
                         loading="lazy">
                  <?php else: ?>
                    <div class="course-image placeholder" aria-hidden="true"></div>
                  <?php endif; ?>
                  <button class="course-action-btn" title="Download" onclick="event.stopPropagation();">
                    <i class="bi bi-download"></i>
                  </button>
                </div>
                <div class="course-info">
                  <h3 class="course-title"><?php echo htmlspecialchars($course['name']); ?></h3>
                  <div class="course-price"><?php echo $priceDisplay; ?></div>
                  <div class="course-meta"><?php echo $categoryName; ?></div>
                  <span class="visually-hidden">View details for <?php echo htmlspecialchars($course['name']); ?></span>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</div>

<style>
/* Centered background container */
.page-wrapper {
    max-width: 1300px;
    margin: 2rem auto;
    padding: 2rem;
    background: #f8f9fa;
    border-radius: 20px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
}

/* --- Existing CSS continued below --- */
.course-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
    margin: 1.5rem 0;
}

.course-card-wrapper {
    position: relative;
    transition: transform 0.2s;
    height: 100%;
}

.course-card-link {
    text-decoration: none;
    color: inherit;
    display: flex; /* allow inner card to grow */
    height: 100%;
}

.course-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.course-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
}

.course-image-container {
    position: relative;
    width: 100%;
    padding-top: 75%;
    background: #f5f5f5;
    overflow: hidden;
}

.course-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    padding: 10px;
    background: #fff;
    border-radius: 8px;
    transition: transform 0.3s;
}

.course-card:hover .course-image {
    transform: scale(1.03);
}

.course-action-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    color: #FF6600;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 2;
    transition: all 0.2s;
}

.course-action-btn:hover {
    background: #FF6600;
    color: white;
}

.course-info {
    padding: 1rem;
    flex: 1 1 auto; /* grow to fill leftover space */
    display: flex;
    flex-direction: column;
}

.course-title {
    font-size: 1rem;
    font-weight: 600;
    color: #000;
    margin: 0 0 0.4rem 0;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.course-price {
    font-weight: 700;
    color: #FFAA00;
    margin: 0.2rem 0 0.15rem;
    font-size: 1.1rem;
}

.course-meta {
    font-size: 0.85rem;
    color: #555;
    margin-top: auto; /* stick to bottom of info */
}

.main-col .section-title, .section-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #000;
    margin: 1.5rem auto 1rem auto !important;
    text-align: center !important;
    width: 100% !important;
    line-height: 1.2;
    display: block !important;
}

.section-divider {
    border: 0;
    height: 1px;
    background: #eee;
    margin: 2rem 0;
}

.no-courses-message {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem 0;
    color: #666;
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (max-width: 768px) {
    .course-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .course-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem; /* Tighter gap for small screens */
    }

    /* Let each card size naturally to its content on mobile */
    .course-card-wrapper { height: auto; }
    .course-card-link { display: block; height: auto; }
    .course-card { height: auto; }

    .course-info {
        padding: 0.54rem 0.58rem 0.46rem;
        display: flex;
        flex-direction: column;
        justify-content: flex-start; /* Do not distribute extra space */
        flex-grow: 0; /* Prevent stretching that causes bottom gaps */
        gap: 0.1rem;
    }
    
    .course-title {
        font-size: 0.9rem;
        -webkit-line-clamp: 3; /* Allow up to 3 lines */
        margin-bottom: 0.12rem;
    }
    
    .course-price { font-size: 1rem; margin: 0 0 0.06rem; }

    .course-image-container {
        padding-top: 78%; /* Give image a bit more height */
        background: transparent;
    }

    .course-image {
        padding: 0; /* Remove inner padding to eliminate visible mid gap */
    }

    .course-action-btn {
        width: 28px;
        height: 28px;
        font-size: 0.85rem;
    }

    .course-meta { margin-top: auto; padding-bottom: 0; line-height: 1.15; }
}

@media (max-width: 400px) {
    .course-grid {
        grid-template-columns: 1fr; /* Single column on very small screens */
        gap: 1rem;
    }
}
</style>
<!-- Sidebar content as horizontal section -->
<div class="sidebar-inline-section">
    <div class="card" style="background:#f8f9fa;">
        <div class="card-header" style="color:#007bff;background:#ffe5b4;">Categories</div>
        <div class="category-strip" role="navigation" aria-label="Browse categories">
            <?php foreach (($categoryTree[0] ?? []) as $cat): ?>
              <a class="category-chip" href="<?= $base ?>/categories-details.php?slug=<?php echo rawurlencode((string)($cat['slug'] ?? '')); ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
            <?php endforeach; ?>
            <a class="category-chip more-chip" href="<?= $base ?>/categories.php">More</a>
        </div>
    </div>
    <div class="card" style="background:#f8f9fa;">
        <div class="card-header" style="color:#007bff;background:#ffe5b4;">Recent Posts</div>
        <ul class="recent-list">
            <?php foreach ($recentPosts as $recent): ?>
                <li>
                    <a href="<?= $base ?>/course-details.php?slug=<?php echo rawurlencode((string)($recent['slug'] ?? '')); ?>" style="color:#FF6600;"><?php echo htmlspecialchars($recent['name']); ?></a><br>
                    <span style="font-size:0.85em;color:#FFD700;"><?php echo date('M d, Y', strtotime($recent['created_at'])); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card" style="background:#f8f9fa;">
        <div class="card-header" style="color:#007bff;background:#ffe5b4;">Follow Us</div>
        <div class="social-icons">
            <a href="#"><i class="bi bi-facebook" style="color:#007bff;"></i></a>
            <a href="#"><i class="bi bi-twitter" style="color:#FF6600;"></i></a>
            <a href="#"><i class="bi bi-telegram" style="color:#FFD700;"></i></a>
        </div>
        <div class="ad-block mt-3" style="color:#FF6600;">Ad Space</div>
    </div>
</div>
<!-- Audio Library Section -->
<?php if ($audioLibCatId && !empty($audioLibcourses)): ?>
    <hr class="section-divider">
    <div class="section-title" style="color:#007bff;background:#ffe5b4;padding:0.5rem 1rem;border-radius:8px;">Audio Library</div>
    <div class="course-grid">
        <?php foreach ($audioLibcourses as $course): ?>
            <?php
            if (!empty($course['thumbnail'])) {
                $imgSrc = htmlspecialchars($course['thumbnail']);
            } elseif (!empty($course['images'])) {
                $imgSrc = htmlspecialchars($course['images'][0]['value']);
            } else {
                $imgSrc = '';
            }
            $courseUrl = $base . '/course-details.php?slug=' . rawurlencode((string)($course['slug'] ?? ''));
            ?>
            <a href="<?php echo $courseUrl; ?>" class="course-card-link" style="text-decoration:none;color:inherit;">
                <div class="course-card" tabindex="0" style="cursor:pointer; min-height: 340px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; background:#f8f9fa;">
                    <button class="course-action-btn" title="Download" onclick="event.stopPropagation();" style="background:#ffe5b4;color:#007bff;"><i class="bi bi-download"></i></button>
                    <div class="course-img" style="height: 40%; min-height: 120px; max-height: 160px; width: 100%; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($imgSrc)): ?>
                          <img src="<?php echo (str_starts_with($imgSrc, 'http') || str_starts_with($imgSrc, '/')) ? $imgSrc : $base . '/' . ltrim($imgSrc, '/'); ?>" alt="<?php echo htmlspecialchars($course['name']); ?>" style="height: 100%; width: auto; max-width: 100%; object-fit: cover;">
                        <?php else: ?>
                          <div class="course-image placeholder" style="height:100%;width:100%" aria-hidden="true"></div>
                        <?php endif; ?>
                    </div>
                    <div class="course-title" style="color:#007bff;"><?php echo htmlspecialchars($course['name']); ?></div>
                    <div class="course-price" style="color:#FF6600;">
                        <?php echo ($course['price'] && $course['price'] > 0) ? 'TSH ' . number_format($course['price'], 0) : 'FREE'; ?>
                    </div>
                    <div class="course-meta" style="color:#FFD700;"><?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized'); ?></div>
                    <span class="visually-hidden">View details for <?php echo htmlspecialchars($course['name']); ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</main>
</div>
    <footer class="footer" style="background:#000;">
        <div class="container">
            <div class="copyright" style="color:#fff;">© <?php echo date('Y'); ?> <h1 style="display:inline;color:#fff;">AK23DOWNLOADS</h1> All rights reserved.</div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Close offcanvas menu when a nav link is clicked (for better UX)
        document.addEventListener('DOMContentLoaded', function() {
            const offcanvasNav = document.getElementById('offcanvasNav');
            if (offcanvasNav) {
                offcanvasNav.querySelectorAll('.nav-link').forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        const dropdown = link.nextElementSibling;
                        // Only close if not a dropdown toggle
                        if (!dropdown || !dropdown.classList.contains('dropdown-menu')) {
                            const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasNav);
                            if (offcanvas) offcanvas.hide();
                        }
                    });
                });
            }
        });
    </script>
</body>