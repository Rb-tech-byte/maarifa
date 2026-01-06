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

// ============================
// Files Manager V2 (default)
// ============================
// A simpler, robust implementation that uses:
// - Tables: file_folders(id,name,description,parent_id,created_at,updated_at)
// - medias(id, courses_id, file_type, value, type, size, folder_id, created_at, deleted_at)
// Actions are handled in-page via POST with explicit 'v2_action' keys.
// Access legacy page via ?legacy=1
if (!isset($_GET['legacy'])) {
    $message = ['type' => '', 'text' => ''];

    // Helpers
    $uploadsDir = realpath(__DIR__ . '/../uploads/files');
    if ($uploadsDir === false) {
        $uploadsDir = __DIR__ . '/../uploads/files';
    }
    if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0755, true); }

    $currentFolderId = isset($_GET['folder']) ? max(0, (int)$_GET['folder']) : 0; // 0 means root (NULL)
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 24;

    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['v2_action'])) {
        header('Content-Type: application/json');
        $out = ['ok' => false, 'msg' => 'Unknown'];
        try {
            // Small helper for thumbnails (GD)
            $makeThumb = function(string $srcFs, string $dstFs, int $max=320){
                try {
                    if (!extension_loaded('gd')) return; // skip silently if GD not available
                    $info = @getimagesize($srcFs);
                    if (!$info) return;
                    [$w,$h,$type] = $info;
                    if ($w<=0 || $h<=0) return;
                    $scale = min($max/$w, $max/$h, 1);
                    $tw = (int)max(1, round($w*$scale));
                    $th = (int)max(1, round($h*$scale));
                    switch ($type) {
                        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($srcFs); break;
                        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($srcFs); break;
                        case IMAGETYPE_GIF:  $src = @imagecreatefromgif($srcFs); break;
                        case IMAGETYPE_WEBP: if (function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($srcFs); } else { $src = null; } break;
                        default: $src = null; break;
                    }
                    if (!$src) return;
                    $dst = imagecreatetruecolor($tw, $th);
                    imagealphablending($dst, false); imagesavealpha($dst, true);
                    imagecopyresampled($dst, $src, 0,0,0,0, $tw,$th, $w,$h);
                    @imagepng($dst, $dstFs, 6);
                    imagedestroy($dst); imagedestroy($src);
                } catch (Throwable $e) { /* ignore */ }
            };
            switch ($_POST['v2_action']) {
                case 'create_folder': {
                    $name = trim((string)($_POST['name'] ?? ''));
                    $parent = (int)($_POST['parent'] ?? 0);
                    $pid = $parent > 0 ? $parent : null;
                    if ($name === '' || preg_match('/[\\\/\?%*:|"<>]/', $name)) {
                        throw new RuntimeException('Invalid folder name.');
                    }
                    $check = $pdo->prepare('SELECT id FROM file_folders WHERE name=? AND ((parent_id IS NULL AND ? IS NULL) OR parent_id=?) LIMIT 1');
                    $check->execute([$name, $pid, $pid]);
                    if ($check->fetch()) { throw new RuntimeException('Folder exists.'); }
                    $ins = $pdo->prepare('INSERT INTO file_folders(name,description,parent_id) VALUES (?,?,?)');
                    $ins->execute([$name, '', $pid]);
                    $out = ['ok' => true, 'msg' => 'Folder created'];
                    break;
                }
                case 'rename_folder': {
                    $id = (int)($_POST['id'] ?? 0);
                    $name = trim((string)($_POST['name'] ?? ''));
                    if ($id <= 0 || $name === '' || preg_match('/[\\\/\?%*:|"<>]/', $name)) { throw new RuntimeException('Invalid input'); }
                    $up = $pdo->prepare('UPDATE file_folders SET name=? WHERE id=?');
                    $up->execute([$name, $id]);
                    $out = ['ok' => true, 'msg' => 'Folder renamed'];
                    break;
                }
                case 'delete_folder': {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('Invalid folder');
                    $cnt = $pdo->prepare('SELECT COUNT(*) FROM medias WHERE (folder_id = ?) AND deleted_at IS NULL');
                    $cnt->execute([$id]);
                    if ((int)$cnt->fetchColumn() > 0) { throw new RuntimeException('Folder not empty'); }
                    $del = $pdo->prepare('DELETE FROM file_folders WHERE id=?');
                    $del->execute([$id]);
                    $out = ['ok' => true, 'msg' => 'Folder deleted'];
                    break;
                }
                case 'upload_files': {
                    $folder = (int)($_POST['folder'] ?? 0);
                    $folderId = $folder > 0 ? $folder : null;
                    if (empty($_FILES['files'])) throw new RuntimeException('No files');
                    $ok = 0; $err = 0;
                    $allowed = ['jpg','jpeg','png','gif','webp','pdf','zip','rar','wav','mp3','ogg','m4a'];
                    $files = $_FILES['files'];
                    $N = is_array($files['name']) ? count($files['name']) : 1;
                    for ($i=0; $i<$N; $i++) {
                        $errCode = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                        if ($errCode !== UPLOAD_ERR_OK) { $err++; continue; }
                        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                        $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed, true)) { $err++; continue; }
                        $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name);
                        $target = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $safe;
                        if (!move_uploaded_file($tmp, $target)) { $err++; continue; }
                        $relPath = '../uploads/files/' . $safe; // store relative to admin
                        $fileType = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : (in_array($ext, ['wav','mp3','ogg','m4a']) ? 'audio' : ($ext === 'pdf' ? 'pdf' : ($ext === 'zip' || $ext === 'rar' ? 'zip' : $ext)));
                        $ins = $pdo->prepare('INSERT INTO medias(value,file_type,type,folder_id,created_at) VALUES (?,?,?,?,NOW())');
                        $ins->execute([$relPath, $fileType, 'upload', $folderId]);
                        // Create thumbnail for images
                        if ($fileType === 'image') {
                            $thumbDir = realpath(__DIR__ . '/../uploads/thumbnails');
                            if ($thumbDir === false) { $thumbDir = __DIR__ . '/../uploads/thumbnails'; }
                            if (!is_dir($thumbDir)) { @mkdir($thumbDir, 0755, true); }
                            $thumbFs = rtrim($thumbDir,'/\\') . DIRECTORY_SEPARATOR . pathinfo($safe, PATHINFO_FILENAME) . '.png';
                            $makeThumb($target, $thumbFs, 320);
                        }
                        $ok++;
                    }
                    $out = ['ok' => true, 'msg' => "$ok uploaded, $err failed"];
                    break;
                }
                case 'rename_file': {
                    $id = (int)($_POST['id'] ?? 0);
                    $new = trim((string)($_POST['name'] ?? ''));
                    if ($id <= 0 || $new === '' || preg_match('/[\\\/\?%*:|"<>]/', $new)) throw new RuntimeException('Invalid');
                    $row = $pdo->prepare('SELECT value FROM medias WHERE id=?');
                    $row->execute([$id]);
                    $cur = $row->fetch(PDO::FETCH_ASSOC);
                    if (!$cur) throw new RuntimeException('Not found');
                    $oldPath = $cur['value'];
                    $ext = pathinfo($oldPath, PATHINFO_EXTENSION);
                    $dir = dirname($oldPath);
                    $newPath = $dir . '/' . $new . ($ext ? '.' . $ext : '');
                    $fsOld = realpath(__DIR__ . '/' . $oldPath) ?: (__DIR__ . '/' . $oldPath);
                    $fsNew = __DIR__ . '/' . $newPath;
                    if (@file_exists($fsNew)) throw new RuntimeException('Target exists');
                    if (@file_exists($fsOld) && @rename($fsOld, $fsNew)) {
                        $up = $pdo->prepare('UPDATE medias SET value=? WHERE id=?');
                        $up->execute([$newPath, $id]);
                        $out = ['ok' => true, 'msg' => 'Renamed'];
                    } else {
                        throw new RuntimeException('Rename failed');
                    }
                    break;
                }
                case 'delete_file': {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('Invalid');
                    $up = $pdo->prepare('UPDATE medias SET deleted_at=NOW() WHERE id=?');
                    $up->execute([$id]);
                    $out = ['ok' => true, 'msg' => 'Deleted'];
                    break;
                }
                case 'move_files': {
                    $ids = json_decode((string)($_POST['ids'] ?? '[]'), true) ?: [];
                    $folder = (int)($_POST['folder'] ?? 0);
                    $folderId = $folder > 0 ? $folder : null;
                    $moved = 0;
                    $up = $pdo->prepare('UPDATE medias SET folder_id=? WHERE id=?');
                    foreach ($ids as $id) { if ($id) { $up->execute([$folderId, (int)$id]); $moved++; } }
                    $out = ['ok' => true, 'msg' => "$moved moved"];
                    break;
                }
                case 'bulk_delete': {
                    $ids = json_decode((string)($_POST['ids'] ?? '[]'), true) ?: [];
                    if (!$ids) throw new RuntimeException('No selection');
                    $del = $pdo->prepare('UPDATE medias SET deleted_at=NOW() WHERE id=?');
                    $n=0; foreach ($ids as $id) { if ($id) { $del->execute([(int)$id]); $n++; } }
                    $out = ['ok' => true, 'msg' => "$n deleted"];
                    break;
                }
                case 'attach_to_course': {
                    $ids = json_decode((string)($_POST['ids'] ?? '[]'), true) ?: [];
                    $courseId = (int)($_POST['course_id'] ?? 0);
                    if ($courseId <= 0) throw new RuntimeException('Invalid course');
                    $chk = $pdo->prepare('SELECT id,name FROM courses WHERE id=? LIMIT 1');
                    $chk->execute([$courseId]);
                    $p = $chk->fetch(PDO::FETCH_ASSOC);
                    if (!$p) throw new RuntimeException('course not found');
                    $up = $pdo->prepare('UPDATE medias SET courses_id=? WHERE id=?');
                    $n=0; foreach ($ids as $id) { if ($id) { $up->execute([$courseId, (int)$id]); $n++; } }
                    $out = ['ok' => true, 'msg' => "$n attached to course #$courseId ({$p['name']})"];
                    break;
                }
            }
        } catch (Throwable $e) {
            $out = ['ok' => false, 'msg' => $e->getMessage()];
        }
        echo json_encode($out);
        exit;
    }

    // Fetch folders and files for view
    $folders = $pdo->query('SELECT id,name,parent_id FROM file_folders ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $folderMap = [];
    foreach ($folders as $f) { $folderMap[(int)$f['id']] = $f; }
    $currentParent = $currentFolderId > 0 && isset($folderMap[$currentFolderId]) ? (int)($folderMap[$currentFolderId]['parent_id'] ?? 0) : 0;

    // Files query
    $countQ = $pdo->prepare('SELECT COUNT(*) FROM medias WHERE deleted_at IS NULL AND ((folder_id IS NULL AND ?=0) OR folder_id=?)');
    $countQ->execute([$currentFolderId, $currentFolderId]);
    $total = (int)$countQ->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;
    $listQ = $pdo->prepare('SELECT id,value,file_type,created_at FROM medias WHERE deleted_at IS NULL AND ((folder_id IS NULL AND ?=0) OR folder_id=?) ORDER BY id DESC LIMIT ? OFFSET ?');
    $listQ->bindValue(1, $currentFolderId, PDO::PARAM_INT);
    $listQ->bindValue(2, $currentFolderId, PDO::PARAM_INT);
    $listQ->bindValue(3, $perPage, PDO::PARAM_INT);
    $listQ->bindValue(4, $offset, PDO::PARAM_INT);
    $listQ->execute();
    $files = $listQ->fetchAll(PDO::FETCH_ASSOC);

    $title = 'Files Manager - AKDOWNLOADS Admin';
    include '../includes/admin_header.php';
    ?>
    <div class="container-fluid p-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
          <form id="uploadForm" class="d-flex align-items-center gap-2" enctype="multipart/form-data">
            <input type="hidden" name="v2_action" value="upload_files">
            <input type="hidden" name="folder" value="<?= (int)$currentFolderId ?>">
            <input class="form-control form-control-sm" type="file" name="files[]" multiple>
            <button class="btn btn-warning btn-sm" type="submit"><i class="fas fa-upload me-1"></i> Upload</button>
          </form>
          <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#newFolderModal"><i class="fas fa-folder-plus me-1"></i> New Folder</button>
        </div>
        <div>
          <select class="form-select form-select-sm" id="folderJump" style="min-width:240px">
            <option value="0" <?= $currentFolderId===0?'selected':'' ?>>/ (Root)</option>
            <?php foreach ($folders as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id']===$currentFolderId)?'selected':'' ?>><?= htmlspecialchars($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="card bg-dark text-white border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <strong>Folder:</strong> <?= $currentFolderId===0?'/ (Root)':htmlspecialchars($folderMap[$currentFolderId]['name'] ?? '/') ?>
          </div>
          <div class="d-flex gap-2">
            <?php if ($currentFolderId>0): ?>
              <button class="btn btn-outline-light btn-sm" id="renameFolderBtn"><i class="fas fa-i-cursor me-1"></i> Rename</button>
              <button class="btn btn-outline-danger btn-sm" id="deleteFolderBtn"><i class="fas fa-trash me-1"></i> Delete</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <?php if (empty($files)): ?>
            <div class="text-center text-muted py-4">No files in this folder.</div>
          <?php else: ?>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
              <div class="btn-group btn-group-sm" role="group" aria-label="Bulk actions">
                <button class="btn btn-outline-light" id="selectAllBtn">Select all</button>
                <button class="btn btn-outline-light" id="clearSelBtn">Clear</button>
              </div>
              <div class="vr mx-1"></div>
              <button class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn"><i class="fas fa-trash me-1"></i> Delete Selected</button>
              <div class="d-flex align-items-center gap-1">
                <select class="form-select form-select-sm" id="bulkMoveDest" style="min-width:200px">
                  <option value="0">/ (Root)</option>
                  <?php foreach ($folders as $f): ?>
                    <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-light btn-sm" id="bulkMoveBtn"><i class="fas fa-folder-open me-1"></i> Move Selected</button>
              </div>
              <button class="btn btn-outline-warning btn-sm" id="attachBtn"><i class="fas fa-link me-1"></i> Attach to course</button>
            </div>

            <div class="row g-3">
              <?php foreach ($files as $fi): $p=$fi['value']; $ext=strtolower(pathinfo($p, PATHINFO_EXTENSION)); $thumb=str_replace('../uploads/files/','../uploads/thumbnails/', $p); $thumbP = __DIR__ . '/' . $thumb; $useThumb = file_exists($thumbP); ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                  <div class="border rounded p-2 h-100 d-flex flex-column bg-secondary-subtle">
                    <div class="form-check mb-1">
                      <input class="form-check-input pick" type="checkbox" value="<?= (int)$fi['id'] ?>">
                    </div>
                    <div class="ratio ratio-1x1 mb-2" style="background:#111; border-radius:6px; overflow:hidden;">
                      <?php if (in_array($ext,['jpg','jpeg','png','gif','webp'])): ?>
                        <img src="<?= htmlspecialchars($useThumb ? $thumb : $p) ?>" alt="" style="object-fit:cover; width:100%; height:100%;">
                      <?php elseif (in_array($ext,['mp3','wav','ogg','m4a'])): ?>
                        <div class="d-flex align-items-center justify-content-center h-100 text-warning"><i class="fas fa-music fa-2x"></i></div>
                      <?php elseif (in_array($ext,['zip','rar'])): ?>
                        <div class="d-flex align-items-center justify-content-center h-100 text-info"><i class="fas fa-file-archive fa-2x"></i></div>
                      <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center h-100 text-light"><i class="fas fa-file fa-2x"></i></div>
                      <?php endif; ?>
                    </div>
                    <div class="small text-truncate" title="<?= htmlspecialchars(basename($p)) ?>"><?= htmlspecialchars(basename($p)) ?></div>
                    <div class="d-flex gap-1 mt-2">
                      <button class="btn btn-sm btn-outline-light flex-fill rename-file" data-id="<?= (int)$fi['id'] ?>" data-name="<?= htmlspecialchars(pathinfo($p, PATHINFO_FILENAME)) ?>"><i class="fas fa-i-cursor"></i></button>
                      <button class="btn btn-sm btn-outline-danger flex-fill delete-file" data-id="<?= (int)$fi['id'] ?>"><i class="fas fa-trash"></i></button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php if ($pages>1): ?>
          <div class="card-footer d-flex justify-content-center gap-2">
            <?php for($i=1;$i<=$pages;$i++): ?>
              <a class="btn btn-sm <?= $i===$page?'btn-warning':'btn-outline-light' ?>" href="?folder=<?= (int)$currentFolderId ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- New Folder Modal -->
    <div class="modal fade" id="newFolderModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
          <div class="modal-header"><h5 class="modal-title">Create Folder</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Folder name</label>
              <input type="text" class="form-control" id="nfName" placeholder="e.g. Images">
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-warning" id="nfCreate">Create</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Attach to course Modal -->
    <div class="modal fade" id="attachModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
          <div class="modal-header"><h5 class="modal-title">Attach to course</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <label class="form-label">course ID</label>
            <input type="number" min="1" class="form-control" id="attachPid" placeholder="e.g. 135">
            <div class="form-text">Enter the target `courses.id`.</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-warning" id="attachDo">Attach</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      (function(){
        const base = location.pathname.replace(/\/admin\/files-manager\.php.*/, '') + 'admin/files-manager.php';
        function post(action, data, cb){
          const fd = new FormData();
          fd.append('v2_action', action);
          Object.keys(data||{}).forEach(k=>fd.append(k, data[k]));
          fetch(base + location.search, { method:'POST', body: fd })
            .then(r=>r.json()).then(cb).catch(()=>cb({ok:false,msg:'Network error'}));
        }
        // Upload
        const uf = document.getElementById('uploadForm');
        if (uf) {
          uf.addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(uf);
            fetch(base + location.search, { method:'POST', body: fd })
              .then(r=>r.json())
              .then(d=>{ alert(d.msg||'Done'); if(d.ok) location.reload(); })
              .catch(()=>alert('Upload failed'));
          });
        }
        // Folder jump
        const fj = document.getElementById('folderJump');
        if (fj) fj.addEventListener('change', function(){ location.href='?folder='+encodeURIComponent(this.value); });
        // New folder
        const nfBtn = document.getElementById('nfCreate');
        if (nfBtn) nfBtn.addEventListener('click', function(){
          const name = document.getElementById('nfName').value.trim();
          if (!name) return alert('Enter folder name');
          post('create_folder', { name: name, parent: '<?= (int)$currentFolderId ?>' }, function(d){
            alert(d.msg||''); if(d.ok) location.reload();
          });
        });
        // Folder rename/delete
        const renBtn = document.getElementById('renameFolderBtn');
        if (renBtn) renBtn.addEventListener('click', function(){
          const nn = prompt('New folder name:'); if(!nn) return;
          post('rename_folder', { id: '<?= (int)$currentFolderId ?>', name: nn }, function(d){ alert(d.msg||''); if(d.ok) location.reload(); });
        });
        const delBtn = document.getElementById('deleteFolderBtn');
        if (delBtn) delBtn.addEventListener('click', function(){
          if (!confirm('Delete this folder? Must be empty.')) return;
          post('delete_folder', { id: '<?= (int)$currentFolderId ?>' }, function(d){ alert(d.msg||''); if(d.ok) location.href='?folder=0'; });
        });
        // File actions
        document.querySelectorAll('.rename-file').forEach(function(b){
          b.addEventListener('click', function(){
            const id = this.dataset.id; const name = this.dataset.name;
            const nn = prompt('New file name (without extension):', name||''); if(!nn) return;
            post('rename_file', { id: id, name: nn }, function(d){ alert(d.msg||''); if(d.ok) location.reload(); });
          });
        });
        document.querySelectorAll('.delete-file').forEach(function(b){
          b.addEventListener('click', function(){
            const id = this.dataset.id; if(!confirm('Delete file?')) return;
            post('delete_file', { id: id }, function(d){ alert(d.msg||''); if(d.ok) location.reload(); });
          });
        });

        // Selection helpers
        function getSelected(){ return Array.from(document.querySelectorAll('.pick:checked')).map(el=>parseInt(el.value,10)).filter(Boolean); }
        const selectAllBtn = document.getElementById('selectAllBtn');
        const clearSelBtn = document.getElementById('clearSelBtn');
        if (selectAllBtn) selectAllBtn.addEventListener('click', function(){ document.querySelectorAll('.pick').forEach(ch=>ch.checked=true); });
        if (clearSelBtn) clearSelBtn.addEventListener('click', function(){ document.querySelectorAll('.pick').forEach(ch=>ch.checked=false); });

        // Bulk delete
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        if (bulkDeleteBtn) bulkDeleteBtn.addEventListener('click', function(){
          const ids = getSelected(); if (!ids.length) return alert('Select files first');
          if (!confirm('Delete selected files?')) return;
          post('bulk_delete', { ids: JSON.stringify(ids) }, function(d){ alert(d.msg||''); if(d.ok) location.reload(); });
        });

        // Bulk move
        const bulkMoveBtn = document.getElementById('bulkMoveBtn');
        const bulkMoveDest = document.getElementById('bulkMoveDest');
        if (bulkMoveBtn && bulkMoveDest) bulkMoveBtn.addEventListener('click', function(){
          const ids = getSelected(); if (!ids.length) return alert('Select files first');
          const folder = parseInt(bulkMoveDest.value||'0',10);
          post('move_files', { ids: JSON.stringify(ids), folder: String(folder) }, function(d){ alert(d.msg||''); if(d.ok) location.reload(); });
        });

        // Attach to course
        const attachBtn = document.getElementById('attachBtn');
        const attachDo = document.getElementById('attachDo');
        const attachPid = document.getElementById('attachPid');
        if (attachBtn) attachBtn.addEventListener('click', function(){
          if (!getSelected().length) return alert('Select files first');
          const m = new bootstrap.Modal(document.getElementById('attachModal')); m.show();
        });
        if (attachDo) attachDo.addEventListener('click', function(){
          const ids = getSelected(); if (!ids.length) return alert('Select files first');
          const pid = parseInt(attachPid.value||'0',10); if (!pid) return alert('Enter course ID');
          post('attach_to_course', { ids: JSON.stringify(ids), course_id: String(pid) }, function(d){ alert(d.msg||''); if(d.ok) location.reload(); });
        });
      })();
    </script>
    <?php include '../includes/footer.php'; exit; }

