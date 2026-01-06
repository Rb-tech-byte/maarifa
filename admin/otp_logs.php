<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
require_once '../includes/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/otp.php';
require_once '../includes/auth.php';

checkAdminAuth();

// Ensure table exists
ak23_otp_table_init($pdo);

// Filters
$phone = isset($_GET['phone']) ? trim((string)$_GET['phone']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($phone !== '') {
  if (function_exists('cleanPhoneNumber')) { $phone = cleanPhoneNumber($phone); }
  $where[] = 'phone = ?';
  $params[] = $phone;
}
if ($status === 'used') { $where[] = 'used_at IS NOT NULL'; }
elseif ($status === 'unused') { $where[] = 'used_at IS NULL'; }
elseif ($status === 'expired') { $where[] = 'used_at IS NULL AND expires_at < NOW()'; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM otps $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

// Fetch
$listStmt = $pdo->prepare("SELECT id, user_id, phone, code, expires_at, used_at, created_at FROM otps $whereSql ORDER BY id DESC LIMIT ? OFFSET ?");
foreach ($params as $i=>$v) { $listStmt->bindValue($i+1, $v); }
$listStmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$listStmt->bindValue(count($params)+2, $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'OTP Logs - AK23 Admin';
include '../includes/admin_header.php';
?>
<div class="container-fluid p-3">
  <h1 class="h4 mb-3">OTP Logs</h1>
  <form class="row g-2 mb-3" method="GET">
    <div class="col-sm-4">
      <label class="form-label">Phone</label>
      <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" placeholder="+255...">
    </div>
    <div class="col-sm-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="" <?= $status===''?'selected':'' ?>>All</option>
        <option value="unused" <?= $status==='unused'?'selected':'' ?>>Unused</option>
        <option value="used" <?= $status==='used'?'selected':'' ?>>Used</option>
        <option value="expired" <?= $status==='expired'?'selected':'' ?>>Expired</option>
      </select>
    </div>
    <div class="col-sm-2 d-flex align-items-end">
      <button class="btn btn-warning w-100" type="submit">Search</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>User</th>
          <th>Phone</th>
          <th>Code</th>
          <th>Expires</th>
          <th>Used At</th>
          <th>Created</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted">No records</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <?php
            $st = 'unused';
            if (!empty($r['used_at'])) { $st = 'used'; }
            elseif (strtotime($r['expires_at']) < time()) { $st = 'expired'; }
          ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= $r['user_id'] ? (int)$r['user_id'] : '-' ?></td>
            <td><?= htmlspecialchars($r['phone']) ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($r['code']) ?></span></td>
            <td><?= htmlspecialchars($r['expires_at']) ?></td>
            <td><?= htmlspecialchars($r['used_at'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td>
              <span class="badge bg-<?= $st==='unused'?'warning':($st==='used'?'success':'danger') ?>"><?= strtoupper($st) ?></span>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages>1): ?>
  <nav aria-label="pagination">
    <ul class="pagination justify-content-center">
      <li class="page-item <?= ($page<=1)?'disabled':'' ?>">
        <a class="page-link" href="?<?= http_build_query(['phone'=>$phone,'status'=>$status,'page'=>max(1,$page-1)]) ?>">Prev</a>
      </li>
      <?php for ($i=1;$i<=$pages;$i++): ?>
        <li class="page-item <?= ($i===$page)?'active':'' ?>">
          <a class="page-link" href="?<?= http_build_query(['phone'=>$phone,'status'=>$status,'page'=>$i]) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= ($page>=$pages)?'disabled':'' ?>">
        <a class="page-link" href="?<?= http_build_query(['phone'=>$phone,'status'=>$status,'page'=>min($pages,$page+1)]) ?>">Next</a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>
</div>
<?php include '../includes/admin_footer.php'; ?>
