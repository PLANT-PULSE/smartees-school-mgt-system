<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Financial Dashboard';
$pdo = getDb();

// Initialize services
$feeService = new FeeService($pdo);
$studentService = new StudentService($pdo);

$action = $_GET['action'] ?? 'overview';

// Get all classes
$stmt = $pdo->query('SELECT id, name FROM classes ORDER BY name');
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedStudent = intval($_GET['student_id'] ?? 0);

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'record_payment') {
    $studentId = intval($_POST['student_id']);
    $classFeeId = intval($_POST['class_fee_id']);
    $amount = floatval($_POST['amount']);
    $paymentMethod = trim($_POST['payment_method']);
    $receiptNumber = trim($_POST['receipt_number'] ?? '');

    if ($amount > 0) {
        $feeService->recordPayment($studentId, $classFeeId, $amount, $paymentMethod, $receiptNumber ?: null);
        flash('success', 'Payment recorded successfully.');
        redirect("financial.php?action=overview");
    } else {
        flash('error', 'Invalid payment amount.');
        redirect("financial.php?action=overview");
    }
}

// Prepare data based on action
$stats = [
    'total_fees' => 0,
    'total_paid' => 0,
    'total_balance' => 0,
    'total_overdue' => 0
];

$classStudents = [];
$studentFees = [];
$allPayments = [];