$error = '';
$success = '';

$error = '';
$success = '';

// Create necessary tables if they don't exist
try {
    // Create file_folders table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS file_folders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        parent_id BIGINT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY parent_id (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Create media_share_tokens table if it doesn't exist (includes quotas and counters)
    $pdo->exec("CREATE TABLE IF NOT EXISTS media_share_tokens (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        media_id BIGINT UNSIGNED NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        max_downloads INT UNSIGNED DEFAULT 10,
        download_count INT UNSIGNED DEFAULT 0,
        KEY media_id (media_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Helper to check if column exists
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->fetch() !== false;
    }

    // Add folder_id column to medias table if it doesn't exist
    if (!columnExists($pdo, 'medias', 'folder_id')) {
        $pdo->exec("ALTER TABLE medias ADD COLUMN folder_id BIGINT UNSIGNED DEFAULT NULL");
    }
    // Add deleted_at column to medias table if it doesn't exist
    if (!columnExists($pdo, 'medias', 'deleted_at')) {
        $pdo->exec("ALTER TABLE medias ADD COLUMN deleted_at TIMESTAMP NULL");
    }
    // Ensure media_share_tokens has expected columns
    if (!columnExists($pdo, 'media_share_tokens', 'max_downloads')) {
        $pdo->exec("ALTER TABLE media_share_tokens ADD COLUMN max_downloads INT UNSIGNED DEFAULT 10 AFTER created_at");
    }
    if (!columnExists($pdo, 'media_share_tokens', 'download_count')) {
        $pdo->exec("ALTER TABLE media_share_tokens ADD COLUMN download_count INT UNSIGNED DEFAULT 0 AFTER max_downloads");
    }
} catch (Exception $e) {
    $error = 'Database setup error: ' . $e->getMessage();
}

// Helper Functions
function getMediaTypeIcon($file_type) {
    $icons = [
        'image' => 'fas fa-image text-primary',
        'video' => 'fas fa-video text-danger',
        'audio' => 'fas fa-music text-success',
        'pdf'   => 'fas fa-file-pdf text-danger',
        'zip'   => 'fas fa-file-archive text-warning',
        'wav'   => 'fas fa-music text-success',
        'doc'   => 'fas fa-file-word text-primary',
        'xls'   => 'fas fa-file-excel text-success',
        'ppt'   => 'fas fa-file-powerpoint text-warning',
        'txt'   => 'fas fa-file-alt text-secondary',
        'default' => 'fas fa-file text-secondary',
    ];
    return $icons[$file_type] ?? $icons['default'];
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

function generateShareToken($media_id, $expiry = '+7 days', $max_downloads = 10) {
    global $pdo;
    try {
        $token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime($expiry));
        $stmt = $pdo->prepare("INSERT INTO media_share_tokens (media_id, token, expires_at, max_downloads) VALUES (?, ?, ?, ?)");
        $stmt->execute([$media_id, $token, $expires_at, $max_downloads]);
        return $token;
    } catch (Exception $e) {
        return false;
    }
}

function getShareLink($token) {
    return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/downloads.php?share_token=' . urlencode($token);
}

function getLatestShareLink($media_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, token, expires_at, max_downloads, download_count FROM media_share_tokens WHERE media_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$media_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return [
            'id' => (int)$row['id'],
            'token' => $row['token'],
            'expires_at' => $row['expires_at'],
            'max_downloads' => isset($row['max_downloads']) ? (int)$row['max_downloads'] : 10,
            'download_count' => isset($row['download_count']) ? (int)$row['download_count'] : 0,
            'link' => getShareLink($row['token'])
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function getFilePreviewHtml($file_path, $file_type = null) {
    if (!$file_type && file_exists($file_path)) {
        $mime = @mime_content_type($file_path);
        if ($mime) {
            if (str_starts_with($mime, 'image/')) $file_type = 'image';
            elseif (str_starts_with($mime, 'video/')) $file_type = 'video';
            elseif (str_starts_with($mime, 'audio/')) $file_type = 'audio';
            elseif (str_contains($mime, 'pdf')) $file_type = 'pdf';
            elseif (str_contains($mime, 'zip')) $file_type = 'zip';
            else $file_type = 'other';
        }
    }
    
    $file_url = str_replace('../', '', $file_path);
    
    if ($file_type === 'image') {
        return '<img src="' . htmlspecialchars($file_url) . '" alt="" class="img-fluid rounded" style="width:40px;height:40px;object-fit:cover;">';
    } elseif ($file_type === 'video') {
        return '<video style="width:40px;height:40px;object-fit:cover;" class="rounded"><source src="' . htmlspecialchars($file_url) . '"></video>';
    } elseif ($file_type === 'audio' || $file_type === 'wav') {
        return '<i class="fas fa-music text-success" style="font-size: 2rem;"></i>';
    } elseif ($file_type === 'pdf') {
        return '<i class="fas fa-file-pdf text-danger" style="font-size: 2rem;"></i>';
    } elseif ($file_type === 'zip') {
        return '<i class="fas fa-file-archive text-warning" style="font-size: 2rem;"></i>';
    }
    return '<i class="fas fa-file text-secondary" style="font-size: 2rem;"></i>';
}

// Unified CUD (Create, Update, Delete) Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Unknown action'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'create_folder') {
            $name = trim($_POST['folder_name'] ?? '');
            $description = trim($_POST['folder_description'] ?? '');
            $parent_id = !empty($_POST['parent_folder']) ? intval($_POST['parent_folder']) : null;
            if (empty($name)) {
                $response['message'] = 'Folder name is required.';
            } elseif (preg_match('/[\/\\?%*:|"<>]/', $name)) {
                $response['message'] = 'Invalid folder name. Cannot contain special characters.';
            } else {
                $check_stmt = $pdo->prepare("SELECT id FROM file_folders WHERE name = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))");
                $check_stmt->execute([$name, $parent_id, $parent_id]);
                if ($check_stmt->fetch()) {
                    $response['message'] = 'Folder name already exists in this location.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO file_folders (name, description, parent_id) VALUES (?, ?, ?)");
                    if ($stmt->execute([$name, $description, $parent_id])) {
                        $response = ['success' => true, 'message' => 'Folder created successfully!'];
                    } else {
                        $response['message'] = 'Failed to create folder.';
                    }
                }
            }
        } elseif ($action === 'rename_file') {
            $id = $_POST['id'];
            $type = $_POST['type'];
            $new_name = trim($_POST['new_name']);
            if (empty($new_name)) {
                $response['message'] = 'Name is required.';
            } elseif (preg_match('/[\/\\?%*:|"<>]/', $new_name)) {
                $response['message'] = 'Invalid name. Cannot contain special characters.';
            } else {
                if ($type === 'folder') {
                    $stmt = $pdo->prepare("UPDATE file_folders SET name = ? WHERE id = ?");
                    if ($stmt->execute([$new_name, $id])) {
                        $response = ['success' => true, 'message' => 'Folder renamed successfully!', 'newName' => $new_name];
                    } else {
                        $response['message'] = 'Failed to rename folder.';
                    }
                } elseif ($type === 'file' || $type === 'db') {
                    $stmt = $pdo->prepare("SELECT value FROM medias WHERE id = ?");
                    $stmt->execute([$id]);
                    $file = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($file) {
                        $old_path = $file['value'];
                        $ext = pathinfo($old_path, PATHINFO_EXTENSION);
                        $dir = dirname($old_path);
                        $new_path = $dir . '/' . $new_name . ($ext ? '.' . $ext : '');
                        if (file_exists('../' . $new_path)) {
                            $response['message'] = 'File with new name already exists.';
                        } else {
                            if (file_exists('../' . $old_path) && rename('../' . $old_path, '../' . $new_path)) {
                                $stmt = $pdo->prepare("UPDATE medias SET value = ? WHERE id = ?");
                                if ($stmt->execute([$new_path, $id])) {
                                    $response = ['success' => true, 'message' => 'File renamed successfully!', 'newName' => basename($new_path)];
                                } else {
                                    $response['message'] = 'Failed to update database.';
                                }
                            } else {
                                $response['message'] = 'Failed to rename file on disk.';
                            }
                        }
                    } else {
                        $response['message'] = 'File not found.';
                    }
                } elseif ($type === 'local') {
                    // Local file rename
                    $basename = $_POST['id'];
                    $local_files_dir = '../uploads/files/';
                    $old_path = $local_files_dir . $basename;
                    $ext = pathinfo($basename, PATHINFO_EXTENSION);
                    $new_path = $local_files_dir . $new_name . ($ext ? '.' . $ext : '');
                    if (file_exists($new_path)) {
                        $response['message'] = 'File with new name already exists.';
                    } else {
                        if (file_exists($old_path) && rename($old_path, $new_path)) {
                            $response = ['success' => true, 'message' => 'File renamed successfully!', 'newName' => basename($new_path)];
                        } else {
                            $response['message'] = 'Failed to rename file on disk.';
                        }
                    }
                }
            }
        } elseif ($action === 'delete_file') {
            $id = $_POST['id'];
            $type = $_POST['type'];
            if ($type === 'folder') {
                // Only allow delete if folder is empty
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM medias WHERE folder_id = ? AND deleted_at IS NULL");
                $check_stmt->execute([$id]);
                $file_count = $check_stmt->fetchColumn();
                if ($file_count > 0) {
                    $response['message'] = 'Cannot delete folder. It contains ' . $file_count . ' file(s).';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM file_folders WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $response = ['success' => true, 'message' => 'Folder deleted successfully!'];
                    } else {
                        $response['message'] = 'Failed to delete folder.';
                    }
                }
            } elseif ($type === 'file' || $type === 'db') {
                $stmt = $pdo->prepare("UPDATE medias SET deleted_at = NOW() WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $response = ['success' => true, 'message' => 'File deleted successfully!'];
                } else {
                    $response['message'] = 'Failed to delete file.';
                }
            } elseif ($type === 'local') {
                $basename = $_POST['id'];
                $local_files_dir = '../uploads/files/';
                $path = $local_files_dir . $basename;
                if (file_exists($path) && unlink($path)) {
                    $response = ['success' => true, 'message' => 'File deleted successfully!'];
                } else {
                    $response['message'] = 'Failed to delete file.';
                }
            }
        } elseif ($action === 'bulk_delete') {
            $db_ids = json_decode($_POST['db_ids'] ?? '[]', true);
            $local_files = json_decode($_POST['local_files'] ?? '[]', true);
            $success_count = 0;
            $fail_count = 0;
            // Delete tracked files
            foreach ($db_ids as $id) {
                $stmt = $pdo->prepare("UPDATE medias SET deleted_at = NOW() WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }
            // Delete local files
            $local_files_dir = '../uploads/files/';
            foreach ($local_files as $basename) {
                $path = $local_files_dir . $basename;
                if (file_exists($path) && unlink($path)) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }
            $response = [
                'success' => $fail_count === 0,
                'message' => ($success_count) . ' file(s) deleted.' . ($fail_count ? " $fail_count failed." : '')
            ];
        } elseif ($action === 'move_to_folder') {
            $file_ids = json_decode($_POST['file_ids'] ?? '[]', true);
            $folder_id = intval($_POST['folder_id'] ?? 0) ?: null;
            $success_count = 0;
            foreach ($file_ids as $id) {
                $stmt = $pdo->prepare("UPDATE medias SET folder_id = ? WHERE id = ?");
                if ($stmt->execute([$folder_id, $id])) {
                    $success_count++;
                }
            }
            $response = [
                'success' => true,
                'message' => $success_count . ' file(s) moved.'
            ];
        } elseif ($action === 'generate_share_link') {
            $media_id = intval($_POST['media_id'] ?? 0);
            $expiry = trim($_POST['expiry'] ?? '+7 days');
            $max_downloads = max(1, intval($_POST['max_downloads'] ?? 10));
            if ($media_id <= 0) {
                $response = ['success' => false, 'message' => 'Invalid media id'];
            } else {
                $token = generateShareToken($media_id, $expiry, $max_downloads);
                if ($token) {
                    $link = getShareLink($token);
                    $response = [
                        'success' => true,
                        'message' => 'Share link generated',
                        'link' => $link,
                        'expires_at' => date('Y-m-d H:i:s', strtotime($expiry)),
                        'max_downloads' => $max_downloads
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to generate share link'];
                }
            }
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

// Handle File Upload (with optional folder assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Upload failed'];
    try {
        $upload_dir = '../uploads/files/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $uploaded_files = [];
        $files = $_FILES['files'];
        $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? intval($_POST['folder_id']) : null;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'rar', 'wav', 'mp3', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = time() . '_' . basename($files['name'][$i]);
                    $target_path = $upload_dir . $filename;
                    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (!in_array($file_extension, $allowed_types)) {
                        $response['message'] = 'Invalid file type: ' . $file_extension;
                        break;
                    }
                    if (move_uploaded_file($files['tmp_name'][$i], $target_path)) {
                        $stmt = $pdo->prepare("INSERT INTO medias (value, file_type, size, type, created_at, folder_id) VALUES (?, ?, ?, 'upload', NOW(), ?)");
                        $file_type = $file_extension;
                        $file_size = filesize($target_path);
                        if ($stmt->execute([$target_path, $file_type, $file_size, $folder_id])) {
                            $uploaded_files[] = $filename;
                        }
                    }
                }
            }
        } else {
            if ($files['error'] === UPLOAD_ERR_OK) {
                $filename = time() . '_' . basename($files['name']);
                $target_path = $upload_dir . $filename;
                $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($file_extension, $allowed_types)) {
                    $response['message'] = 'Invalid file type: ' . $file_extension;
                } elseif (move_uploaded_file($files['tmp_name'], $target_path)) {
                    $stmt = $pdo->prepare("INSERT INTO medias (value, file_type, size, type, created_at, folder_id) VALUES (?, ?, ?, 'upload', NOW(), ?)");
                    $file_type = $file_extension;
                    $file_size = filesize($target_path);
                    if ($stmt->execute([$target_path, $file_type, $file_size, $folder_id])) {
                        $uploaded_files[] = $filename;
                    }
                }
            }
        }
        if (!empty($uploaded_files)) {
            $response = [
                'success' => true,
                'message' => count($uploaded_files) . ' file(s) uploaded successfully'
            ];
        }
    } catch (Exception $e) {
        $response['message'] = 'Upload error: ' . $e->getMessage();
    }
    echo json_encode($response);
    exit;
}


