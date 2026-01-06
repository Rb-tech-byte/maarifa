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
$instructor_id = (int)($instructor['id'] ?? 0);

$wallet = ['balance'=>0,'wallet_number'=>''];
$stmt = $pdo->prepare("SELECT balance, wallet_number FROM instructor_wallet WHERE instructor_id = ?");
$stmt->execute([$instructor_id]);
if($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $wallet['balance'] = (float)$row['balance'];
    $wallet['wallet_number'] = $row['wallet_number'];
}

$err = '';
$success = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $bank = trim($_POST['bank_name'] ?? '');
    $account = trim($_POST['account_number'] ?? '');

    if($amount<=0 || $amount>$wallet['balance']){
        $err = 'Enter a valid amount within your balance.';
    }elseif(!in_array($method,['mobile_money','bank'])){
        $err = 'Select a valid withdrawal method.';
    }elseif($method==='mobile_money' && $phone===''){
        $err = 'Phone number required for mobile money.';
    }elseif($method==='bank' && ($bank==='' || $account==='')){
        $err = 'Bank name and account number required.';
    }else{
        $stmt = $pdo->prepare("INSERT INTO instructor_withdraw_requests (instructor_id,amount,method,phone_number,bank_name,account_number) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$instructor_id,$amount,$method,$phone,$bank,$account]);
        $success = 'Withdrawal request submitted! Our team will process it shortly.';
        // Optionally reserve amount but here we just wait for admin approval
    }
}

$title = 'Withdraw Funds';
include 'user_header.php';
include 'instructor_sidebar.php';
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <h1 class="h3 mb-4">Withdraw Funds</h1>
    <?php if($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php elseif($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Current Balance (TZS)</label>
                    <input type="text" class="form-control" value="<?= number_format($wallet['balance']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Amount to Withdraw (TZS)</label>
                    <input type="number" name="amount" class="form-control" min="100" step="1" max="<?= (int)$wallet['balance'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Method</label>
                    <select name="method" class="form-select" required>
                        <option value="">Select method</option>
                        <option value="mobile_money">Mobile Money (Phone)</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                <div class="col-md-6 d-none" id="phoneField">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" placeholder="e.g. +2557XXXXXXXX">
                </div>
                <div class="col-md-6 d-none" id="bankField">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="e.g. CRDB">
                </div>
                <div class="col-md-6 d-none" id="accountField">
                    <label class="form-label">Account Number</label>
                    <input type="text" name="account_number" class="form-control" placeholder="e.g. 0123456789">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success">Submit Withdraw Request</button>
                    <a href="instructor_earnings.php" class="btn btn-link">Back to Wallet</a>
                </div>
            </form>
        </div>
    </div>
</main>
<script>
// simple JS to toggle extra fields
const methodSelect = document.querySelector('select[name="method"]');
const phoneField = document.getElementById('phoneField');
const bankField = document.getElementById('bankField');
const accountField = document.getElementById('accountField');
function toggleFields(){
    const val = methodSelect.value;
    phoneField.classList.add('d-none');
    bankField.classList.add('d-none');
    accountField.classList.add('d-none');
    if(val==='mobile_money') phoneField.classList.remove('d-none');
    if(val==='bank') { bankField.classList.remove('d-none'); accountField.classList.remove('d-none'); }
}
methodSelect.addEventListener('change',toggleFields);
</script>
<?php include 'user_footer.php'; ?>
