<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

checkInstructorAuth();

$instructor_user_id = $_SESSION['user_id'];

// Fetch instructor and wallet details
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
    $to_wallet = trim($_POST['to_wallet'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);

    if($to_wallet==='' || $amount<=0){
        $err = 'Destination wallet and positive amount are required.';
    }elseif($amount>$wallet['balance']){
        $err = 'Insufficient balance.';
    }else{
        try{
            $pdo->beginTransaction();
            // lock sender row
            $stmt = $pdo->prepare("SELECT balance FROM instructor_wallet WHERE instructor_id = ? FOR UPDATE");
            $stmt->execute([$instructor_id]);
            $sender_balance = (float)$stmt->fetchColumn();
            if($amount>$sender_balance){
                throw new Exception('Insufficient balance.');
            }
            // find receiver
            $stmt = $pdo->prepare("SELECT instructor_id FROM instructor_wallet WHERE wallet_number = ? FOR UPDATE");
            $stmt->execute([$to_wallet]);
            $receiver_id = (int)($stmt->fetchColumn() ?: 0);
            if(!$receiver_id){
                throw new Exception('Destination wallet not found.');
            }
            // update sender balance
            $stmt = $pdo->prepare("UPDATE instructor_wallet SET balance = balance - ? WHERE instructor_id = ?");
            $stmt->execute([$amount,$instructor_id]);
            // update receiver balance
            $stmt = $pdo->prepare("UPDATE instructor_wallet SET balance = balance + ? WHERE instructor_id = ?");
            $stmt->execute([$amount,$receiver_id]);
            // insert transactions
            $stmt = $pdo->prepare("INSERT INTO instructor_wallet_transactions (instructor_id,type,amount,description) VALUES (?,?,?,?)");
            $stmt->execute([$instructor_id,'debit',$amount,'Sent to '.$to_wallet]);
            $stmt->execute([$receiver_id,'credit',$amount,'Received from '.$wallet['wallet_number']]);
            $pdo->commit();
            $success = 'Transfer successful!';
            // refresh balance
            $wallet['balance'] -= $amount;
        }catch(Throwable $e){
            $pdo->rollBack();
            $err = $e->getMessage();
        }
    }
}

$title = 'Send Money';
include 'user_header.php';
include 'instructor_sidebar.php';
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <h1 class="h3 mb-4">Send Money</h1>
    <?php if($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php elseif($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Your Wallet Number</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($wallet['wallet_number']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Current Balance (TZS)</label>
                    <input type="text" class="form-control" value="<?= number_format($wallet['balance']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">To Wallet Number</label>
                    <input type="text" name="to_wallet" class="form-control" placeholder="AKW-XXXX-XXXX" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Amount (TZS)</label>
                    <input type="number" name="amount" class="form-control" min="100" step="1" max="<?= (int)$wallet['balance'] ?>" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Send Money</button>
                    <a href="instructor_earnings.php" class="btn btn-link">Back to Wallet</a>
                </div>
            </form>
        </div>
    </div>
</main>
<?php include 'user_footer.php'; ?>