// Handle Create - New Folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $name = trim($_POST['folder_name'] ?? '');
    $description = trim($_POST['folder_description'] ?? '');
    $parent_id = !empty($_POST['parent_folder']) ? intval($_POST['parent_folder']) : null;
    
    if (empty($name)) {
        $error = 'Folder name is required.';
    } elseif (preg_match('/[\/\\\?%*:|"<>]/', $name)) {
        $error = 'Invalid folder name. Cannot contain special characters.';
    } else {
        try {
            // Check if folder name already exists in the same parent
            $check_stmt = $pdo->prepare("SELECT id FROM file_folders WHERE name = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))");
            $check_stmt->execute([$name, $parent_id, $parent_id]);
            if ($check_stmt->fetch()) {
                $error = 'Folder name already exists in this location.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO file_folders (name, description, parent_id) VALUES (?, ?, ?)");
                if ($stmt->execute([$name, $description, $parent_id])) {
                    $success = 'Folder created successfully!';
                } else {
                    $error = 'Failed to create folder.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Delete - File/Folder
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $type = $_GET['type'] ?? 'file';
    
    try {
        if ($type === 'folder') {
            // Check if folder has files
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM medias WHERE folder_id = ? AND deleted_at IS NULL");
            $check_stmt->execute([$id]);
            $file_count = $check_stmt->fetchColumn();
            
            if ($file_count > 0) {
                $error = 'Cannot delete folder. It contains ' . $file_count . ' file(s).';
            } else {
                $stmt = $pdo->prepare("DELETE FROM file_folders WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = 'Folder deleted successfully!';
                } else {
                    $error = 'Failed to delete folder.';
                }
            }
        } else {
            // Delete file
            $stmt = $pdo->prepare("UPDATE medias SET deleted_at = NOW() WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = 'File deleted successfully!';
            } else {
                $error = 'Failed to delete file.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle Update - Rename File/Folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_item'])) {
    $id = intval($_POST['id']);
    $type = $_POST['type'];
    $new_name = trim($_POST['new_name']);
    
    if (empty($new_name)) {
        $error = 'Name is required.';
    } elseif (preg_match('/[\/\\\?%*:|"<>]/', $new_name)) {
        $error = 'Invalid name. Cannot contain special characters.';
    } else {
        try {
            if ($type === 'folder') {
                $stmt = $pdo->prepare("UPDATE file_folders SET name = ? WHERE id = ?");
                if ($stmt->execute([$new_name, $id])) {
                    $success = 'Folder renamed successfully!';
                } else {
                    $error = 'Failed to rename folder.';
                }
            } else {
                // For files, we need to update the file path
                $stmt = $pdo->prepare("SELECT value FROM medias WHERE id = ?");
                $stmt->execute([$id]);
                $file = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($file) {
                    $old_path = $file['value'];
                    $ext = pathinfo($old_path, PATHINFO_EXTENSION);
                    $dir = dirname($old_path);
                    $new_path = $dir . '/' . $new_name . ($ext ? '.' . $ext : '');
                    
                    if (file_exists('../' . $new_path)) {
                        $error = 'File with new name already exists.';
                    } else {
                        // Rename physical file
                        if (file_exists('../' . $old_path) && rename('../' . $old_path, '../' . $new_path)) {
                            // Update database
                            $stmt = $pdo->prepare("UPDATE medias SET value = ? WHERE id = ?");
                            if ($stmt->execute([$new_path, $id])) {
                                $success = 'File renamed successfully!';
                            } else {
                                $error = 'Failed to update database.';
                            }
                        } else {
                            $error = 'Failed to rename file on disk.';
                        }
                    }
                } else {
                    $error = 'File not found.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Create - New Folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $name = trim($_POST['folder_name'] ?? '');
    $description = trim($_POST['folder_description'] ?? '');
    $parent_id = !empty($_POST['parent_folder']) ? intval($_POST['parent_folder']) : null;
    
    if (empty($name)) {
        $error = 'Folder name is required.';
    } elseif (preg_match('/[\/\\\?%*:|"<>]/', $name)) {
        $error = 'Invalid folder name. Cannot contain special characters.';
    } else {
        try {
            // Check if folder name already exists in the same parent
            $check_stmt = $pdo->prepare("SELECT id FROM file_folders WHERE name = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))");
            $check_stmt->execute([$name, $parent_id, $parent_id]);
            if ($check_stmt->fetch()) {
                $error = 'Folder name already exists in this location.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO file_folders (name, description, parent_id) VALUES (?, ?, ?)");
                if ($stmt->execute([$name, $description, $parent_id])) {
                    $success = 'Folder created successfully!';
                } else {
                    $error = 'Failed to create folder.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Delete - File/Folder
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $type = $_GET['type'] ?? 'file';
    
    try {
        if ($type === 'folder') {
            // Check if folder has files
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM medias WHERE folder_id = ? AND deleted_at IS NULL");
            $check_stmt->execute([$id]);
            $file_count = $check_stmt->fetchColumn();
            
            if ($file_count > 0) {
                $error = 'Cannot delete folder. It contains ' . $file_count . ' file(s).';
            } else {
                $stmt = $pdo->prepare("DELETE FROM file_folders WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = 'Folder deleted successfully!';
                } else {
                    $error = 'Failed to delete folder.';
                }
            }
        } else {
            // Delete file
            $stmt = $pdo->prepare("UPDATE medias SET deleted_at = NOW() WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = 'File deleted successfully!';
            } else {
                $error = 'Failed to delete file.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle Update - Rename File/Folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_item'])) {
    $id = intval($_POST['id']);
    $type = $_POST['type'];
    $new_name = trim($_POST['new_name']);
    
    if (empty($new_name)) {
        $error = 'Name is required.';
    } elseif (preg_match('/[\/\\\?%*:|"<>]/', $new_name)) {
        $error = 'Invalid name. Cannot contain special characters.';
    } else {
        try {
            if ($type === 'folder') {
                $stmt = $pdo->prepare("UPDATE file_folders SET name = ? WHERE id = ?");
                if ($stmt->execute([$new_name, $id])) {
                    $success = 'Folder renamed successfully!';
                } else {
                    $error = 'Failed to rename folder.';
                }
            } else {
                // For files, we need to update the file path
                $stmt = $pdo->prepare("SELECT value FROM medias WHERE id = ?");
                $stmt->execute([$id]);
                $file = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($file) {
                    $old_path = $file['value'];
                    $ext = pathinfo($old_path, PATHINFO_EXTENSION);
                    $dir = dirname($old_path);
                    $new_path = $dir . '/' . $new_name . ($ext ? '.' . $ext : '');
                    
                    if (file_exists('../' . $new_path)) {
                        $error = 'File with new name already exists.';
                    } else {
                        // Rename physical file
                        if (file_exists('../' . $old_path) && rename('../' . $old_path, '../' . $new_path)) {
                            // Update database
                            $stmt = $pdo->prepare("UPDATE medias SET value = ? WHERE id = ?");
                            if ($stmt->execute([$new_path, $id])) {
                                $success = 'File renamed successfully!';
                            } else {
                                $error = 'Failed to update database.';
                            }
                        } else {
                            $error = 'Failed to rename file on disk.';
                        }
                    }
                } else {
                    $error = 'File not found.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Filtering & Pagination
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$date_from = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$date_to = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$folder_id = filter_input(INPUT_GET, 'folder_id', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], ['created_at', 'value', 'file_type', 'size']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1);
$perPage = 20;

$where = ['m.deleted_at IS NULL'];
$params = [];

if ($type) {
    $where[] = 'm.file_type = ?';
    $params[] = $type;
}
if ($date_from) {
    $where[] = 'DATE(m.created_at) >= ?';
    $params[] = $date_from;
}
if ($date_to) {
    $where[] = 'DATE(m.created_at) <= ?';
    $params[] = $date_to;
}
if ($folder_id !== '') {
    if ($folder_id === '0') {
        $where[] = '(m.folder_id IS NULL OR m.folder_id = 0)';
    } else {
        $where[] = 'm.folder_id = ?';
        $params[] = $folder_id;
    }
}
if ($search) {
    $where[] = '(m.value LIKE ? OR m.file_type LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $total = $pdo->prepare("SELECT COUNT(*) FROM medias m $where_sql");
    $total->execute($params);
    $total = $total->fetchColumn();
    $pages = ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT m.*, f.name as folder_name 
            FROM medias m 
            LEFT JOIN file_folders f ON m.folder_id = f.id 
            $where_sql 
            ORDER BY m.$sort_by $sort_order 
            LIMIT ?, ?";
    $stmt = $pdo->prepare($sql);
    $params[] = $offset;
    $params[] = $perPage;
    foreach ($params as $i => $value) {
        $stmt->bindValue($i + 1, is_int($value) ? $value : $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $files = [];
    $total = 0;
    $pages = 0;
    $error = 'Database error: ' . $e->getMessage();
}

// Get folders
try {
    $folders = $pdo->query("SELECT id, name FROM file_folders ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $folders = [];
}

// List local files
$local_files_dir = '../uploads/files/';
$local_files = [];
if (is_dir($local_files_dir)) {
    foreach (array_diff(scandir($local_files_dir), ['.', '..']) as $fname) {
        $full = $local_files_dir . $fname;
        if (is_file($full)) {
            $in_db = false;
            foreach ($files as $f) {
                if (basename($f['value']) === $fname) {
                    $in_db = true;
                    break;
                }
            }
            if (!$in_db) {
                $local_files[] = [
                    'basename' => $fname,
                    'full' => $full,
                    'size' => filesize($full),
                    'created_at' => date('Y-m-d H:i:s', filectime($full)),
                ];
            }
        }
    }
}

$title = 'Files Manager - AK23 App';
include '../includes/admin_header.php';
?>

<style>
.form-label, .form-control, .form-select, .form-check-label, h1, h2, h3, h4, h5, h6 {
    color: #000 !important;
}
.card-title, .fw-bold {
    color: #000 !important;
}
.table th, .table td {
    color: #000 !important;
}
.alert {
    color: #000 !important;
}
</style>

<style>
.form-label, .form-control, .form-select, .form-check-label, h1, h2, h3, h4, h5, h6 {
    color: #000 !important;
}
.card-title, .fw-bold {
    color: #000 !important;
}
.table th, .table td {
    color: #000 !important;
}
.alert {
    color: #000 !important;
}
</style>

<main class="container-fluid py-4">
    <!-- Header with Stats -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0" style="color: #000;">Files Manager</h1>
                    <p class="text-muted mb-0">Manage and organize your digital assets</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                        <i class="fas fa-folder-plus me-2"></i>New Folder
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-upload me-2"></i>Upload Files
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Files</h6>
                            <h3 class="mb-0"><?= $total ?></h3>
                        </div>
                        <i class="fas fa-file fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Tracked Files</h6>
                            <h3 class="mb-0"><?= count($files) ?></h3>
                        </div>
                        <i class="fas fa-database fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Untracked Files</h6>
                            <h3 class="mb-0"><?= count($local_files) ?></h3>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Folders</h6>
                            <h3 class="mb-0"><?= count($folders) ?></h3>
                        </div>
                        <i class="fas fa-folder fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-bold">File Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="image" <?= $type === 'image' ? 'selected' : '' ?>>Images</option>
                        <option value="video" <?= $type === 'video' ? 'selected' : '' ?>>Videos</option>
                        <option value="audio" <?= $type === 'audio' ? 'selected' : '' ?>>Audio</option>
                        <option value="pdf" <?= $type === 'pdf' ? 'selected' : '' ?>>PDFs</option>
                        <option value="zip" <?= $type === 'zip' ? 'selected' : '' ?>>Archives</option>
                        <option value="wav" <?= $type === 'wav' ? 'selected' : '' ?>>WAV Files</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Folder</label>
                    <select name="folder_id" class="form-select">
                        <option value="">All Folders</option>
                        <option value="0" <?= $folder_id === '0' ? 'selected' : '' ?>>No Folder</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= $folder['id'] ?>" <?= $folder_id == $folder['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($folder['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Sort By</label>
                    <select name="sort_by" class="form-select">
                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Upload Date</option>
                        <option value="value" <?= $sort_by === 'value' ? 'selected' : '' ?>>File Name</option>
                        <option value="file_type" <?= $sort_by === 'file_type' ? 'selected' : '' ?>>File Type</option>
                        <option value="size" <?= $sort_by === 'size' ? 'selected' : '' ?>>File Size</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Order</label>
                    <select name="sort_order" class="form-select">
                        <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                        <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search files by name or type..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                        <a href="files-manager.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Files Table -->
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0" style="color: #000;">Files List (<?= $total ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($files) && empty($local_files)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <h5 style="color: #000;">No files found</h5>
                    <p class="text-muted">Upload your first file to get started.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="60">Preview</th>
                                <th>File Name</th>
                                <th width="100">Type</th>
                                <th width="100">Size</th>
                                <th width="120">Folder</th>
                                <th width="120">Upload Date</th>
                                <th width="200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td>
                                        <?= getFilePreviewHtml($file['value'], $file['file_type'] ?? null) ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars(basename($file['value'])) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($file['value']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= strtoupper($file['file_type'] ?? 'unknown') ?></span>
                                    </td>
                                    <td><?= formatFileSize($file['size'] ?? 0) ?></td>
                                    <td>
                                        <?php if ($file['folder_name']): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($file['folder_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">No Folder</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($file['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= htmlspecialchars($file['value']) ?>" class="btn btn-outline-primary" target="_blank" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= htmlspecialchars($file['value']) ?>" class="btn btn-outline-success" download title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-warning rename-btn" data-id="<?= $file['id'] ?>" data-type="file" data-name="<?= htmlspecialchars(basename($file['value'])) ?>" title="Rename">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="files-manager.php?delete=<?= $file['id'] ?>&type=file" class="btn btn-outline-danger" title="Delete" 
                                               onclick="return confirm('Are you sure you want to delete this file?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($local_files as $lf): ?>
                                <tr>
                                    <td>
                                        <?= getFilePreviewHtml($lf['full']) ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($lf['basename']) ?></div>
                                                <small class="text-muted">Untracked File</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">UNTRACKED</span>
                                    </td>
                                    <td><?= formatFileSize($lf['size']) ?></td>
                                    <td>
                                        <span class="text-muted">No Folder</span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($lf['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= htmlspecialchars($lf['full']) ?>" class="btn btn-outline-primary" target="_blank" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= htmlspecialchars($lf['full']) ?>" class="btn btn-outline-success" download title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-warning rename-btn" data-id="<?= htmlspecialchars($lf['basename']) ?>" data-type="local" data-name="<?= htmlspecialchars($lf['basename']) ?>" title="Rename">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="files-manager.php?delete=<?= urlencode($lf['basename']) ?>&type=local" class="btn btn-outline-danger" title="Delete" 
                                               onclick="return confirm('Are you sure you want to delete this file?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
        <nav aria-label="Files pagination" class="mt-4">
            <ul class="pagination justify-content-center flex-wrap">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item<?= $i == $page ? ' active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&type=<?= urlencode($type) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>&folder_id=<?= urlencode($folder_id) ?>&sort_by=<?= urlencode($sort_by) ?>&sort_order=<?= urlencode($sort_order) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<?php // Per-file Preview/Share/Rename modals
include __DIR__ . '/includes/file_manager_modals.php'; ?>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color: #000;">Upload Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="files" class="form-label">Select Files</label>
                        <input type="file" class="form-control" id="files" name="files[]" multiple required>
                        <div class="form-text">Supported: JPG, PNG, GIF, PDF, ZIP, RAR, WAV, MP3, DOC, XLS, TXT</div>
                    </div>
                    <div id="progressContainer" class="d-none">
                        <div class="progress">
                            <div id="uploadProgress" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Files</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Folder Modal -->
<div class="modal fade" id="createFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color: #000;">Create New Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="folder_name" class="form-label">Folder Name *</label>
                        <input type="text" class="form-control" id="folder_name" name="folder_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="folder_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control rte" id="folder_description" name="folder_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="parent_folder" class="form-label">Parent Folder</label>
                        <select class="form-select" id="parent_folder" name="parent_folder">
                            <option value="">Root Folder</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?= $folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_folder" class="btn btn-primary">Create Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color: #000;">Rename Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="rename_id" name="id">
                <input type="hidden" id="rename_type" name="type">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_name" class="form-label">New Name</label>
                        <input type="text" class="form-control" id="new_name" name="new_name" required>
                        <div class="form-text">Enter the new name without file extension.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="rename_item" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Rename button -> open modal
  document.querySelectorAll('.rename-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const id = this.dataset.id;
      const type = this.dataset.type;
      const currentName = this.dataset.name || '';
      document.getElementById('rename_id').value = id;
      document.getElementById('rename_type').value = type;
      document.getElementById('new_name').value = currentName.replace(/\.[^\/.]+$/, '');
      new bootstrap.Modal(document.getElementById('renameModal')).show();
    });
  });

  // Upload with progress using XHR
  const uploadForm = document.getElementById('uploadForm');
  if (uploadForm) {
    uploadForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(uploadForm);
      const progressContainer = document.getElementById('progressContainer');
      const progressBar = document.getElementById('uploadProgress');
      progressContainer.classList.remove('d-none');
      progressBar.style.width = '0%';
      progressBar.textContent = '0%';
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'files-manager.php');
      xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          progressBar.style.width = percent + '%';
          progressBar.textContent = percent + '%';
        }
      });
      xhr.onload = function() {
        progressContainer.classList.add('d-none');
        try {
          const data = JSON.parse(xhr.responseText);
          if (data.success) {
            alert(data.message);
            location.reload();
          } else {
            alert('Error: ' + (data.message || 'Upload failed'));
          }
        } catch (err) {
          alert('Upload failed: Invalid server response');
        }
      };
      xhr.onerror = function() {
        progressContainer.classList.add('d-none');
        alert('Upload failed: Network error');
      };
      xhr.send(formData);
    });
  }

  // Copy to clipboard (buttons with data-target)
  document.querySelectorAll('.copy-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const targetSel = this.getAttribute('data-target');
      const input = targetSel ? document.querySelector(targetSel) : null;
      if (input) {
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand('copy');
        this.textContent = 'Copied!';
        setTimeout(() => { this.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 1200);
      }
    });
  });

  // Generate share link buttons in modals
  document.querySelectorAll('.generate-share-link-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const mediaId = this.getAttribute('data-media-id');
      const modal = this.closest('.modal');
      const expirySel = modal ? modal.querySelector('[id^="expirySelect-"]') : null;
      const maxSel = modal ? modal.querySelector('[id^="maxDownloads-"]') : null;
      const linkInput = modal ? modal.querySelector('[id^="shareLinkInput-"]') : null;
      const expiry = expirySel ? expirySel.value : '+7 days';
      const maxDownloads = maxSel ? parseInt(maxSel.value || '10', 10) : 10;
      const fd = new FormData();
      fd.append('action', 'generate_share_link');
      fd.append('media_id', mediaId);
      fd.append('expiry', expiry);
      fd.append('max_downloads', maxDownloads);
      fetch('files-manager.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            if (linkInput) linkInput.value = data.link;
            alert('Share link generated');
          } else {
            alert('Error: ' + (data.message || 'Failed to generate link'));
          }
        })
        .catch(err => alert('Network error: ' + err.message));
    });
  });

  // Auto-hide alerts
  setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
      try { new bootstrap.Alert(alert).close(); } catch (e) {}
    });
  }, 5000);
});
</script>

<?php include '../includes/admin_footer.php'; ?>
