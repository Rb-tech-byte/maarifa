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

$errors = [];
$success = '';
$tab = $_GET['tab'] ?? 'withdrawals';
$selected_instructor_id = isset($_GET['instructor_id']) ? (int)$_GET['instructor_id'] : 0;

// If instructor_id is provided, switch to appropriate tab
if ($selected_instructor_id > 0 && $tab === 'withdrawals') {
    $tab = 'transfer';
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $admin_id = (int)($_SESSION['admin_id'] ?? 0);

    if ($action === 'approve_withdrawal') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        if ($request_id > 0) {
            try {
                $pdo->beginTransaction();
                // Get withdrawal request
                $stmt = $pdo->prepare("SELECT * FROM instructor_withdraw_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
                $stmt->execute([$request_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$request) {
                    throw new Exception('Withdrawal request not found or already processed.');
                }

                // Update status
                $stmt = $pdo->prepare("UPDATE instructor_withdraw_requests SET status = 'approved', processed_at = NOW() WHERE id = ?");
                $stmt->execute([$request_id]);
                
                $pdo->commit();
                $success = 'Withdrawal request approved successfully.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'reject_withdrawal') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        if ($request_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE instructor_withdraw_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?");
                $stmt->execute([$request_id]);
                $success = 'Withdrawal request rejected.';
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'process_payout') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        if ($request_id > 0) {
            try {
                $pdo->beginTransaction();
                
                // Get approved withdrawal request
                $stmt = $pdo->prepare("SELECT * FROM instructor_withdraw_requests WHERE id = ? AND status = 'approved' FOR UPDATE");
                $stmt->execute([$request_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$request) {
                    throw new Exception('Withdrawal request not found or not approved.');
                }

                $instructor_id = (int)$request['instructor_id'];
                $amount = (float)$request['amount'];

                // Check wallet balance
                $stmt = $pdo->prepare("SELECT balance FROM instructor_wallet WHERE instructor_id = ? FOR UPDATE");
                $stmt->execute([$instructor_id]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$wallet || (float)$wallet['balance'] < $amount) {
                    throw new Exception('Insufficient wallet balance.');
                }

                // Deduct from wallet
                $stmt = $pdo->prepare("UPDATE instructor_wallet SET balance = balance - ?, last_withdraw = NOW() WHERE instructor_id = ?");
                $stmt->execute([$amount, $instructor_id]);

                // Record transaction
                $stmt = $pdo->prepare("INSERT INTO instructor_wallet_transactions (instructor_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
                $stmt->execute([$instructor_id, $amount, 'Withdrawal payout processed']);

                // Update request status
                $stmt = $pdo->prepare("UPDATE instructor_withdraw_requests SET status = 'paid', processed_at = NOW() WHERE id = ?");
                $stmt->execute([$request_id]);
                
                $pdo->commit();
                $success = 'Payout processed successfully.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'transfer_money') {
        $instructor_id = (int)($_POST['instructor_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? 'Admin transfer');
        
        if ($instructor_id <= 0 || $amount <= 0) {
            $errors[] = 'Invalid instructor or amount.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Update wallet
                $stmt = $pdo->prepare("UPDATE instructor_wallet SET balance = balance + ?, total_earned = total_earned + ? WHERE instructor_id = ?");
                $stmt->execute([$amount, $amount, $instructor_id]);
                
                // Record transaction
                $stmt = $pdo->prepare("INSERT INTO instructor_wallet_transactions (instructor_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                $stmt->execute([$instructor_id, $amount, $description]);
                
                $pdo->commit();
                $success = 'Money transferred successfully.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Get pending withdrawals
$pending_withdrawals = [];
try {
    $stmt = $pdo->query("
        SELECT iwr.*, i.name AS instructor_name, u.email AS instructor_email, iw.wallet_number, iw.balance
        FROM instructor_withdraw_requests iwr
        JOIN instructors i ON iwr.instructor_id = i.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN instructor_wallet iw ON iwr.instructor_id = iw.instructor_id
        WHERE iwr.status = 'pending'
        ORDER BY iwr.created_at DESC
    ");
    $pending_withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
}

// Get approved withdrawals (ready for payout)
$approved_withdrawals = [];
try {
    $stmt = $pdo->query("
        SELECT iwr.*, i.name AS instructor_name, u.email AS instructor_email, iw.wallet_number, iw.balance
        FROM instructor_withdraw_requests iwr
        JOIN instructors i ON iwr.instructor_id = i.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN instructor_wallet iw ON iwr.instructor_id = iw.instructor_id
        WHERE iwr.status = 'approved'
        ORDER BY iwr.created_at DESC
    ");
    $approved_withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
}

// Get all instructors for transfer
$instructors = [];
try {
    $stmt = $pdo->query("
        SELECT i.id, i.name, u.email, iw.wallet_number, iw.balance, iw.total_earned
        FROM instructors i
        JOIN users u ON i.user_id = u.id
        LEFT JOIN instructor_wallet iw ON i.id = iw.instructor_id
        ORDER BY i.name
    ");
    $instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
}

// Get all transactions
$all_transactions = [];
try {
    $transactions_sql = "
        SELECT iwt.*, i.name AS instructor_name, u.email AS instructor_email
        FROM instructor_wallet_transactions iwt
        JOIN instructors i ON iwt.instructor_id = i.id
        JOIN users u ON i.user_id = u.id
    ";
    
    if ($selected_instructor_id > 0 && $tab === 'transactions') {
        $transactions_sql .= " WHERE iwt.instructor_id = :instructor_id";
        $stmt = $pdo->prepare($transactions_sql . " ORDER BY iwt.created_at DESC LIMIT 100");
        $stmt->execute(['instructor_id' => $selected_instructor_id]);
    } else {
        $stmt = $pdo->query($transactions_sql . " ORDER BY iwt.created_at DESC LIMIT 100");
    }
    
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
}

$title = 'Instructor Finances - Admin';
include '../includes/admin_header.php';
?>

<style>
.tab-content {
    min-height: 400px;
}
.status-badge {
    font-size: 0.85rem;
    padding: 0.35rem 0.75rem;
}
</style>

<main class="flex-grow-1 p-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-money-bill-wave me-2"></i>Instructor Financial Management</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'withdrawals' ? 'active' : '' ?>" href="?tab=withdrawals">
                <i class="fas fa-money-check-alt me-1"></i> Pending Withdrawals
                <?php if (count($pending_withdrawals) > 0): ?>
                    <span class="badge bg-danger ms-2"><?= count($pending_withdrawals) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'payout' ? 'active' : '' ?>" href="?tab=payout">
                <i class="fas fa-hand-holding-usd me-1"></i> Approved (Ready for Payout)
                <?php if (count($approved_withdrawals) > 0): ?>
                    <span class="badge bg-success ms-2"><?= count($approved_withdrawals) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'transfer' ? 'active' : '' ?>" href="?tab=transfer">
                <i class="fas fa-paper-plane me-1"></i> Transfer Money
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'transactions' ? 'active' : '' ?>" href="?tab=transactions">
                <i class="fas fa-exchange-alt me-1"></i> All Transactions
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Pending Withdrawals Tab -->
        <?php if ($tab === 'withdrawals'): ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Withdrawal Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_withdrawals)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">No pending withdrawal requests.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Instructor</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Details</th>
                                        <th>Wallet Balance</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_withdrawals as $req): ?>
                                        <tr>
                                            <td>#<?= $req['id'] ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($req['instructor_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($req['instructor_email']) ?></small>
                                            </td>
                                            <td class="fw-bold text-primary">TZS <?= number_format((float)$req['amount'], 0) ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= htmlspecialchars(ucfirst($req['method'] ?? 'N/A')) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($req['method'] === 'mobile_money'): ?>
                                                    <small>Phone: <?= htmlspecialchars($req['phone_number'] ?? 'N/A') ?></small>
                                                <?php elseif ($req['method'] === 'bank'): ?>
                                                    <small>
                                                        Bank: <?= htmlspecialchars($req['bank_name'] ?? 'N/A') ?><br>
                                                        Account: <?= htmlspecialchars($req['account_number'] ?? 'N/A') ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>TZS <?= number_format((float)($req['balance'] ?? 0), 0) ?></td>
                                            <td>
                                                <small><?= date('M j, Y H:i', strtotime($req['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Approve this withdrawal request?');">
                                                    <input type="hidden" name="action" value="approve_withdrawal">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check me-1"></i> Approve
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $req['id'] ?>">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                                
                                                <!-- Reject Modal -->
                                                <div class="modal fade" id="rejectModal<?= $req['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Reject Withdrawal Request</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="post">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="action" value="reject_withdrawal">
                                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Reason (Optional)</label>
                                                                        <textarea name="notes" class="form-control" rows="3"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">Reject Request</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
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

        <!-- Approved Withdrawals (Payout) Tab -->
        <?php elseif ($tab === 'payout'): ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i>Approved Withdrawals (Ready for Payout)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($approved_withdrawals)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No approved withdrawals ready for payout.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Instructor</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Details</th>
                                        <th>Wallet Balance</th>
                                        <th>Approved</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_withdrawals as $req): ?>
                                        <tr>
                                            <td>#<?= $req['id'] ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($req['instructor_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($req['instructor_email']) ?></small>
                                            </td>
                                            <td class="fw-bold text-success">TZS <?= number_format((float)$req['amount'], 0) ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= htmlspecialchars(ucfirst($req['method'] ?? 'N/A')) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($req['method'] === 'mobile_money'): ?>
                                                    <small>Phone: <?= htmlspecialchars($req['phone_number'] ?? 'N/A') ?></small>
                                                <?php elseif ($req['method'] === 'bank'): ?>
                                                    <small>
                                                        Bank: <?= htmlspecialchars($req['bank_name'] ?? 'N/A') ?><br>
                                                        Account: <?= htmlspecialchars($req['account_number'] ?? 'N/A') ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>TZS <?= number_format((float)($req['balance'] ?? 0), 0) ?></td>
                                            <td>
                                                <small><?= date('M j, Y H:i', strtotime($req['processed_at'] ?? $req['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Process payout? This will deduct the amount from the instructor wallet.');">
                                                    <input type="hidden" name="action" value="process_payout">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check-circle me-1"></i> Process Payout
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <!-- Transfer Money Tab -->
        <?php elseif ($tab === 'transfer'): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Transfer Money to Instructor</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="transfer_money">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Select Instructor</label>
                                    <select name="instructor_id" class="form-select" required>
                                        <option value="">Choose an instructor...</option>
                                        <?php foreach ($instructors as $inst): ?>
                                            <option value="<?= $inst['id'] ?>" <?= ($selected_instructor_id > 0 && $inst['id'] == $selected_instructor_id) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($inst['name']) ?> 
                                                (<?= htmlspecialchars($inst['email']) ?>)
                                                - Balance: TZS <?= number_format((float)($inst['balance'] ?? 0), 0) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Amount (TZS)</label>
                                    <input type="number" name="amount" class="form-control" min="1" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Description</label>
                                    <input type="text" name="description" class="form-control" value="Admin transfer" placeholder="Transaction description">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Transfer Money
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Instructor Wallets</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Instructor</th>
                                            <th>Wallet</th>
                                            <th>Balance</th>
                                            <th>Total Earned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($instructors as $inst): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($inst['name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($inst['email']) ?></small>
                                                </td>
                                                <td><code><?= htmlspecialchars($inst['wallet_number'] ?? 'N/A') ?></code></td>
                                                <td class="fw-bold text-primary">TZS <?= number_format((float)($inst['balance'] ?? 0), 0) ?></td>
                                                <td class="text-success">TZS <?= number_format((float)($inst['total_earned'] ?? 0), 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- All Transactions Tab -->
        <?php elseif ($tab === 'transactions'): ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>All Transactions</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-light" onclick="exportTransactions()">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($all_transactions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No transactions found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Instructor</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_transactions as $txn): ?>
                                        <tr>
                                            <td>#<?= $txn['id'] ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($txn['instructor_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($txn['instructor_email']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($txn['type'] === 'credit'): ?>
                                                    <span class="badge bg-success">Credit</span>
                                                <?php elseif ($txn['type'] === 'debit'): ?>
                                                    <span class="badge bg-danger">Debit</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($txn['type'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold <?= $txn['type'] === 'credit' ? 'text-success' : 'text-danger' ?>">
                                                <?= $txn['type'] === 'credit' ? '+' : '-' ?> TZS <?= number_format((float)$txn['amount'], 0) ?>
                                            </td>
                                            <td><?= htmlspecialchars($txn['description'] ?? '') ?></td>
                                            <td>
                                                <small><?= date('M j, Y H:i', strtotime($txn['created_at'])) ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
function exportTransactions() {
    alert('Export feature coming soon!');
}
</script>

<?php include '../includes/admin_footer.php'; ?>

