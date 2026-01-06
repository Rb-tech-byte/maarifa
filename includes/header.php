<?php
  require_once __DIR__ . '/base.php';
  // Determine site logo from settings (admin/system_logo.php writes to settings.system_logo)
  $defaultLogo = $base . '/assets/images/ak.png';
  $logoUrl = $defaultLogo;           // navbar brand logo (prefers light variant on dark header)
  $faviconUrl = $defaultLogo;        // favicon/apple-touch (prefers small/square if provided)
  // Site name defaults and dynamic values
  $siteName = 'AKDOWNLOADS';
  $siteNameShort = 'AKDOWNLOADS';
  try {
    if (!isset($pdo)) {
      $dbConf = __DIR__ . '/db_config.php';
      if (file_exists($dbConf)) require_once $dbConf;
    }
    if (isset($pdo)) {
      // Helper: resolve a settings key to a usable URL
      // Accepts full URLs (http/https), absolute paths (/...), or relative paths (uploads/..., assets/...).
      // Falls back to treating the value as a filename in uploads/.
      $resolveLogo = function(string $key) use ($pdo, $base) : ?string {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $val = isset($row['setting_value']) ? trim((string)$row['setting_value']) : '';
        if ($val === '') return null;

        // 1) Full URL
        if (preg_match('/^https?:\/\//i', $val)) {
          return $val;
        }

        // 2) Absolute path from web root
        if (strpos($val, '/') === 0) {
          return $val; // assume web-root absolute URL path
        }

        // 3) Relative path within project (e.g., uploads/logo.png or assets/images/logo.png)
        $rel = ltrim($val, '/');
        $fsPath = __DIR__ . '/../' . $rel;
        if (is_file($fsPath)) {
          $mtime = @filemtime($fsPath) ?: time();
          return $base . '/' . str_replace('%2F', '/', rawurlencode($rel)) . '?v=' . $mtime;
        }

        // 4) Fallback: treat as filename located under uploads/
        $fname = basename($val);
        $fsPath2 = __DIR__ . '/../uploads/' . $fname;
        if (is_file($fsPath2)) {
          $mtime = @filemtime($fsPath2) ?: time();
          return $base . '/uploads/' . rawurlencode($fname) . '?v=' . $mtime;
        }
        return null;
      };

      // Prefer light logo on dark header; fallback order
      $logoUrl = $resolveLogo('system_logo_light')
              ?: $resolveLogo('system_logo_large')
              ?: $resolveLogo('system_logo')
              ?: $defaultLogo;

      // Favicon prefers small/square if available; fallback order
      $faviconUrl = $resolveLogo('system_logo_small')
                 ?: $resolveLogo('system_logo')
                 ?: $logoUrl
                 ?: $defaultLogo;

      // Resolve system name values
      $getSetting = function(string $key) use ($pdo) : ?string {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $val = isset($row['setting_value']) ? trim((string)$row['setting_value']) : '';
        return $val !== '' ? $val : null;
      };
      if ($n = $getSetting('system_name')) { $siteName = $n; }
      if ($ns = $getSetting('system_name_short')) { $siteNameShort = $ns; }
    }
  } catch (Throwable $e) {
    // Ignore and keep default logo
  }
  // Force static logo usage to avoid DB-related issues
  $logoUrl = $defaultLogo;
  $faviconUrl = $defaultLogo;
  $siteName = 'AKDOWNLOADS';
  $siteNameShort = 'AKDOWNLOADS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title : htmlspecialchars($siteName) ?></title>
    
    <!-- Allow common media iframe sources globally (minimal CSP just for frame-src) -->
    <meta http-equiv="Content-Security-Policy" content="frame-src https://www.youtube.com https://youtube.com https://www.youtube-nocookie.com https://*.youtube-nocookie.com https://vimeo.com https://player.vimeo.com https://soundcloud.com https://w.soundcloud.com https://drive.google.com https://pay.pesapal.com; connect-src 'self' https: https://pay.pesapal.com https://www.pesapal.com https://centinelapi.cardinalcommerce.com https://writer.cardinalcommerce.com;">

    <!-- Site icons (dynamic with cache-busting; prefers small square if set) -->
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>" sizes="32x32" type="image/png">
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    <meta name="msapplication-TileImage" content="<?= htmlspecialchars($faviconUrl) ?>">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
    <?php
      // Optional dynamic SEO meta (set by pages before including header.php)
      // Expected variables (strings): $metaTitle, $metaDescription, $metaImage, $metaUrl, $metaCategory
      $metaTitleOut = isset($metaTitle) && $metaTitle !== '' ? (string)$metaTitle : (isset($title) ? (string)$title : $siteName);
      $metaDescOut = isset($metaDescription) && $metaDescription !== '' ? (string)$metaDescription : '';
      $metaImageOut = isset($metaImage) && $metaImage !== '' ? (string)$metaImage : '';
      $metaUrlOut = isset($metaUrl) && $metaUrl !== '' ? (string)$metaUrl : '';
      $metaCatOut = isset($metaCategory) && $metaCategory !== '' ? (string)$metaCategory : '';
      if ($metaUrlOut === '' && isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $metaUrlOut = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
      }
    ?>
    <?php if (!empty($metaUrlOut)): ?>
      <link rel="canonical" href="<?= htmlspecialchars($metaUrlOut) ?>" />
    <?php endif; ?>
    <?php if (!empty($metaTitleOut)): ?>
      <meta property="og:title" content="<?= htmlspecialchars($metaTitleOut) ?>" />
      <meta name="twitter:title" content="<?= htmlspecialchars($metaTitleOut) ?>" />
    <?php endif; ?>
    <?php if (!empty($metaDescOut)): ?>
      <meta name="description" content="<?= htmlspecialchars($metaDescOut) ?>" />
      <meta property="og:description" content="<?= htmlspecialchars($metaDescOut) ?>" />
      <meta name="twitter:description" content="<?= htmlspecialchars($metaDescOut) ?>" />
    <?php endif; ?>
    <?php if (!empty($metaImageOut)): ?>
      <meta property="og:image" content="<?= htmlspecialchars($metaImageOut) ?>" />
      <meta name="twitter:image" content="<?= htmlspecialchars($metaImageOut) ?>" />
    <?php endif; ?>
    <?php if (!empty($metaUrlOut)): ?>
      <meta property="og:url" content="<?= htmlspecialchars($metaUrlOut) ?>" />
    <?php endif; ?>
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <?php if (!empty($metaCatOut)): ?>
      <meta name="article:section" content="<?= htmlspecialchars($metaCatOut) ?>" />
    <?php endif; ?>
    <?php
      // Build a conservative keywords list with hard anchor 'akdownloads.com'
      $kw = ['akdownloads.com'];
      // Add site and category names when available
      if (!empty($siteName)) { $kw[] = $siteName; }
      if (!empty($metaCatOut)) { $kw[] = $metaCatOut; }
      // Add common domain terms (kept short to avoid keyword stuffing)
      $kw = array_values(array_unique(array_filter(array_map('trim', $kw))));
      $keywordsOut = implode(', ', $kw);
    ?>
    <meta name="keywords" content="<?= htmlspecialchars($keywordsOut) ?>" />
    <script>
      // Normalize media playback attributes for better cross-browser behavior
      document.addEventListener('DOMContentLoaded', function(){
        try {
          function isExternalAbsolute(u){
            try { var a = document.createElement('a'); a.href = u; return /^https?:/i.test(a.protocol) && a.hostname && a.hostname !== location.hostname; } catch(e){ return false; }
          }
          function toProxy(u){
            return '<?= $base ?>/media_proxy.php?url=' + encodeURIComponent(u);
          }
          document.querySelectorAll('audio, video').forEach(function(el){
            var src = el.getAttribute('src');
            if (src && isExternalAbsolute(src) && !/\/media_proxy\.php\?/.test(src)) {
              el.setAttribute('src', toProxy(src));
            } else {
              // If using <source> children, rewrite them
              el.querySelectorAll('source[src]').forEach(function(s){
                var ssrc = s.getAttribute('src');
                if (ssrc && isExternalAbsolute(ssrc) && !/\/media_proxy\.php\?/.test(ssrc)) {
                  s.setAttribute('src', toProxy(ssrc));
                }
              });
            }
            if (!el.hasAttribute('controls')) el.setAttribute('controls','');
            if (!el.hasAttribute('preload')) el.setAttribute('preload','metadata');
            el.setAttribute('playsinline','');
            if (!el.hasAttribute('crossorigin')) el.setAttribute('crossorigin','anonymous');
            if (el.hasAttribute('autoplay')) {
              el.muted = true; // required by mobile Safari/Chrome for autoplay
              el.setAttribute('muted','');
              var tryPlay = function(){ el.play().catch(function(){}); };
              el.addEventListener('canplay', tryPlay, { once: true });
            }
          });
          document.querySelectorAll('iframe').forEach(function(ifr){
            var allow = ifr.getAttribute('allow') || '';
            ['autoplay','encrypted-media','picture-in-picture'].forEach(function(tok){
              if (allow.indexOf(tok) === -1) { allow = (allow ? allow + '; ' : '') + tok; }
            });
            ifr.setAttribute('allow', allow);
            if (!ifr.hasAttribute('loading')) ifr.setAttribute('loading','lazy');
            if (!ifr.hasAttribute('referrerpolicy')) ifr.setAttribute('referrerpolicy','strict-origin-when-cross-origin');
          });
        } catch(e) {}
      });
    </script>

    <style>
      /* Category pill strip */
      .cat-strip {
        display:flex;
        align-items:center;
        gap: .5rem;
        /* Desktop: allow wrapping (previous design) */
        flex-wrap: wrap;
        justify-content: space-between;
        padding-bottom: 4px;
      }
      .cat-strip a { text-decoration: none !important; }
      .cat-strip .cat-link { font-size: 1.15rem; padding: 8px 14px; color:#000 !important; background:transparent; border-radius:0; display:inline-flex; align-items:center; text-decoration:none !important; }
      .cat-strip .cat-link.active { font-weight:800; color:#111 !important; text-decoration: none !important; }
      .cat-item { position: relative; }
      .cat-item > a.cat-link { cursor: pointer; }
      .cat-dd {
        display: none;
        position: absolute;
        left: 0;
        top: calc(100% + 6px);
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        min-width: 220px;
        padding: 8px;
        z-index: 1080;
      }
      .cat-dd a { display:block; padding:8px 14px; color:#222; border-radius:6px; font-size: 1.1rem; }
      .cat-dd a:hover { background:#f7f7f7; }
      .cat-item:hover .cat-dd { display:block; }
      .cat-item.open .cat-dd { display:block; }
      @media (max-width: 991px){
        .cat-item:hover .cat-dd { display:none; }
        /* Mobile: single-row horizontal scroll */
        .cat-strip {
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
          flex-wrap: nowrap;
          justify-content: flex-start;
          scroll-snap-type: x proximity;
          gap: .5rem;
        }
        .cat-item { flex: 0 0 auto; scroll-snap-align: start; }
        .cat-strip::-webkit-scrollbar { height: 6px; }
        .cat-strip::-webkit-scrollbar-track { background: #f1f1f1; }
        .cat-strip::-webkit-scrollbar-thumb { background: #c9c9c9; border-radius: 3px; }
        .cat-strip .cat-link { padding: 10px 14px; }
      }

      /* Brand logo: responsive (height-based to preserve aspect ratio) */
      .brand-logo {
        height: clamp(22px, 3.8vh, 30px);
        width: auto;
        max-width: 160px; /* safety cap */
        border-radius: 8px;
        object-fit: contain;
        display: block;
      }

      /* Left Drawer Menu */
      .drawer {
        position: fixed;
        top: 0;
        left: -70%;
        width: 70%;
        height: 100%;
        background: #1e1e1e;
        display: flex;
        flex-direction: column;
        padding-top: 4rem;
        transition: left 0.3s ease;
        z-index: 1050;
        box-shadow: 2px 0 8px rgba(0,0,0,0.4);
      }
      .drawer.open { left: 0; }
      .drawer a {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: 1rem 1.5rem;
        color: yellow;
        text-decoration: none;
        font-size: 1.1rem;
        border-bottom: 1px solid #333;
        transition: background 0.2s, transform 0.2s;
      }
      .drawer .drawer-cat-btn {
        display:flex; align-items:center; justify-content:space-between; width:100%;
        background:transparent; color:yellow; border:0; text-align:left; padding:1rem 1.5rem; font-size:1.1rem;
        border-bottom:1px solid #333; cursor:pointer;
      }
      .drawer .drawer-sub { display:none; flex-direction:column; }
      .drawer .drawer-sub a { color:#eee; padding:.6rem 2.25rem; font-size:1rem; border-bottom:0; }
      .drawer .drawer-sub a:hover { background:#2a2a2a; }
      .drawer a:hover {
        background: #2a2a2a;
        transform: translateX(5px);
      }
      .drawer a i { width: 20px; text-align: center; }

      /* Overlay */
      .overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
        z-index: 1040;
      }
      .overlay.active {
        opacity: 1;
        pointer-events: all;
      }

      /* Desktop: use normal nav */
      @media (min-width: 992px) {
        .drawer, .overlay { display: none; }
      }
      /* Compact fixed header */
      :root { --nav-h: 40px; }
      .fixed-topnav { position: fixed; top: 0; left: 0; right: 0; width: 100%; z-index: 1060; background: #212529 !important; }
      .fixed-topnav .container-fluid { min-height: var(--nav-h); padding-top: 4px; padding-bottom: 4px; }
      .navbar-brand { padding: 0; }
      .navbar .nav-link { padding: .2rem .5rem; font-size: .95rem; }
      .with-fixed-offset { padding-top: var(--nav-h); }
      body { padding-top: var(--nav-h); }
      /* Slightly taller on large screens, but still compact */
      @media (min-width: 992px) {
        :root { --nav-h: 44px; }
        .navbar .nav-link { font-size: 1rem; }
      }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">
<div class="d-flex min-vh-100 flex-column">

  <header>
    <!-- Top Navbar -->
    <nav class="navbar navbar-dark bg-dark shadow-sm fixed-topnav">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base ?>/index.php">
          <img class="brand-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?> Logo">
          <span class="fw-bold d-none d-sm-inline"><?= htmlspecialchars($siteNameShort) ?></span>
        </a>

        <!-- Hamburger -->
        <button class="btn btn-dark d-lg-none" id="menuToggle" aria-label="Open Menu">
          <i class="fas fa-bars fa-lg"></i>
        </button>

        <!-- Desktop Nav -->
        <ul class="navbar-nav ms-auto mb-0 d-none d-lg-flex flex-row gap-3 align-items-center">
          <li class="nav-item"><a class="nav-link text-warning" href="<?= $base ?>/request_course.php"><i class="fas fa-clipboard-list"></i> Request a course</a></li>
          <li class="nav-item"><a class="nav-link text-warning" href="<?= $base ?>/contact_us.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
          <?php if (isset($_SESSION['user_logged_in'])): ?>
            <li class="nav-item"><a class="nav-link text-warning" href="<?= $base ?>/user_dashboard.php"><i class="fas fa-user"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-warning" href="<?= $base ?>/user_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link text-warning" href="<?= $base ?>/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <li class="nav-item"><a class="nav-link text-warning" href="<?= $base ?>/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </nav>

    <!-- Drawer Menu for Mobile -->
    <nav class="drawer" id="drawerMenu">
      <a href="<?= $base ?>/request_course.php"><i class="fas fa-clipboard-list"></i> Request a course</a>
      <a href="<?= $base ?>/contact_us.php"><i class="fas fa-envelope"></i> Contact Us</a>
      <?php
        // Prepare parents/children for mobile drawer
        $drawerParents = $headerTopCats ?? [];
        $drawerChildrenMap = $childrenByParent ?? [];
      ?>
      <?php if (!empty($drawerParents)): ?>
        <button class="drawer-cat-btn" type="button" data-role="toggle" aria-expanded="false">
          <span><i class="fas fa-tags"></i> Categories</span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="drawer-sub" data-role="submenu">
          <?php foreach ($drawerParents as $p): $kids = $drawerChildrenMap[(int)$p['id']] ?? []; ?>
            <?php if (!empty($kids)): ?>
              <button class="drawer-cat-btn" type="button" data-role="toggle-parent" aria-expanded="false" style="padding:.8rem 2rem;">
                <span><i class="fas fa-folder"></i> <?= htmlspecialchars($p['name']) ?></span>
                <i class="fas fa-chevron-down"></i>
              </button>
              <div class="drawer-sub" data-role="submenu-parent">
                <a href="<?= $base ?>/categories-details.php?slug=<?= rawurlencode($p['slug']) ?>"><i class="fas fa-list"></i> View all <?= htmlspecialchars($p['name']) ?></a>
                <?php foreach ($kids as $ch): ?>
                  <a href="<?= $base ?>/categories-details.php?slug=<?= rawurlencode($ch['slug']) ?>"><i class="fas fa-tag"></i> <?= htmlspecialchars($ch['name']) ?></a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <a href="<?= $base ?>/categories-details.php?slug=<?= rawurlencode($p['slug']) ?>"><i class="fas fa-folder"></i> <?= htmlspecialchars($p['name']) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (isset($_SESSION['user_logged_in'])): ?>
        <a href="<?= $base ?>/user_dashboard.php"><i class="fas fa-user"></i> Dashboard</a>
        <a href="<?= $base ?>/user_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      <?php else: ?>
        <a href="<?= $base ?>/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
        <a href="<?= $base ?>/register.php"><i class="fas fa-user-plus"></i> Register</a>
      <?php endif; ?>
    </nav>
    <div class="overlay" id="drawerOverlay"></div>

    <!-- Category Strip -->
    <?php
      // Use cached category tree to avoid repeated queries and counts
      if (!function_exists('getCategoryTreeCached')) {
        require_once __DIR__ . '/functions.php';
      }
      $tree = getCategoryTreeCached();
      $childrenByParent = $tree;
      $headerTopCats = $tree[0] ?? [];
    ?>
    <?php if (!empty($headerTopCats)): ?>
      <div>
        <div class="container bg-white border-bottom py-2 d-flex cat-strip">
          <?php foreach ($headerTopCats as $hc): ?>
            <?php $isActiveCat = isset($currentCategorySlug) && $hc['slug'] === $currentCategorySlug; $kids = $childrenByParent[(int)$hc['id']] ?? []; ?>
            <div class="cat-item" data-parent-id="<?= (int)$hc['id'] ?>">
              <a href="<?= $base ?>/categories-details.php?slug=<?= rawurlencode($hc['slug']) ?>"
                 class="cat-link<?= $isActiveCat ? ' active' : '' ?>"
                 data-has-children="<?= !empty($kids) ? '1':'0' ?>">
                <?= htmlspecialchars($hc['name']) ?>
                <?php if (!empty($kids)): ?><i class="bi bi-caret-down-fill" style="font-size:.8em; margin-left:6px;"></i><?php endif; ?>
              </a>
              <?php if (!empty($kids)): ?>
                <div class="cat-dd">
                  <a class="dropdown-item" href="<?= $base ?>/categories-details.php?slug=<?= rawurlencode($hc['slug']) ?>" style="font-weight:600; color:#000;">View all <?= htmlspecialchars($hc['name']) ?></a>
                  <hr class="dropdown-divider" />
                  <?php foreach ($kids as $ch): ?>
                    <a href="<?= $base ?>/categories-details.php?slug=<?= rawurlencode($ch['slug']) ?>">
                      <i class="fas fa-tag" style="width:14px; margin-right:6px; color:#888;"></i>
                      <?= htmlspecialchars($ch['name']) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </header>

  <main class="flex-grow-1 p-3">
  <script src="<?= $base ?>/assets/js/header-cats.js" defer></script>
