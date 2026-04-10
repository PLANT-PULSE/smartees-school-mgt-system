<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();

$user = currentUser();
if ($user['role'] !== 'parent') {
    http_response_code(403);
    die('Access denied');
}

$pageTitle = 'Parent Dashboard';
$pdo = getDb();

// Initialize services
$parentService = new ParentService($pdo);
$studentService = new StudentService($pdo);
$feeService = new FeeService($pdo);
$gradeService = new GradeService($pdo);
$attendanceService = new AttendanceService($pdo);
$scheduleService = new ScheduleService($pdo);

// Get parent's user ID from session
$stmt = $pdo->prepare('SELECT parent_id FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$userParent = $stmt->fetch(PDO::FETCH_ASSOC);
$parentId = $userParent['parent_id'] ?? null;

if (!$parentId) {
    die('Parent profile not found');
}

// Get parent's students
$parentWithStudents = $parentService->getParentWithStudents($parentId);
$students = $parentWithStudents['students'] ?? [];

// Get selected student (default to first)
$selectedStudentId = intval($_GET['student_id'] ?? ($students[0]['id'] ?? 0));
$selectedStudent = null;

foreach ($students as $s) {
    if ($s['id'] == $selectedStudentId) {
        $selectedStudent = $s;
        break;
    }
}

if (!$selectedStudent && !empty($students)) {
    $selectedStudent = $students[0];
    $selectedStudentId = $selectedStudent['id'];
}

// Get student details
$studentDetails = $selectedStudent ? $studentService->getStudentDetails($selectedStudentId) : null;
$studentFees = $selectedStudent ? $feeService->getStudentFeesOverview($selectedStudentId) : [];
$studentGrades = $selectedStudent ? $gradeService->getStudentGrades($selectedStudentId) : [];
$attendanceStats = $selectedStudent ? $attendanceService->getAttendanceStats($selectedStudentId, 3) : [];
$overdueFees = $selectedStudent ? $feeService->getOverdueFees($selectedStudentId) : [];
$paymentHistory = $selectedStudent ? $feeService->getPaymentHistory($selectedStudentId) : [];

// Get class schedule
$classSchedule = $selectedStudent && isset($selectedStudent['class_id']) 
    ? $scheduleService->getClassSchedule($selectedStudent['class_id']) 
    : [];

$dayNames = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="dashboard-container">
    <!-- Welcome Section -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 8px; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p style="color: rgba(255,255,255,0.9); margin-bottom: 5px;">Parent Dashboard</p>
                <h2 style="color: white; margin-bottom: 0;">Monitor your child's progress</h2>
            </div>
            <div>
                <i class="fas fa-chart-line fa-4x text-white opacity-75"></i>
            </div>
        </div>
    </div>

    <!-- Student Selection -->
    <?php if (count($students) > 1): ?>
    <div style="margin-bottom: 30px;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h5 style="margin-bottom: 15px;">
                <i class="fas fa-users me-2"></i>Select Student
            </h5>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach ($students as $s): ?>
                <a href="?student_id=<?= $s['id'] ?>" style="padding: 10px 20px; border-radius: 6px; background: <?= $s['id'] == $selectedStudentId ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : '#f0f0f0' ?>; color: <?= $s['id'] == $selectedStudentId ? 'white' : '#333' ?>; text-decoration: none; transition: all 0.3s ease;">
                    <?= htmlspecialchars($s['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($selectedStudent): ?>

        <!-- Student Profile Card -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-id-card me-2"></i>Student Profile
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <?php if ($selectedStudent['photo']): ?>
                                <img src="<?= htmlspecialchars($selectedStudent['photo']) ?>" alt="Student" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                                    <i class="fas fa-user fa-5x text-muted"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <h4><?= htmlspecialchars($selectedStudent['name']) ?></h4>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Age:</strong></td>
                                        <td><?= $selectedStudent['age'] ?? 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Class:</strong></td>
                                        <td><?= isset($selectedStudent['class']) ? htmlspecialchars($selectedStudent['class']['name']) : 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Contact:</strong></td>
                                        <td><?= htmlspecialchars($selectedStudent['contact'] ?? 'N/A') ?></td>
                                    </tr>
                                    <?php if ($selectedStudent['contacts']): ?>
                                    <tr>
                                        <td><strong>Emergency Contact:</strong></td>
                                        <td><?= htmlspecialchars($selectedStudent['contacts']['emergency_contact_name'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Blood Group:</strong></td>
                                        <td><?= htmlspecialchars($selectedStudent['contacts']['blood_group'] ?? 'N/A') ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center shadow-sm" style="border-left: 4px solid #28a745;">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h6 class="text-muted">Present</h6>
                        <h3 class="text-success"><?= $attendanceStats['present'] ?? 0 ?></h3>
                        <small class="text-muted">Last 3 months</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center shadow-sm" style="border-left: 4px solid #dc3545;">
                    <div class="card-body">
                        <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                        <h6 class="text-muted">Absent</h6>
                        <h3 class="text-danger"><?= $attendanceStats['absent'] ?? 0 ?></h3>
                        <small class="text-muted">Last 3 months</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center shadow-sm" style="border-left: 4px solid #ffc107;">
                    <div class="card-body">
                        <i class="fas fa-coins fa-2x text-warning mb-2"></i>
                        <h6 class="text-muted">Fees Pending</h6>
                        <h3 class="text-warning"><?= count($overdueFees) ?></h3>
                        <small class="text-muted">Overdue</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center shadow-sm" style="border-left: 4px solid #17a2b8;">
                    <div class="card-body">
                        <i class="fas fa-book fa-2x text-info mb-2"></i>
                        <h6 class="text-muted">Subjects</h6>
                        <h3 class="text-info"><?= count($studentGrades) ?></h3>
                        <small class="text-muted">Enrolled</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="row mb-4">
            <!-- Left Column: Grades and Schedule -->
            <div class="col-lg-6 mb-4">
                <!-- Grades Section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-star me-2"></i>Academic Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($studentGrades): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Grade</th>
                                        <th>Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($studentGrades as $grade): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($grade['subject_name']) ?></td>
                                        <td>
                                            <span class="badge badge-primary"><?= htmlspecialchars($grade['grade']) ?></span>
                                        </td>
                                        <td><small><?= htmlspecialchars($grade['remark'] ?? '-') ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-0">No grades recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Class Schedule -->
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar me-2"></i>Class Schedule
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($classSchedule): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Subject</th>
                                        <th>Time</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classSchedule as $schedule): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($dayNames[$schedule['day_of_week']] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($schedule['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($schedule['start_time']) . ' - ' . htmlspecialchars($schedule['end_time']) ?></td>
                                        <td><?= htmlspecialchars($schedule['room_number'] ?? 'N/A') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-0">No schedule available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Fees Information -->
            <div class="col-lg-6">
                <!-- Fees Overview -->
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Fee Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($studentFees): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($studentFees as $fee): ?>
                                    <tr class="<?= $fee['is_overdue'] ? 'table-danger' : '' ?>">
                                        <td><?= htmlspecialchars($fee['fee_name']) ?></td>
                                        <td>₦<?= number_format($fee['amount'], 2) ?></td>
                                        <td>₦<?= number_format($fee['total_paid'], 2) ?></td>
                                        <td>
                                            <strong class="<?= $fee['balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                                ₦<?= number_format($fee['balance'], 2) ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?= $fee['due_date'] ? htmlspecialchars($fee['due_date']) : 'N/A' ?>
                                            <?php if ($fee['is_overdue']): ?>
                                            <br><small class="badge bg-danger">Overdue</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Fee Summary -->
                        <?php 
                            $totalFees = array_sum(array_column($studentFees, 'amount'));
                            $totalPaid = array_sum(array_column($studentFees, 'total_paid'));
                            $totalBalance = $totalFees - $totalPaid;
                        ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="row">
                                <div class="col-4 text-center">
                                    <small class="text-muted">Total Fees</small>
                                    <br><strong>₦<?= number_format($totalFees, 2) ?></strong>
                                </div>
                                <div class="col-4 text-center">
                                    <small class="text-muted">Paid</small>
                                    <br><strong class="text-success">₦<?= number_format($totalPaid, 2) ?></strong>
                                </div>
                                <div class="col-4 text-center">
                                    <small class="text-muted">Balance</small>
                                    <br><strong class="text-danger">₦<?= number_format($totalBalance, 2) ?></strong>
                                </div>
                            </div>
                        </div>

                        <?php if ($paymentHistory): ?>
                        <hr>
                        <h6 class="mt-3 mb-2">Recent Payments</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($paymentHistory, 0, 5) as $payment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                                        <td><?= htmlspecialchars($payment['fee_name']) ?></td>
                                        <td class="text-success">+₦<?= number_format($payment['amount_paid'], 2) ?></td>
                                        <td><small><?= htmlspecialchars(ucfirst($payment['payment_method'])) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <?php else: ?>
                        <p class="text-muted mb-0">No fee information available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- No Student Selected -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                        <h5>No Students Linked</h5>
                        <p class="text-muted">No students are currently linked to your account. Please contact the school administration.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
