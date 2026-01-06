<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/functions.php';
$title = 'course Requests';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS course_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  name VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL,
  phone VARCHAR(50) NULL,
  course_name VARCHAR(255) NOT NULL,
  course_link VARCHAR(512) NULL,
  category VARCHAR(191) NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  status ENUM('new','in_review','fulfilled','rejected') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_email (email),
  INDEX idx_user_created (user_id, created_at),
  INDEX idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$allowedStatuses = ['','new','in_review','fulfilled','rejected'];
if (!in_array($status, $allowedStatuses, true)) { $status = ''; }

$sql = 'SELECT * FROM course_requests';
$params = [];
if ($status !== '') { $sql .= ' WHERE status = :s'; $params[':s'] = $status; }
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">course Requests</h1>
    <form class="d-flex" method="get">
      <select name="status" class="form-select form-select-sm me-2" onchange="this.form.submit()">
        <option value="" <?php echo $status===''?'selected':''; ?>>All</option>
        <option value="new" <?php echo $status==='new'?'selected':''; ?>>New</option>
        <option value="in_review" <?php echo $status==='in_review'?'selected':''; ?>>In Review</option>
        <option value="fulfilled" <?php echo $status==='fulfilled'?'selected':''; ?>>Fulfilled</option>
        <option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>Rejected</option>
      </select>
      <noscript><button class="btn btn-sm btn-secondary">Filter</button></noscript>
    </form>
  </div>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Requester</th>
          <th>Contact</th>
          <th>course</th>
          <th>Category</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo (int)$r['id']; ?></td>
          <td><?php echo htmlspecialchars($r['created_at']); ?></td>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars($r['name']); ?></div>
            <button class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#d<?php echo (int)$r['id']; ?>">Details</button>
            <div class="collapse" id="d<?php echo (int)$r['id']; ?>">
              <?php if (!empty($r['details'])): ?>
                <div class="small text-muted mb-2"><?php echo nl2br(htmlspecialchars($r['details'])); ?></div>
              <?php else: ?>
                <div class="small text-muted mb-2">No additional details provided.</div>
              <?php endif; ?>
              <div class="small text-muted">
                <div><strong>IP:</strong> <?php echo htmlspecialchars($r['ip_address'] ?? ''); ?></div>
                <div class="text-truncate" style="max-width: 520px;"><strong>UA:</strong> <?php echo htmlspecialchars($r['user_agent'] ?? ''); ?></div>
                <?php if (!empty($r['device_fingerprint'])): ?>
                  <div><strong>Device:</strong> <?php echo htmlspecialchars($r['device_fingerprint']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <div><i class="fa fa-envelope me-1"></i><?php echo htmlspecialchars($r['email']); ?></div>
            <?php if (!empty($r['phone'])): ?><div><i class="fa fa-phone me-1"></i><?php echo htmlspecialchars($r['phone']); ?></div><?php endif; ?>
          </td>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars($r['course_name']); ?></div>
            <?php if (!empty($r['course_link'])): ?>
              <a href="<?php echo htmlspecialchars($r['course_link']); ?>" target="_blank" rel="noopener">Open link</a>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($r['category'] ?? ''); ?></td>
          <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($r['status']); ?></span></td>
          <td>
            <form method="post" action="course_requests_update.php" class="d-flex align-items-center gap-2">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <select name="status" class="form-select form-select-sm">
                <option value="new" <?php echo $r['status']==='new'?'selected':''; ?>>New</option>
                <option value="in_review" <?php echo $r['status']==='in_review'?'selected':''; ?>>In Review</option>
                <option value="fulfilled" <?php echo $r['status']==='fulfilled'?'selected':''; ?>>Fulfilled</option>
                <option value="rejected" <?php echo $r['status']==='rejected'?'selected':''; ?>>Rejected</option>
              </select>
              <button class="btn btn-sm btn-primary">Update</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
