<?php
// Reusable breadcrumb renderer
// Usage: set $breadcrumb = [ ['label'=>'Home', 'url'=>'/index.php', 'icon'=>'fas fa-home'], ['label'=>'Dashboard'] ];
// Include this file and call render_breadcrumb($breadcrumb)

if (!function_exists('render_breadcrumb')) {
  function render_breadcrumb(array $items, string $extraClass = ''): void {
    if (empty($items)) return;
    // Build markup
    echo '<nav aria-label="breadcrumb" class="mb-3 ' . htmlspecialchars($extraClass) . '">';
    echo '<ol class="breadcrumb mb-0">';
    $last = count($items) - 1;
    foreach ($items as $idx => $it) {
      $label = htmlspecialchars((string)($it['label'] ?? ''));
      $icon  = isset($it['icon']) && $it['icon'] !== '' ? '<i class="' . htmlspecialchars($it['icon']) . ' me-1"></i> ' : '';
      $url   = $it['url'] ?? null;
      if ($idx === $last || empty($url)) {
        echo '<li class="breadcrumb-item active" aria-current="page">' . $icon . $label . '</li>';
      } else {
        $href = htmlspecialchars((string)$url);
        echo '<li class="breadcrumb-item"><a class="text-decoration-none" href="' . $href . '">' . $icon . $label . '</a></li>';
      }
    }
    echo '</ol>';
    echo '</nav>';
  }
}