if ($selectedClass > 0) {
    // Get students in class
    $classStudents = $studentService->getStudentsByClass($selectedClass);

    // Calculate statistics
    foreach ($classStudents as $student) {
        $fees = $feeService->getStudentFeesOverview($student['id']);
        foreach ($fees as $fee) {
            $stats['total_fees'] += $fee['amount'];
            $stats['total_paid'] += $fee['total_paid'];
            $stats['total_balance'] += $fee['balance'];
            if ($fee['is_overdue']) {
                $stats['total_overdue'] += $fee['balance'];
            }
        }
    }

    if ($selectedStudent > 0) {
        $studentFees = $feeService->getStudentFeesOverview($selectedStudent);
        $allPayments = $feeService->getPaymentHistory($selectedStudent);
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container-fluid mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-chart-line me-2"></i>Financial Dashboard
    </h1>

    <?php if ($msg = flash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Sidebar: Selection -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-graduation-cap me-2"></i>Select Class
                    </h6>
                </div>
                <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                    <a href="financial.php?action=overview&class_id=0" 
                       class="list-group-item list-group-item-action <?= $selectedClass == 0 ? 'active' : '' ?>">
                        <i class="fas fa-list me-2"></i>All Classes
                    </a>
                    <?php foreach ($classes as $c): ?>
                    <a href="financial.php?action=overview&class_id=<?= $c['id'] ?>" 
                       class="list-group-item list-group-item-action <?= $c['id'] == $selectedClass ? 'active' : '' ?>">
                        <i class="fas fa-book me-2"></i><?= htmlspecialchars($c['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($selectedClass > 0 && count($classStudents) > 0): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-2"></i>Students
                    </h6>
                </div>
                <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($classStudents as $s): ?>
                    <a href="?action=overview&class_id=<?= $selectedClass ?>&student_id=<?= $s['id'] ?>" 
                       class="list-group-item list-group-item-action <?= $s['id'] == $selectedStudent ? 'active' : '' ?>">
                        <small><?= htmlspecialchars($s['name']) ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <?php if ($selectedClass == 0): ?>
            <!-- Overview for All Classes -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h6 class="text-muted">Total Students</h6>
                            <h3 class="text-success">
                                <?php 
                                    $stmt = $pdo->query('SELECT COUNT(*) FROM students');
                                    echo $stmt->fetchColumn();
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm" style="border-left: 4px solid #0d6efd;">
                        <div class="card-body">
                            <h6 class="text-muted">Total Classes</h6>
                            <h3 class="text-primary"><?= count($classes) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-layer-group me-2"></i>All Classes Fee Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Students</th>
                                    <th>Total Fees</th>
                                    <th>Total Paid</th>
                                    <th>Balance</th>
                                    <th>Overdue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $grandTotalFees = 0;
                                    $grandTotalPaid = 0;
                                    $grandTotalBalance = 0;
                                    $grandTotalOverdue = 0;
                                    
                                    foreach ($classes as $class): 
                                        $classStudents = $studentService->getStudentsByClass($class['id']);
                                        $classTotalFees = 0;
                                        $classTotalPaid = 0;
                                        $classTotalBalance = 0;
                                        $classTotalOverdue = 0;

                                        foreach ($classStudents as $student) {
                                            $fees = $feeService->getStudentFeesOverview($student['id']);
                                            foreach ($fees as $fee) {
                                                $classTotalFees += $fee['amount'];
                                                $classTotalPaid += $fee['total_paid'];
                                                $classTotalBalance += $fee['balance'];
                                                if ($fee['is_overdue']) {
                                                    $classTotalOverdue += $fee['balance'];
                                                }
                                            }
                                        }

                                        $grandTotalFees += $classTotalFees;
                                        $grandTotalPaid += $classTotalPaid;
                                        $grandTotalBalance += $classTotalBalance;
                                        $grandTotalOverdue += $classTotalOverdue;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($class['name']) ?></strong></td>
                                    <td><?= count($classStudents) ?></td>
                                    <td>₦<?= number_format($classTotalFees, 2) ?></td>
                                    <td class="text-success">₦<?= number_format($classTotalPaid, 2) ?></td>
                                    <td class="text-warning">₦<?= number_format($classTotalBalance, 2) ?></td>
                                    <td><span class="badge bg-danger">₦<?= number_format($classTotalOverdue, 2) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>TOTAL</th>
                                    <th><?= array_sum(array_map(fn($c) => count($studentService->getStudentsByClass($c['id'])), $classes)) ?></th>
                                    <th>₦<?= number_format($grandTotalFees, 2) ?></th>
                                    <th class="text-success">₦<?= number_format($grandTotalPaid, 2) ?></th>
                                    <th class="text-warning">₦<?= number_format($grandTotalBalance, 2) ?></th>
                                    <th><span class="badge bg-danger">₦<?= number_format($grandTotalOverdue, 2) ?></span></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <?php elseif ($selectedClass > 0 && $selectedStudent == 0): ?>
            <!-- Class Fee Overview -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm text-center" style="border-left: 4px solid #0d6efd;">
                        <div class="card-body">
                            <i class="fas fa-money-bill fa-2x text-primary mb-2"></i>
                            <h6 class="text-muted">Total Fees</h6>
                            <h4 class="text-primary">₦<?= number_format($stats['total_fees'], 2) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm text-center" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h6 class="text-muted">Paid</h6>
                            <h4 class="text-success">₦<?= number_format($stats['total_paid'], 2) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm text-center" style="border-left: 4px solid #ffc107;">
                        <div class="card-body">
                            <i class="fas fa-hourglass-half fa-2x text-warning mb-2"></i>
                            <h6 class="text-muted">Pending</h6>
                            <h4 class="text-warning">₦<?= number_format($stats['total_balance'], 2) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm text-center" style="border-left: 4px solid #dc3545;">
                        <div class="card-body">
                            <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                            <h6 class="text-muted">Overdue</h6>
                            <h4 class="text-danger">₦<?= number_format($stats['total_overdue'], 2) ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Class Students Fees Table -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Student Fee Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Total Fees</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classStudents as $student): 
                                    $fees = $feeService->getStudentFeesOverview($student['id']);
                                    $totalFees = array_sum(array_column($fees, 'amount'));
                                    $totalPaid = array_sum(array_column($fees, 'total_paid'));
                                    $totalBalance = $totalFees - $totalPaid;
                                    $hasOverdue = count(array_filter($fees, fn($f) => $f['is_overdue'])) > 0;
                                ?>
                                <tr class="<?= $hasOverdue ? 'table-danger opacity-50' : '' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($student['name']) ?></strong>
                                        <?php if ($hasOverdue): ?>
                                        <br><small class="badge bg-danger">Overdue</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>₦<?= number_format($totalFees, 2) ?></td>
                                    <td class="text-success">₦<?= number_format($totalPaid, 2) ?></td>
                                    <td class="text-warning">
                                        <strong>₦<?= number_format($totalBalance, 2) ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($totalBalance == 0) {
                                                echo '<span class="badge bg-success">Paid</span>';
                                            } elseif ($hasOverdue) {
                                                echo '<span class="badge bg-danger">Overdue</span>';
                                            } else {
                                                echo '<span class="badge bg-warning">Pending</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="?action=overview&class_id=<?= $selectedClass ?>&student_id=<?= $student['id'] ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php elseif ($selectedStudent > 0): ?>
            <!-- Student Fee Details -->
            <?php $student = $studentService->getById($selectedStudent); ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5>
                                <i class="fas fa-user me-2"></i><?= htmlspecialchars($student['name']) ?>
                            </h5>
                            <p class="text-muted mb-0">
                                Contact: <?= htmlspecialchars($student['contact'] ?? 'N/A') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <?php 
                    $totalFees = array_sum(array_column($studentFees, 'amount'));
                    $totalPaid = array_sum(array_column($studentFees, 'total_paid'));
                    $totalBalance = $totalFees - $totalPaid;
                ?>
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h6 class="text-muted">Total Fees</h6>
                            <h3 class="text-primary">₦<?= number_format($totalFees, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h6 class="text-muted">Paid</h6>
                            <h3 class="text-success">₦<?= number_format($totalPaid, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h6 class="text-muted">Balance</h6>
                            <h3 class="text-warning">₦<?= number_format($totalBalance, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h6 class="text-muted">Progress</h6>
                            <h3 class="text-info"><?= $totalFees > 0 ? round(($totalPaid / $totalFees) * 100) : 0 ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" style="width: <?= $totalFees > 0 ? round(($totalPaid / $totalFees) * 100) : 0 ?>%">
                            <?= round(($totalPaid / $totalFees) * 100) ?? 0 ?>%
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Fee Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentFees as $fee): ?>
                                <tr class="<?= $fee['is_overdue'] ? 'table-danger' : '' ?>">
                                    <td><strong><?= htmlspecialchars($fee['fee_name']) ?></strong></td>
                                    <td>₦<?= number_format($fee['amount'], 2) ?></td>
                                    <td class="text-success">₦<?= number_format($fee['total_paid'], 2) ?></td>
                                    <td class="text-warning">₦<?= number_format($fee['balance'], 2) ?></td>
                                    <td><?= htmlspecialchars($fee['due_date'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($fee['balance'] == 0): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php elseif ($fee['is_overdue']): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <?php if (!empty($allPayments)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Payment History
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allPayments as $payment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                                    <td><?= htmlspecialchars($payment['fee_name']) ?></td>
                                    <td class="text-success">₦<?= number_format($payment['amount_paid'], 2) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($payment['payment_method'])) ?></td>
                                    <td><?= htmlspecialchars($payment['receipt_number'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
