    </main>
    <footer class="footer bg-dark text-white py-3 mt-auto">
      <div class="container text-center">
        <span> 2023 <strong>AK23DOWNLOADS</strong> All rights reserved.</span>
      </div>
    </footer>
</div>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Mobile Drawer Toggle (enhanced) -->
<script>
  (function(){
    const menuToggle = document.getElementById('menuToggle');
    const drawerMenu = document.getElementById('drawerMenu');
    const drawerOverlay = document.getElementById('drawerOverlay');
    const body = document.body;

    if (!menuToggle || !drawerMenu || !drawerOverlay) return;

    function openDrawer(){
      drawerMenu.classList.add('open');
      drawerOverlay.classList.add('active');
      body.classList.add('drawer-open');
      menuToggle.setAttribute('aria-expanded', 'true');
    }
    function closeDrawer(){
      drawerMenu.classList.remove('open');
      drawerOverlay.classList.remove('active');
      body.classList.remove('drawer-open');
      menuToggle.setAttribute('aria-expanded', 'false');
    }
    function toggleDrawer(){
      if (drawerMenu.classList.contains('open')) closeDrawer(); else openDrawer();
    }

    menuToggle.addEventListener('click', toggleDrawer);
    drawerOverlay.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (e)=>{
      if (e.key === 'Escape' && drawerMenu.classList.contains('open')) closeDrawer();
    });
  })();
  </script>
<?php
// WhatsApp Floating Chat Button (configurable via settings)
try {
  // Try load DB if not already available
  if (!isset($pdo)) {
    $cfg = __DIR__ . '/db_config.php';
    if (file_exists($cfg)) require_once $cfg;
  }

  // Defaults
  $waEnabled = true;
  $waPhone   = '255763312251';
  $waLabel   = 'help';
  $waTextRaw = 'Hello! I need assistance on AK23DOWNLOADS.';

  // Load from settings if DB present
  if (isset($pdo)) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) UNIQUE, setting_value TEXT)");
    $st = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('wa_enabled','wa_phone','wa_label','wa_text')");
    $st->execute();
    $kv = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $kv[$r['setting_key']] = (string)$r['setting_value']; }
    if (isset($kv['wa_enabled'])) { $waEnabled = ((int)$kv['wa_enabled'] === 1); }
    if (!empty($kv['wa_phone'])) { $waPhone = preg_replace('/\D+/', '', $kv['wa_phone']); }
    if (isset($kv['wa_label'])) { $waLabel = (string)$kv['wa_label']; }
    if (isset($kv['wa_text'])) { $waTextRaw = (string)$kv['wa_text']; }
  }

  if ($waEnabled && $waPhone !== '') {
    $waUrl = 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($waTextRaw);
    echo '<a href="' . htmlspecialchars($waUrl) . '" target="_blank" rel="noopener" aria-label="Chat on WhatsApp" style="position:fixed;right:18px;bottom:18px;z-index:9999;text-decoration:none;">'
       . '<div style="display:flex;align-items:center;gap:10px;background:#25D366;color:#fff;border-radius:999px;box-shadow:0 6px 16px rgba(0,0,0,.25);padding:10px 14px;font-weight:700;">'
       . '<svg width="22" height="22" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true" focusable="false"><path d="M19.11 17.19c-.33-.16-1.94-.96-2.24-1.07-.3-.11-.52-.16-.74.16-.22.33-.85 1.06-1.04 1.28-.19.22-.38.25-.71.09-.33-.16-1.38-.51-2.64-1.62-.97-.86-1.62-1.92-1.81-2.24-.19-.33-.02-.5.14-.66.14-.14.33-.38.49-.57.16-.19.22-.33.33-.55.11-.22.05-.41-.02-.57-.08-.16-.74-1.79-1.02-2.45-.27-.66-.55-.56-.74-.57-.19-.01-.41-.01-.63-.01-.22 0-.57.08-.87.41-.3.33-1.14 1.12-1.14 2.74 0 1.62 1.17 3.19 1.33 3.41.16.22 2.3 3.51 5.58 4.92.78.34 1.39.54 1.87.69.79.25 1.5.21 2.06.13.63-.09 1.94-.79 2.21-1.55.27-.76.27-1.41.19-1.55-.08-.14-.3-.22-.63-.38z"/><path d="M26.02 5.98C23.22 3.18 19.7 1.69 16 1.69 8.33 1.69 2.11 7.91 2.11 15.58c0 2.48.65 4.89 1.89 7.01L2 31l8.57-2.25c2.06 1.12 4.39 1.71 6.77 1.71 7.67 0 13.89-6.22 13.89-13.89 0-3.7-1.49-7.22-4.29-10.02zM16 28.33c-2.16 0-4.27-.58-6.11-1.68l-.44-.26-5.08 1.33 1.36-4.95-.29-.47c-1.18-1.96-1.8-4.21-1.8-6.72 0-6.97 5.67-12.64 12.64-12.64 3.38 0 6.56 1.32 8.95 3.71 2.39 2.39 3.71 5.57 3.71 8.95 0 6.97-5.67 12.64-12.64 12.64z"/></svg>'
       . '<span style="line-height:1;display:flex;flex-direction:column;">'
       . '<span style="font-size:12px;opacity:.9;">WhatsApp</span>'
       . '<span style="font-size:14px;color:#fff;">' . htmlspecialchars($waLabel) . '</span>'
       . '</span>'
       . '</div>'
       . '</a>';
  }
} catch (Throwable $e) {
  // Silent fail
}
?>
</body>
</html>