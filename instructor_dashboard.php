<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

checkInstructorAuth();

$instructor_user_id = $_SESSION['user_id'];

// Resolve instructor record
$stmt = $pdo->prepare("SELECT i.* FROM instructors i WHERE i.user_id = ?");
$stmt->execute([$instructor_user_id]);
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);
$instructor_id = $instructor['id'] ?? null;

// Initialize stats
$total_courses = 0;
$total_students = 0;
$total_earnings = 0.0;
$wallet_balance = 0.0;
$pending_withdrawals = 0;
$recent_courses = [];
$monthly_earnings = [];
$course_performance = [];
$recent_transactions = [];
$student_enrollments = [];

if ($instructor_id) {
    // Total courses
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a.course_id) AS cnt
                           FROM instructor_course_assignments a
                           WHERE a.instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_courses = (int)($stmt->fetchColumn() ?: 0);

    // Total students
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT e.user_id) AS cnt
                           FROM instructor_course_assignments a
                           JOIN enrollments e ON a.course_id = e.course_id
                           WHERE a.instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_students = (int)($stmt->fetchColumn() ?: 0);

    // Wallet info
    $stmt = $pdo->prepare("SELECT balance, total_earned FROM instructor_wallet WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['balance' => 0, 'total_earned' => 0];
    $wallet_balance = (float)($wallet['balance'] ?? 0);
    $total_earnings = (float)($wallet['total_earned'] ?? 0);
    
    // Count pending withdrawals
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM instructor_withdraw_requests WHERE instructor_id = ? AND status = 'pending'");
    $stmt->execute([$instructor_id]);
    $pending_withdrawals = (int)($stmt->fetchColumn() ?: 0);

    // Recent courses
    $stmt = $pdo->prepare("SELECT c.*, COUNT(DISTINCT e.user_id) AS students_count
                           FROM instructor_course_assignments a
                           JOIN courses c ON a.course_id = c.id
                           LEFT JOIN enrollments e ON e.course_id = c.id
                           WHERE a.instructor_id = ?
                           GROUP BY c.id
                           ORDER BY c.created_at DESC LIMIT 5");
    $stmt->execute([$instructor_id]);
    $recent_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly earnings (last 6 months)
    $stmt = $pdo->prepare("SELECT 
                            DATE_FORMAT(created_at, '%Y-%m') AS month,
                            SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) AS earnings
                           FROM instructor_wallet_transactions
                           WHERE instructor_id = ? 
                             AND type = 'credit'
                             AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                           GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                           ORDER BY month ASC");
    $stmt->execute([$instructor_id]);
    $monthly_earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Course performance (top 5 by enrollment)
    $stmt = $pdo->prepare("SELECT 
                            c.title,
                            COUNT(DISTINCT e.user_id) AS enrollments,
                            COUNT(DISTINCT e.id) AS total_enrollments
                           FROM instructor_course_assignments a
                           JOIN courses c ON a.course_id = c.id
                           LEFT JOIN enrollments e ON e.course_id = c.id
                           WHERE a.instructor_id = ?
                           GROUP BY c.id, c.title
                           ORDER BY enrollments DESC
                           LIMIT 5");
    $stmt->execute([$instructor_id]);
    $course_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent transactions
    $stmt = $pdo->prepare("SELECT type, amount, description, created_at 
                           FROM instructor_wallet_transactions 
                           WHERE instructor_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT 10");
    $stmt->execute([$instructor_id]);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Student enrollments by month (last 6 months)
    $stmt = $pdo->prepare("SELECT 
                            DATE_FORMAT(e.created_at, '%Y-%m') AS month,
                            COUNT(DISTINCT e.user_id) AS new_students
                           FROM instructor_course_assignments a
                           JOIN enrollments e ON a.course_id = e.course_id
                           WHERE a.instructor_id = ?
                             AND e.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                           GROUP BY DATE_FORMAT(e.created_at, '%Y-%m')
                           ORDER BY month ASC");
    $stmt->execute([$instructor_id]);
    $student_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$title = 'Instructor Dashboard - AK23 App';
include 'user_header.php';
include 'instructor_sidebar.php';
?>
<style>
.stats-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: none;
    border-radius: 12px;
}
.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
}
.stats-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.report-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.chart-container {
    position: relative;
    height: 300px;
}
/* Main content adjustment for fixed sidebar */
@media (min-width: 768px) {
    main {
        margin-left: 200px;
        width: calc(100% - 200px);
    }
}
</style>
<main class="px-md-4 py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
        <div>
            <h1 class="h2 mb-1" style="color: #2c3e50;">Dashboard</h1>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'Instructor') ?>!</p>
        </div>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="instructor_earnings.php#send-money" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-paper-plane me-1"></i> Send Money
                </a>
                <a href="instructor_earnings.php#withdraw" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-money-bill-transfer me-1"></i> Withdraw
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <!-- Wallet Balance -->
        <div class="col-md-3 col-sm-6">
            <div class="card stats-card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-2 text-uppercase small fw-bold">Wallet Balance</h6>
                            <h3 class="mb-0 fw-bold" style="color: #2563eb;">TZS <?= number_format($wallet_balance, 0) ?></h3>
                            <div class="small mt-2 text-muted">Available to withdraw</div>
                        </div>
                        <div class="stats-icon bg-primary bg-opacity-10">
                            <i class="fas fa-wallet fa-2x text-primary"></i>
                        </div>
                    </div>
                    <a href="instructor_earnings.php" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <!-- Total Earnings -->
        <div class="col-md-3 col-sm-6">
            <div class="card stats-card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-2 text-uppercase small fw-bold">Total Earnings</h6>
                            <h3 class="mb-0 fw-bold" style="color: #059669;">TZS <?= number_format($total_earnings, 0) ?></h3>
                            <div class="small mt-2 text-muted">Lifetime earnings</div>
                        </div>
                        <div class="stats-icon bg-success bg-opacity-10">
                            <i class="fas fa-chart-line fa-2x text-success"></i>
                        </div>
                    </div>
                    <a href="instructor_earnings.php#transactions" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <!-- Pending Withdrawals -->
        <div class="col-md-3 col-sm-6">
            <div class="card stats-card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-2 text-uppercase small fw-bold">Pending Withdrawals</h6>
                            <h3 class="mb-0 fw-bold" style="color: #d97706;"><?= $pending_withdrawals ?></h3>
                            <div class="small mt-2 text-muted">Awaiting approval</div>
                        </div>
                        <div class="stats-icon bg-warning bg-opacity-10">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                    <a href="instructor_earnings.php#withdraw" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <!-- Total Students -->
        <div class="col-md-3 col-sm-6">
            <div class="card stats-card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-2 text-uppercase small fw-bold">Total Students</h6>
                            <h3 class="mb-0 fw-bold" style="color: #0284c7;"><?= number_format($total_students) ?></h3>
                            <div class="small mt-2 text-muted">Across all courses</div>
                        </div>
                        <div class="stats-icon bg-info bg-opacity-10">
                            <i class="fas fa-users fa-2x text-info"></i>
                        </div>
                    </div>
                    <a href="instructor_students.php" class="stretched-link"></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="instructor_earnings.php#send-money" class="btn btn-outline-primary d-flex align-items-center">
                            <i class="fas fa-paper-plane me-2"></i> Send Money
                        </a>
                        <a href="instructor_earnings.php#withdraw" class="btn btn-outline-success d-flex align-items-center">
                            <i class="fas fa-money-bill-transfer me-2"></i> Withdraw Funds
                        </a>
                        <a href="instructor_earnings.php#transactions" class="btn btn-outline-secondary d-flex align-items-center">
                            <i class="fas fa-list me-2"></i> View Transactions
                        </a>
                        <a href="instructor_courses.php" class="btn btn-outline-info d-flex align-items-center">
                            <i class="fas fa-book-open me-2"></i> My Courses
                        </a>
                        <a href="instructor_students.php" class="btn btn-outline-dark d-flex align-items-center">
                            <i class="fas fa-users me-2"></i> My Students
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Section -->
    <div class="row g-4 mb-4">
        <!-- Earnings Report -->
        <div class="col-lg-8">
            <div class="card report-card">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold" style="color: #1f2937;">
                            <i class="fas fa-chart-area me-2 text-primary"></i>Earnings Report
                        </h5>
                        <a href="instructor_earnings.php#transactions" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="earningsChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="small text-muted">This Month</div>
                                <div class="fw-bold text-primary">
                                    TZS <?= number_format(array_sum(array_column($monthly_earnings, 'earnings')), 0) ?>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Last 6 Months</div>
                                <div class="fw-bold text-success">
                                    TZS <?= number_format(array_sum(array_column($monthly_earnings, 'earnings')), 0) ?>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Total</div>
                                <div class="fw-bold text-dark">
                                    TZS <?= number_format($total_earnings, 0) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Performance -->
        <div class="col-lg-4">
            <div class="card report-card">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <h5 class="mb-0 fw-bold" style="color: #1f2937;">
                        <i class="fas fa-trophy me-2 text-warning"></i>Top Courses
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($course_performance)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-book fa-3x mb-3 opacity-25"></i>
                            <p>No courses yet</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($course_performance as $idx => $course): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold mb-1"><?= htmlspecialchars($course['title']) ?></div>
                                            <div class="small text-muted">
                                                <i class="fas fa-users me-1"></i>
                                                <?= (int)($course['enrollments'] ?? 0) ?> students
                                            </div>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">#<?= $idx + 1 ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <a href="instructor_courses.php" class="btn btn-sm btn-outline-primary w-100">View All Courses</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Enrollment & Recent Transactions -->
    <div class="row g-4 mb-4">
        <!-- Student Enrollment Report -->
        <div class="col-lg-6">
            <div class="card report-card">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold" style="color: #1f2937;">
                            <i class="fas fa-user-graduate me-2 text-info"></i>Student Enrollments
                        </h5>
                        <a href="instructor_students.php" class="btn btn-sm btn-outline-info">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="enrollmentChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <div class="row">
                            <div class="col-6">
                                <div class="small text-muted">Total Students</div>
                                <div class="fw-bold text-info fs-4"><?= number_format($total_students) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted">Total Courses</div>
                                <div class="fw-bold text-dark fs-4"><?= $total_courses ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-lg-6">
            <div class="card report-card">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold" style="color: #1f2937;">
                            <i class="fas fa-exchange-alt me-2 text-secondary"></i>Recent Transactions
                        </h5>
                        <a href="instructor_earnings.php#transactions" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_transactions)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-exchange-alt fa-3x mb-3 opacity-25"></i>
                            <p>No transactions yet</p>
                            <a href="instructor_earnings.php#send-money" class="btn btn-sm btn-primary">Send Money</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Type</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end pe-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recent_transactions, 0, 5) as $tr): 
                                        $isCredit = ($tr['type'] ?? '') === 'credit';
                                        $isDebit = ($tr['type'] ?? '') === 'debit';
                                    ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="d-flex align-items-center">
                                                    <?php if ($isCredit): ?>
                                                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-2">
                                                            <i class="fas fa-arrow-down"></i>
                                                        </div>
                                                    <?php elseif ($isDebit): ?>
                                                        <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2 me-2">
                                                            <i class="fas fa-arrow-up"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-2 me-2">
                                                            <i class="fas fa-exchange-alt"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="small fw-medium"><?= htmlspecialchars(ucfirst($tr['type'] ?? 'Transaction')) ?></div>
                                                        <div class="text-muted" style="font-size: 0.75rem;">
                                                            <?= htmlspecialchars(substr($tr['description'] ?? '', 0, 30)) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end fw-medium <?= $isCredit ? 'text-success' : ($isDebit ? 'text-danger' : '') ?>">
                                                <?= $isCredit ? '+' : ($isDebit ? '-' : '') ?> TZS <?= number_format((float)($tr['amount'] ?? 0), 0) ?>
                                            </td>
                                            <td class="text-end pe-3 text-muted small">
                                                <?= date('M j, Y', strtotime($tr['created_at'] ?? '')) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Courses -->
    <div class="row">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header bg-white border-0 pt-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold" style="color: #1f2937;">
                            <i class="fas fa-book-open me-2 text-primary"></i>Recent Courses
                        </h5>
                        <a href="instructor_courses.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_courses)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-book fa-3x mb-3 opacity-25"></i>
                            <p>No courses assigned yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Students</th>
                                        <th>Visibility</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_courses as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($c['title']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= (int)($c['students_count'] ?? 0) ?> students</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= ($c['visibility'] ?? '') === 'public' ? 'success' : 'secondary' ?>">
                                                    <?= htmlspecialchars($c['visibility'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small">
                                                <?= date('M j, Y', strtotime($c['created_at'] ?? '')) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Earnings Chart
const earningsCtx = document.getElementById('earningsChart');
if (earningsCtx) {
    const earningsData = <?= json_encode($monthly_earnings) ?>;
    const labels = earningsData.map(d => {
        const date = new Date(d.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    const amounts = earningsData.map(d => parseFloat(d.earnings || 0));

    new Chart(earningsCtx, {
        type: 'line',
        data: {
            labels: labels.length > 0 ? labels : ['No data'],
            datasets: [{
                label: 'Earnings (TZS)',
                data: amounts.length > 0 ? amounts : [0],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'TZS ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Enrollment Chart
const enrollmentCtx = document.getElementById('enrollmentChart');
if (enrollmentCtx) {
    const enrollmentData = <?= json_encode($student_enrollments) ?>;
    const labels = enrollmentData.map(d => {
        const date = new Date(d.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    const counts = enrollmentData.map(d => parseInt(d.new_students || 0));

    new Chart(enrollmentCtx, {
        type: 'bar',
        data: {
            labels: labels.length > 0 ? labels : ['No data'],
            datasets: [{
                label: 'New Students',
                data: counts.length > 0 ? counts : [0],
                backgroundColor: '#0284c7',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
</script>
<?php include 'user_footer.php'; ?>
