<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

checkInstructorAuth();

$instructor_user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id FROM instructors WHERE user_id = ?");
$stmt->execute([$instructor_user_id]);
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);
$instructor_id = $instructor['id'] ?? 0;

$wallet = [
    'balance'       => 0,
    'total_earned'  => 0,
    'last_withdraw' => null,
    'wallet_number' => ''
];

$transactions = [];
$withdraw_requests = [];
$wallet_error = '';
$wallet_success = '';

// Ensure base tables exist (safe guards; do not fail page if already exist)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS instructor_wallet_transactions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        instructor_id INT UNSIGNED NOT NULL,
        type VARCHAR(50) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_iwt_instructor (instructor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS instructor_withdraw_requests (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        instructor_id INT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        method VARCHAR(50) NOT NULL,
        phone_number VARCHAR(50) DEFAULT NULL,
        bank_name VARCHAR(191) DEFAULT NULL,
        account_number VARCHAR(191) DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_iwr_instructor (instructor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // ignore
}

if ($instructor_id) {
    // Load wallet
    $stmt = $pdo->prepare("SELECT balance, total_earned, last_withdraw, wallet_number FROM instructor_wallet WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $wallet['balance']       = (float)($row['balance'] ?? 0);
        $wallet['total_earned']  = (float)($row['total_earned'] ?? 0);
        $wallet['last_withdraw'] = $row['last_withdraw'] ?? null;
        $wallet['wallet_number'] = (string)($row['wallet_number'] ?? '');
    }

    // Handle wallet POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $w_action = $_POST['wallet_action'] ?? '';
        if ($w_action === 'send_money') {
            $to_wallet = trim((string)($_POST['to_wallet'] ?? ''));
            $amount = (float)($_POST['amount'] ?? 0);
            if ($to_wallet === '' || $amount <= 0) {
                $wallet_error = 'Destination wallet and positive amount are required.';
            } elseif ($amount > $wallet['balance']) {
                $wallet_error = 'Insufficient balance.';
            } else {
                try {
                    $pdo->beginTransaction();
                    // Find receiver
                    $stmt = $pdo->prepare("SELECT instructor_id FROM instructor_wallet WHERE wallet_number = ? FOR UPDATE");
                    $stmt->execute([$to_wallet]);
                    $rcv_id = (int)($stmt->fetchColumn() ?: 0);
                    if (!$rcv_id) {
                        throw new Exception('Destination wallet not found.');
                    }

                    // Refresh sender balance with lock
                    $stmt = $pdo->prepare("SELECT balance FROM instructor_wallet WHERE instructor_id = ? FOR UPDATE");
                    $stmt->execute([$instructor_id]);
                    $sender_balance = (float)($stmt->fetchColumn() ?? 0);
                    if ($amount > $sender_balance) {
                        throw new Exception('Insufficient balance.');
                    }

                    // Update sender
                    $stmt = $pdo->prepare("UPDATE instructor_wallet SET balance = balance - ? WHERE instructor_id = ?");
                    $stmt->execute([$amount, $instructor_id]);
                    $stmt = $pdo->prepare("INSERT INTO instructor_wallet_transactions (instructor_id, type, amount, description) VALUES (?, 'debit', ?, 'Transfer to " . "'" . " . :to_wallet . " . "'" . "')");
                    // We cannot bind named param inside text above; use simple description
                    $desc = 'Transfer to ' . $to_wallet;
                    $stmt = $pdo->prepare("INSERT INTO instructor_wallet_transactions (instructor_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
                    $stmt->execute([$instructor_id, $amount, $desc]);

                    // Update receiver
                    $stmt = $pdo->prepare("UPDATE instructor_wallet SET balance = balance + ? WHERE instructor_id = ?");
                    $stmt->execute([$amount, $rcv_id]);
                    $desc2 = 'Transfer from ' . ($wallet['wallet_number'] ?: ('ID ' . $instructor_id));
                    $stmt = $pdo->prepare("INSERT INTO instructor_wallet_transactions (instructor_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                    $stmt->execute([$rcv_id, $amount, $desc2]);

                    $pdo->commit();
                    $wallet_success = 'Transfer completed successfully.';
                } catch (Throwable $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $wallet_error = $ex->getMessage() ?: 'Failed to transfer funds.';
                }
            }
        } elseif ($w_action === 'withdraw_request') {
            $amount = (float)($_POST['amount'] ?? 0);
            $method = trim((string)($_POST['method'] ?? ''));
            $phone  = trim((string)($_POST['phone_number'] ?? ''));
            $bank   = trim((string)($_POST['bank_name'] ?? ''));
            $acct   = trim((string)($_POST['account_number'] ?? ''));
            if ($amount <= 0 || $amount > $wallet['balance']) {
                $wallet_error = 'Invalid withdraw amount.';
            } elseif ($method === '' || !in_array($method, ['mobile_money', 'bank'], true)) {
                $wallet_error = 'Invalid withdraw method.';
            } elseif ($method === 'mobile_money' && $phone === '') {
                $wallet_error = 'Phone number is required for mobile money.';
            } elseif ($method === 'bank' && ($bank === '' || $acct === '')) {
                $wallet_error = 'Bank name and account number are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO instructor_withdraw_requests (instructor_id, amount, method, phone_number, bank_name, account_number) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$instructor_id, $amount, $method, $phone ?: null, $bank ?: null, $acct ?: null]);
                    $wallet_success = 'Withdraw request submitted.';
                } catch (Throwable $ex) {
                    $wallet_error = 'Failed to submit withdraw request.';
                }
            }
        }

        // Reload wallet after any action
        $stmt = $pdo->prepare("SELECT balance, total_earned, last_withdraw, wallet_number FROM instructor_wallet WHERE instructor_id = ?");
        $stmt->execute([$instructor_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $wallet['balance']       = (float)($row['balance'] ?? 0);
            $wallet['total_earned']  = (float)($row['total_earned'] ?? 0);
            $wallet['last_withdraw'] = $row['last_withdraw'] ?? null;
            $wallet['wallet_number'] = (string)($row['wallet_number'] ?? '');
        }
    }

    // Load last transactions
    $stmt = $pdo->prepare("SELECT type, amount, description, created_at FROM instructor_wallet_transactions WHERE instructor_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$instructor_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Load recent withdraw requests
    $stmt = $pdo->prepare("SELECT amount, method, status, created_at FROM instructor_withdraw_requests WHERE instructor_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$instructor_id]);
    $withdraw_requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$title = 'My Earnings - Instructor';
include 'user_header.php';
include 'instructor_sidebar.php';
?>
<style>
.card-modern {
  background: linear-gradient(135deg, #e0f7fa, #80deea);
  border-radius: 15px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
  padding: 20px;
  color: #333;
  position: relative;
}
.card-modern .card-logo {
  position: absolute;
  top: 15px;
  right: 15px;
  height: 30px;
}
.card-modern .card-number {
  font-size: 1.2rem;
  letter-spacing: 2px;
  margin: 15px 0;
}
.card-modern .card-holder {
  text-transform: uppercase;
  font-weight: bold;
}
</style>
<style>
/* Main content adjustment for fixed sidebar */
@media (min-width: 768px) {
    main {
        margin-left: 200px;
        width: calc(100% - 200px);
    }
}
</style>
<main class="px-md-4 py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0" style="color:black;">My Wallet &amp; Earnings</h1>
    <div>
      <a href="instructor_earnings.php#send-money" class="btn btn-primary me-2">
        <i class="fas fa-paper-plane me-1"></i> Send Money
      </a>
      <a href="instructor_earnings.php#withdraw" class="btn btn-success">
        <i class="fas fa-money-bill-transfer me-1"></i> Withdraw
      </a>
    </div>
  </div>
  
  <?php if ($wallet_error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($wallet_error) ?></div>
  <?php endif; ?>
  <?php if ($wallet_success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($wallet_success) ?></div>
  <?php endif; ?>
  
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card-modern h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted mb-2">Available Balance</h6>
            <div class="h3 fw-bold mb-0">TSH <?= number_format((float)$wallet['balance'], 0) ?></div>
          </div>
          <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
            <i class="fas fa-wallet fa-2x text-primary"></i>
          </div>
        </div>
        <div class="mt-3">
          <span class="text-muted small">Wallet: <?= htmlspecialchars($wallet['wallet_number'] ?: 'N/A') ?></span>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card-modern h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted mb-2">Total Earnings</h6>
            <div class="h3 fw-bold mb-0">TSH <?= number_format((float)$wallet['total_earned'], 0) ?></div>
          </div>
          <div class="bg-success bg-opacity-10 p-3 rounded-circle">
            <i class="fas fa-chart-line fa-2x text-success"></i>
          </div>
        </div>
        <div class="mt-3">
          <span class="text-muted small">Lifetime earnings</span>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card-modern h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted mb-2">Last Withdrawal</h6>
            <div class="h3 fw-bold mb-0">
              <?= $wallet['last_withdraw'] ? date('M j, Y', strtotime($wallet['last_withdraw'])) : 'N/A' ?>
            </div>
          </div>
          <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
            <i class="fas fa-money-bill-transfer fa-2x text-warning"></i>
          </div>
        </div>
        <div class="mt-3">
          <a href="instructor_earnings.php#withdraw" class="text-decoration-none">
            <span class="badge bg-light text-dark">Withdraw Now</span>
          </a>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Send Money Section -->
  <div class="card shadow-sm border-0 mb-4" id="send-money">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="fas fa-paper-plane me-2"></i>Send Money
      </h5>
      <span class="badge bg-light text-dark">Transfer to another instructor wallet</span>
    </div>
    <div class="card-body p-4">
      <form method="post" class="row g-3">
        <input type="hidden" name="wallet_action" value="send_money">
        <div class="col-md-6">
          <label class="form-label fw-bold">Your Wallet Number</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($wallet['wallet_number'] ?: 'N/A') ?>" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Current Balance (TZS)</label>
          <input type="text" class="form-control" value="<?= number_format((float)$wallet['balance'], 0) ?>" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">To Wallet Number <span class="text-danger">*</span></label>
          <input type="text" name="to_wallet" class="form-control" placeholder="AKW-XXXX-XXXX" required>
          <small class="text-muted">Enter the destination wallet number</small>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Amount (TZS) <span class="text-danger">*</span></label>
          <input type="number" name="amount" class="form-control" min="100" step="1" max="<?= (int)$wallet['balance'] ?>" required>
          <small class="text-muted">Minimum: TZS 100</small>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane me-2"></i>Send Money
          </button>
          <a href="instructor_earnings.php#withdraw" class="btn btn-outline-secondary ms-2">
            <i class="fas fa-money-bill-transfer me-2"></i>Go to Withdraw
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Withdraw Section -->
  <div class="card shadow-sm border-0 mb-4" id="withdraw">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="fas fa-money-bill-transfer me-2"></i>Withdraw Funds
      </h5>
      <span class="badge bg-light text-dark">Request withdrawal to your account</span>
    </div>
    <div class="card-body p-4">
      <form method="post" class="row g-3">
        <input type="hidden" name="wallet_action" value="withdraw_request">
        <div class="col-md-6">
          <label class="form-label fw-bold">Current Balance (TZS)</label>
          <input type="text" class="form-control" value="<?= number_format((float)$wallet['balance'], 0) ?>" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Amount to Withdraw (TZS) <span class="text-danger">*</span></label>
          <input type="number" name="amount" class="form-control" min="100" step="1" max="<?= (int)$wallet['balance'] ?>" required>
          <small class="text-muted">Minimum: TZS 100</small>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Withdrawal Method <span class="text-danger">*</span></label>
          <select name="method" class="form-select" id="withdrawMethod" required>
            <option value="">Select method</option>
            <option value="mobile_money">Mobile Money (Phone)</option>
            <option value="bank">Bank Transfer</option>
          </select>
        </div>
        <div class="col-md-6 d-none" id="phoneField">
          <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
          <input type="text" name="phone_number" class="form-control" placeholder="e.g. +2557XXXXXXXX">
          <small class="text-muted">Enter your mobile money phone number</small>
        </div>
        <div class="col-md-6 d-none" id="bankField">
          <label class="form-label fw-bold">Bank Name <span class="text-danger">*</span></label>
          <input type="text" name="bank_name" class="form-control" placeholder="e.g. CRDB, NMB, etc.">
        </div>
        <div class="col-md-6 d-none" id="accountField">
          <label class="form-label fw-bold">Account Number <span class="text-danger">*</span></label>
          <input type="text" name="account_number" class="form-control" placeholder="e.g. 0123456789">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-money-bill-transfer me-2"></i>Submit Withdraw Request
          </button>
          <a href="instructor_earnings.php#send-money" class="btn btn-outline-secondary ms-2">
            <i class="fas fa-paper-plane me-2"></i>Go to Send Money
          </a>
        </div>
      </form>
    </div>
  </div>
  
  <script>
  // Toggle withdrawal method fields
  const methodSelect = document.getElementById('withdrawMethod');
  const phoneField = document.getElementById('phoneField');
  const bankField = document.getElementById('bankField');
  const accountField = document.getElementById('accountField');
  
  function toggleWithdrawFields() {
    const val = methodSelect.value;
    phoneField.classList.add('d-none');
    bankField.classList.add('d-none');
    accountField.classList.add('d-none');
    
    if (val === 'mobile_money') {
      phoneField.classList.remove('d-none');
    } else if (val === 'bank') {
      bankField.classList.remove('d-none');
      accountField.classList.remove('d-none');
    }
  }
  
  if (methodSelect) {
    methodSelect.addEventListener('change', toggleWithdrawFields);
  }
  
  // Scroll to section if hash is present
  window.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
      const hash = window.location.hash.substring(1);
      const element = document.getElementById(hash);
      if (element) {
        setTimeout(() => {
          element.scrollIntoView({ behavior: 'smooth', block: 'start' });
          // Add highlight effect
          element.style.transition = 'box-shadow 0.3s';
          element.style.boxShadow = '0 0 20px rgba(37, 99, 235, 0.5)';
          setTimeout(() => {
            element.style.boxShadow = '';
          }, 2000);
        }, 100);
      }
    }
  });
  
  // Export transactions function
  function exportTransactions() {
    alert('Export feature coming soon!');
  }
  
  // Filter transactions function
  function filterTransactions() {
    alert('Filter feature coming soon!');
  }
  </script>

  <!-- Transactions Section -->
  <div class="card shadow-sm border-0 mb-4" id="transactions">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="fas fa-list me-2"></i>Transaction History
      </h5>
      <div>
        <button class="btn btn-sm btn-outline-light me-2" onclick="exportTransactions()">
          <i class="fas fa-download me-1"></i> Export
        </button>
        <button class="btn btn-sm btn-outline-light" onclick="filterTransactions()">
          <i class="fas fa-filter me-1"></i> Filter
        </button>
      </div>
    </div>
    <div class="card-body p-0">
      <?php if (empty($transactions)): ?>
        <div class="text-center p-5">
          <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
          <p class="text-muted mb-0">No transactions yet</p>
          <p class="small text-muted">Your transaction history will appear here</p>
          <a href="instructor_earnings.php#send-money" class="btn btn-primary mt-2">
            <i class="fas fa-paper-plane me-1"></i> Send Money
          </a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Description</th>
                <th class="text-end pe-4">Amount</th>
                <th class="text-end pe-4">Date</th>
                <th class="text-end pe-4">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transactions as $tr): 
                $isCredit = ($tr['type'] ?? '') === 'credit';
                $isDebit = ($tr['type'] ?? '') === 'debit';
              ?>
                <tr>
                  <td class="ps-4">
                    <div class="d-flex align-items-center">
                      <div class="me-3">
                        <?php if ($isCredit): ?>
                          <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                            <i class="fas fa-arrow-down"></i>
                          </div>
                        <?php elseif ($isDebit): ?>
                          <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                            <i class="fas fa-arrow-up"></i>
                          </div>
                        <?php else: ?>
                          <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-2">
                            <i class="fas fa-exchange-alt"></i>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div>
                        <div class="fw-medium"><?= htmlspecialchars(ucfirst($tr['type'] ?? 'Transaction')) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($tr['description'] ?? '') ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="text-end pe-4 fw-medium <?= $isCredit ? 'text-success' : ($isDebit ? 'text-danger' : '') ?>">
                    <?= $isCredit ? '+' : ($isDebit ? '-' : '') ?> TSH <?= number_format((float)($tr['amount'] ?? 0), 0) ?>
                  </td>
                  <td class="text-end pe-4 text-muted small">
                    <?= date('M j, Y', strtotime($tr['created_at'] ?? '')) ?>
                  </td>
                  <td class="text-end pe-4">
                    <span class="badge bg-success bg-opacity-10 text-success">
                      <i class="fas fa-check-circle me-1"></i> Completed
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($transactions)): ?>
      <div class="card-footer bg-light">
        <div class="d-flex justify-content-between align-items-center">
          <div class="text-muted small">
            Showing 1 to <?= min(10, count($transactions)) ?> of <?= count($transactions) ?> entries
          </div>
          <nav>
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item disabled">
                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
              </li>
              <li class="page-item active"><a class="page-link" href="#">1</a></li>
              <li class="page-item"><a class="page-link" href="#">2</a></li>
              <li class="page-item"><a class="page-link" href="#">3</a></li>
              <li class="page-item">
                <a class="page-link" href="#">Next</a>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include 'user_footer.php'; ?>
