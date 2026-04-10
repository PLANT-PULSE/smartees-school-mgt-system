<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Fees Management';
$pdo = getDb();

$action = $_GET['action'] ?? 'overview';
$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedStudent = intval($_GET['student_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add_fee') {
        $classId = intval($_POST['class_id']);
        $feeName = trim($_POST['fee_name']);
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        $dueDate = $_POST['due_date'] ?: null;

        if ($feeName && $amount > 0) {
            $stmt = $pdo->prepare('INSERT INTO class_fees (class_id, fee_name, amount, description, due_date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$classId, $feeName, $amount, $description, $dueDate]);
            flash('success', 'Fee added successfully.');
        } else {
            flash('error', 'Please provide valid fee details.');
        }
        redirect("fees.php?action=manage_fees&class_id=$classId");

    } elseif ($action === 'update_fee') {
        $feeId = intval($_POST['fee_id']);
        $feeName = trim($_POST['fee_name']);
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        $dueDate = $_POST['due_date'] ?: null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($feeName && $amount > 0) {
            $stmt = $pdo->prepare('UPDATE class_fees SET fee_name = ?, amount = ?, description = ?, due_date = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$feeName, $amount, $description, $dueDate, $isActive, $feeId]);
            flash('success', 'Fee updated successfully.');
        } else {
            flash('error', 'Please provide valid fee details.');
        }
        redirect("fees.php?action=manage_fees&class_id=$selectedClass");

    } elseif ($action === 'delete_fee') {
        $feeId = intval($_POST['fee_id']);
        $stmt = $pdo->prepare('DELETE FROM class_fees WHERE id = ?');
        $stmt->execute([$feeId]);
        flash('success', 'Fee deleted successfully.');
        redirect("fees.php?action=manage_fees&class_id=$selectedClass");

    } elseif ($action === 'record_payment') {
        $studentId = intval($_POST['student_id']);
        $classFeeId = intval($_POST['class_fee_id']);
        $amountPaid = floatval($_POST['amount_paid']);
        $paymentMethod = trim($_POST['payment_method']);
        $receiptNumber = trim($_POST['receipt_number']);
        $notes = trim($_POST['notes']);

        if ($amountPaid > 0) {
            $stmt = $pdo->prepare('INSERT INTO student_fees (student_id, class_fee_id, amount_paid, payment_method, receipt_number, notes) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount_paid = amount_paid + VALUES(amount_paid), payment_date = CURRENT_TIMESTAMP, payment_method = VALUES(payment_method), receipt_number = VALUES(receipt_number), notes = VALUES(notes)');
            $stmt->execute([$studentId, $classFeeId, $amountPaid, $paymentMethod, $receiptNumber, $notes]);
            flash('success', 'Payment recorded successfully.');
        } else {
            flash('error', 'Please enter a valid payment amount.');
        }
        redirect("fees.php?action=student_fees&student_id=$studentId");
    }
} elseif ($action === 'get_fee' && isset($_GET['fee_id'])) {
    // AJAX endpoint for getting fee data
    $feeId = intval($_GET['fee_id']);
    $stmt = $pdo->prepare('SELECT * FROM class_fees WHERE id = ?');
    $stmt->execute([$feeId]);
    $fee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fee) {
        header('Content-Type: application/json');
        echo json_encode($fee);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Fee not found']);
        exit;
    }
}

// Get data based on action
$classes = $pdo->query('SELECT c.*, t.name as teacher_name FROM classes c LEFT JOIN teachers t ON c.teacher_id = t.id ORDER BY c.name')->fetchAll();

$fees = [];
$students = [];
$studentFees = [];
$feeSummary = [];

if ($action === 'manage_fees' && $selectedClass) {
    $stmt = $pdo->prepare('SELECT * FROM class_fees WHERE class_id = ? ORDER BY due_date, fee_name');
    $stmt->execute([$selectedClass]);
    $fees = $stmt->fetchAll();
} elseif ($action === 'student_fees' && $selectedStudent) {
    // Get student info
    $stmt = $pdo->prepare('SELECT s.*, c.name as class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = ?');
    $stmt->execute([$selectedStudent]);
    $student = $stmt->fetch();

    if (!$student) {
        flash('error', 'Student not found.');
        redirect('fees.php?action=student_fees');
    }

    // Get all fees for student's class
    $stmt = $pdo->prepare('SELECT cf.*, COALESCE(sf.amount_paid, 0) as amount_paid, sf.payment_date, sf.payment_method, sf.receipt_number, sf.notes FROM class_fees cf LEFT JOIN student_fees sf ON cf.id = sf.class_fee_id AND sf.student_id = ? WHERE cf.class_id = ? AND cf.is_active = 1 ORDER BY cf.due_date, cf.fee_name');
    $stmt->execute([$selectedStudent, $student['class_id']]);
    $studentFees = $stmt->fetchAll();
} elseif ($action === 'overview') {
    // Get fee summary for all classes
    $stmt = $pdo->query("
        SELECT
            c.name as class_name,
            c.id as class_id,
            COUNT(DISTINCT s.id) as total_students,
            COUNT(DISTINCT cf.id) as total_fees,
            COALESCE(SUM(cf.amount), 0) as total_fee_amount,
            COALESCE(SUM(sf.amount_paid), 0) as total_paid,
            COALESCE(SUM(cf.amount - COALESCE(sf.amount_paid, 0)), 0) as total_pending
        FROM classes c
        LEFT JOIN students s ON c.id = s.class_id
        LEFT JOIN class_fees cf ON c.id = cf.class_id AND cf.is_active = 1
        LEFT JOIN student_fees sf ON cf.id = sf.class_fee_id
        GROUP BY c.id, c.name
        ORDER BY c.name
    ");
    $feeSummary = $stmt->fetchAll();
}

function calculateFeeStatus($feeAmount, $paidAmount) {
    if ($paidAmount >= $feeAmount) {
        return ['status' => 'paid', 'remaining' => 0, 'percentage' => 100];
    } elseif ($paidAmount > 0) {
        $remaining = $feeAmount - $paidAmount;
        $percentage = ($paidAmount / $feeAmount) * 100;
        return ['status' => 'partial', 'remaining' => $remaining, 'percentage' => $percentage];
    } else {
        return ['status' => 'unpaid', 'remaining' => $feeAmount, 'percentage' => 0];
    }
}
?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<!-- Page Header -->
<div class="table-header">
    <div>
        <h1 class="table-title">
            <i class="fas fa-dollar-sign me-3 text-primary"></i>Fees Management
        </h1>
        <p class="text-muted mb-0">Manage class fees and track student payments</p>
    </div>
    <div class="table-actions">
        <a href="fees.php?action=overview" class="btn btn-primary-custom">
            <i class="fas fa-chart-bar me-2"></i>Overview
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($msg = flash('success')): ?>
    <div class="alert alert-custom alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-custom alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Navigation Tabs -->
<div class="nav-tabs-custom mb-4">
    <a href="fees.php?action=overview" class="nav-tab <?= $action === 'overview' ? 'active' : '' ?>">
        <i class="fas fa-chart-bar me-2"></i>Overview
    </a>
    <a href="fees.php?action=manage_fees" class="nav-tab <?= $action === 'manage_fees' ? 'active' : '' ?>">
        <i class="fas fa-cogs me-2"></i>Manage Fees
    </a>
    <a href="fees.php?action=student_fees" class="nav-tab <?= $action === 'student_fees' ? 'active' : '' ?>">
        <i class="fas fa-users me-2"></i>Student Fees
    </a>
</div>

<?php if ($action === 'overview'): ?>
    <!-- Fee Overview -->
    <div class="row">
        <?php foreach ($feeSummary as $summary): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="fee-card">
                    <div class="fee-card-header">
                        <h3 class="fee-card-title">
                            <i class="fas fa-graduation-cap me-2"></i>
                            <?= htmlspecialchars($summary['class_name']) ?>
                        </h3>
                        <div class="fee-card-stats">
                            <span class="badge badge-info-custom">
                                <i class="fas fa-users me-1"></i>
                                <?= $summary['total_students'] ?> students
                            </span>
                        </div>
                    </div>
                    <div class="fee-card-body">
                        <div class="fee-stat">
                            <div class="fee-stat-label">Total Fees</div>
                            <div class="fee-stat-value">$<?= number_format($summary['total_fee_amount'], 2) ?></div>
                        </div>
                        <div class="fee-stat">
                            <div class="fee-stat-label">Paid</div>
                            <div class="fee-stat-value text-success">$<?= number_format($summary['total_paid'], 2) ?></div>
                        </div>
                        <div class="fee-stat">
                            <div class="fee-stat-label">Pending</div>
                            <div class="fee-stat-value text-warning">$<?= number_format($summary['total_pending'], 2) ?></div>
                        </div>
                        <div class="fee-progress">
                            <div class="progress-bar-custom" style="width: <?= $summary['total_fee_amount'] > 0 ? ($summary['total_paid'] / $summary['total_fee_amount'] * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="fee-card-footer">
                        <a href="fees.php?action=manage_fees&class_id=<?= $summary['class_id'] ?>" class="btn btn-sm btn-primary-custom">
                            <i class="fas fa-cogs me-1"></i>Manage Fees
                        </a>
                        <a href="fees.php?action=student_fees&class_id=<?= $summary['class_id'] ?>" class="btn btn-sm btn-secondary-custom">
                            <i class="fas fa-users me-1"></i>View Students
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($action === 'manage_fees'): ?>
    <!-- Manage Fees -->
    <?php if (!$selectedClass): ?>
        <!-- Class Selection -->
        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-graduation-cap me-2"></i>Select Class
                </h2>
                <p class="form-subtitle">Choose a class to manage fees</p>
            </div>
            <div class="row">
                <?php foreach ($classes as $class): ?>
                    <div class="col-md-4 mb-3">
                        <a href="fees.php?action=manage_fees&class_id=<?= $class['id'] ?>" class="class-card">
                            <div class="class-card-content">
                                <i class="fas fa-graduation-cap class-icon"></i>
                                <h4><?= htmlspecialchars($class['name']) ?></h4>
                                <p class="text-muted">
                                    <?php if ($class['teacher_name']): ?>
                                        Teacher: <?= htmlspecialchars($class['teacher_name']) ?>
                                    <?php else: ?>
                                        No teacher assigned
                                    <?php endif; ?>
                                </p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Fee Management for Selected Class -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-cogs me-2"></i>
                Managing Fees for <?= htmlspecialchars($classes[array_search($selectedClass, array_column($classes, 'id'))]['name'] ?? 'Unknown Class') ?>
            </h2>
            <div>
                <a href="fees.php?action=manage_fees" class="btn btn-secondary-custom me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Classes
                </a>
                <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                    <i class="fas fa-plus me-2"></i>Add Fee
                </button>
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-tag me-2"></i>Fee Name</th>
                            <th><i class="fas fa-dollar-sign me-2"></i>Amount</th>
                            <th><i class="fas fa-calendar me-2"></i>Due Date</th>
                            <th><i class="fas fa-info-circle me-2"></i>Description</th>
                            <th><i class="fas fa-toggle-on me-2"></i>Status</th>
                            <th class="text-end"><i class="fas fa-cogs me-2"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($fees)): ?>
                            <?php foreach ($fees as $fee): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($fee['fee_name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary-custom">
                                            $<?= number_format($fee['amount'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($fee['due_date']): ?>
                                            <span class="text-muted">
                                                <i class="fas fa-calendar-day me-1"></i>
                                                <?= date('M d, Y', strtotime($fee['due_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($fee['description'] ?: 'No description') ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $fee['is_active'] ? 'badge-success-custom' : 'badge-danger-custom' ?>">
                                            <?= $fee['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-edit action-btn" onclick="editFee(<?= $fee['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this fee?')">
                                            <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                                            <button type="submit" name="action" value="delete_fee" class="btn btn-delete action-btn">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-dollar-sign"></i>
                                        <h3>No Fees Found</h3>
                                        <p>Get started by adding fees for this class.</p>
                                        <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                                            <i class="fas fa-plus me-2"></i>Add First Fee
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'student_fees' && $selectedStudent): ?>
    <!-- Student Fees -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>
                <i class="fas fa-user-graduate me-2"></i>
                Fees for <?= htmlspecialchars($student['name']) ?>
            </h2>
            <p class="text-muted mb-0">Class: <?= htmlspecialchars($student['class_name']) ?></p>
        </div>
        <a href="fees.php?action=student_fees" class="btn btn-secondary-custom">
            <i class="fas fa-arrow-left me-2"></i>Back to Students
        </a>
    </div>

    <div class="row">
        <?php
        $totalFees = 0;
        $totalPaid = 0;
        $totalPending = 0;
        foreach ($studentFees as $fee) {
            $totalFees += $fee['amount'];
            $totalPaid += $fee['amount_paid'];
            $totalPending += max(0, $fee['amount'] - $fee['amount_paid']);
        }
        ?>
        <div class="col-md-4">
            <div class="fee-summary-card">
                <div class="fee-summary-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="fee-summary-content">
                    <h4>$<?= number_format($totalFees, 2) ?></h4>
                    <p>Total Fees</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="fee-summary-card">
                <div class="fee-summary-icon text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="fee-summary-content">
                    <h4>$<?= number_format($totalPaid, 2) ?></h4>
                    <p>Total Paid</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="fee-summary-card">
                <div class="fee-summary-icon text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="fee-summary-content">
                    <h4>$<?= number_format($totalPending, 2) ?></h4>
                    <p>Pending</p>
                </div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="fas fa-tag me-2"></i>Fee Name</th>
                        <th><i class="fas fa-dollar-sign me-2"></i>Amount</th>
                        <th><i class="fas fa-credit-card me-2"></i>Paid</th>
                        <th><i class="fas fa-minus-circle me-2"></i>Remaining</th>
                        <th><i class="fas fa-calendar me-2"></i>Due Date</th>
                        <th><i class="fas fa-info-circle me-2"></i>Status</th>
                        <th class="text-end"><i class="fas fa-cogs me-2"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentFees as $fee):
                        $status = calculateFeeStatus($fee['amount'], $fee['amount_paid']);
                    ?>
                        <tr class="fee-row-<?= $status['status'] ?>">
                            <td>
                                <strong><?= htmlspecialchars($fee['fee_name']) ?></strong>
                                <?php if ($fee['description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($fee['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>$<?= number_format($fee['amount'], 2) ?></td>
                            <td>
                                <span class="text-success">$<?= number_format($fee['amount_paid'], 2) ?></span>
                            </td>
                            <td>
                                <span class="text-<?= $status['remaining'] > 0 ? 'warning' : 'success' ?>">
                                    $<?= number_format($status['remaining'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($fee['due_date']): ?>
                                    <?= date('M d, Y', strtotime($fee['due_date'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">No due date</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fee-status">
                                    <span class="badge badge-<?= $status['status'] === 'paid' ? 'success' : ($status['status'] === 'partial' ? 'warning' : 'danger') ?>-custom">
                                        <?= ucfirst($status['status']) ?>
                                    </span>
                                    <div class="fee-progress-small">
                                        <div class="progress-bar-custom" style="width: <?= $status['percentage'] ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-primary-custom btn-sm" onclick="recordPayment(<?= $fee['id'] ?>, <?= $selectedStudent ?>, '<?= htmlspecialchars($fee['fee_name']) ?>', <?= $fee['amount'] - $fee['amount_paid'] ?>)">
                                    <i class="fas fa-plus me-1"></i>Pay
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action === 'student_fees' && !$selectedStudent): ?>
    <!-- Student Selection -->
    <div class="form-container">
        <div class="form-header">
            <h2 class="form-title">
                <i class="fas fa-users me-2"></i>Select Student
            </h2>
            <p class="form-subtitle">Choose a student to view their fee details</p>
        </div>
        <div class="row">
            <?php
            if ($selectedClass) {
                $students = $pdo->prepare('SELECT s.*, c.name as class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.class_id = ? ORDER BY s.name');
                $students->execute([$selectedClass]);
                $students = $students->fetchAll();
            } else {
                $students = $pdo->query('SELECT s.*, c.name as class_name FROM students s JOIN classes c ON s.class_id = c.id ORDER BY c.name, s.name')->fetchAll();
            }

            if (empty($students)) {
                echo '<div class="col-12"><div class="alert alert-info">No students found.</div></div>';
            }

            foreach ($students as $student):
            ?>
                <div class="col-md-4 mb-3">
                    <a href="fees.php?action=student_fees&student_id=<?= $student['id'] ?>" class="student-card">
                        <div class="student-card-content">
                            <i class="fas fa-user-graduate student-icon"></i>
                            <h4><?= htmlspecialchars($student['name']) ?></h4>
                            <p class="text-muted">Class: <?= htmlspecialchars($student['class_name']) ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Add Fee Modal -->
<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Fee
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="fee_name">Fee Name *</label>
                        <input type="text" class="form-control" id="fee_name" name="fee_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="amount">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="due_date">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="add_fee" class="btn btn-primary-custom">Add Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Fee Modal -->
<div class="modal fade" id="editFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Fee
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="fee_id" id="edit_fee_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_fee_name">Fee Name *</label>
                        <input type="text" class="form-control" id="edit_fee_name" name="fee_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_amount">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_due_date">Due Date</label>
                        <input type="date" class="form-control" id="edit_due_date" name="due_date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" checked>
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="update_fee" class="btn btn-primary-custom">Update Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card me-2"></i>Record Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="student_id" id="payment_student_id">
                <input type="hidden" name="class_fee_id" id="payment_class_fee_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Fee</label>
                        <input type="text" class="form-control" id="payment_fee_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="amount_paid">Payment Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" min="0" required>
                        </div>
                        <small class="text-muted">Remaining amount: $<span id="remaining_amount"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payment_method">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method">
                            <option value="">Select method</option>
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Online">Online</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="receipt_number">Receipt Number</label>
                        <input type="text" class="form-control" id="receipt_number" name="receipt_number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payment_notes">Notes</label>
                        <textarea class="form-control" id="payment_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="record_payment" class="btn btn-primary-custom">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Additional CSS for this page -->
<style>
.fee-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.fee-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}

.fee-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
}

.fee-card-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.fee-card-stats {
    margin-top: 10px;
}

.fee-card-body {
    padding: 20px;
}

.fee-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.fee-stat-label {
    font-weight: 500;
    color: #666;
}

.fee-stat-value {
    font-weight: 700;
    font-size: 1.1rem;
}

.fee-progress {
    height: 8px;
    background: #e1e5e9;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 10px;
}

.fee-card-footer {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
}

.class-card, .student-card {
    display: block;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.class-card:hover, .student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    border-color: #667eea;
}

.class-card-content, .student-card-content {
    padding: 20px;
    text-align: center;
}

.class-icon, .student-icon {
    font-size: 2rem;
    color: #667eea;
    margin-bottom: 10px;
}

.fee-summary-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 15px;
}

.fee-summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #667eea;
}

.fee-summary-content h4 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
}

.fee-summary-content p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.fee-row-paid {
    background: rgba(40, 167, 69, 0.05);
}

.fee-row-partial {
    background: rgba(255, 193, 7, 0.05);
}

.fee-row-unpaid {
    background: rgba(220, 53, 69, 0.05);
}

.fee-status {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 5px;
}

.fee-progress-small {
    width: 60px;
    height: 4px;
    background: #e1e5e9;
    border-radius: 2px;
    overflow: hidden;
}

.nav-tabs-custom {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 30px;
}

.nav-tab {
    padding: 12px 24px;
    text-decoration: none;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.nav-tab:hover {
    color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}

.nav-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}
</style>

<!-- Additional JavaScript for this page -->
<script>
function editFee(feeId) {
    // Fetch fee data and populate edit modal
    fetch(`fees.php?action=get_fee&fee_id=${feeId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_fee_id').value = data.id;
            document.getElementById('edit_fee_name').value = data.fee_name;
            document.getElementById('edit_amount').value = data.amount;
            document.getElementById('edit_due_date').value = data.due_date || '';
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_is_active').checked = data.is_active == 1;

            new bootstrap.Modal(document.getElementById('editFeeModal')).show();
        })
        .catch(error => {
            alert('Error loading fee data');
            console.error('Error:', error);
        });
}

function recordPayment(feeId, studentId, feeName, remainingAmount) {
    document.getElementById('payment_student_id').value = studentId;
    document.getElementById('payment_class_fee_id').value = feeId;
    document.getElementById('payment_fee_name').value = feeName;
    document.getElementById('remaining_amount').textContent = remainingAmount.toFixed(2);
    document.getElementById('amount_paid').max = remainingAmount;

    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

// Add ripple effect to cards
document.querySelectorAll('.class-card, .student-card').forEach(card => {
    card.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;

        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(ripple);

        setTimeout(() => ripple.remove(), 600);
    });
});
</script>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>