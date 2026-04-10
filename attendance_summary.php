<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Attendance Summary Report';
$pdo = getDb();

$selectedClass = intval($_GET['class_id'] ?? 0);

// Get classes
$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();

// Get summary statistics
$summary = [];
$students = [];

if ($selectedClass) {
    // Get students
    $stmt = $pdo->prepare('SELECT id, name FROM students WHERE class_id = ? ORDER BY name');
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();

    // Get attendance summary
    $stmt = $pdo->prepare('
        SELECT 
            student_id,
            status,
            COUNT(*) as count
        FROM attendance
        WHERE class_id = ?
        GROUP BY student_id, status
    ');
    $stmt->execute([$selectedClass]);

    foreach ($stmt->fetchAll() as $row) {
        if (!isset($summary[$row['student_id']])) {
            $summary[$row['student_id']] = ['present' => 0, 'absent' => 0];
        }
        $summary[$row['student_id']][$row['status']] = $row['count'];
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-chart-pie me-2"></i>Attendance Summary Report
    </h1>

    <!-- Filter -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Select Class</label>
                <select name="class_id" class="form-control" onchange="
                    const url = new URL(window.location);
                    url.searchParams.set('class_id', this.value);
                    window.location = url.toString();
                ">
                    <option value="">Choose a class...</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?= $cls['id'] ?>" <?= $cls['id'] == $selectedClass ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cls['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <?php if ($selectedClass): ?>
        <?php
        // Calculate class-wide statistics
        $totalPresent = 0;
        $totalAbsent = 0;
        $totalRecords = 0;

        foreach ($summary as $studentSummary) {
            $totalPresent += $studentSummary['present'];
            $totalAbsent += $studentSummary['absent'];
        }
        $totalRecords = $totalPresent + $totalAbsent;
        $overallPercentage = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 0;
        ?>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Total Students</h5>
                        <p class="card-text" style="font-size: 2rem; font-weight: bold; color: #667eea;">
                            <?= count($students) ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Total Present</h5>
                        <p class="card-text" style="font-size: 2rem; font-weight: bold; color: #28a745;">
                            <?= $totalPresent ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-times fa-3x text-danger mb-3"></i>
                        <h5 class="card-title">Total Absent</h5>
                        <p class="card-text" style="font-size: 2rem; font-weight: bold; color: #dc3545;">
                            <?= $totalAbsent ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-percentage fa-3x text-<?= $overallPercentage >= 80 ? 'success' : 'warning' ?> mb-3"></i>
                        <h5 class="card-title">Overall Rate</h5>
                        <p class="card-text" style="font-size: 2rem; font-weight: bold; color: <?= $overallPercentage >= 80 ? '#28a745' : '#ffc107' ?>;">
                            <?= $overallPercentage ?>%
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Attendance Table -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i>Student Attendance Summary
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="45%"><i class="fas fa-user me-2"></i>Student Name</th>
                                <th width="12%" class="text-center"><i class="fas fa-check me-1"></i>Present</th>
                                <th width="12%" class="text-center"><i class="fas fa-times me-1"></i>Absent</th>
                                <th width="12%" class="text-center"><i class="fas fa-calendar me-1"></i>Total</th>
                                <th width="19%" class="text-center"><i class="fas fa-chart-line me-1"></i>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student):
                                $studentSummary = $summary[$student['id']] ?? ['present' => 0, 'absent' => 0];
                                $present = $studentSummary['present'];
                                $absent = $studentSummary['absent'];
                                $total = $present + $absent;
                                $percentage = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($student['name']) ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success" style="font-size: 1rem;">
                                            <?= $present ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger" style="font-size: 1rem;">
                                            <?= $absent ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?= $total ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar bg-<?= $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger') ?>" 
                                                 style="width: <?= $percentage ?>%">
                                                <?= $percentage ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="alert alert-info mt-4">
            <h6 class="mb-3"><i class="fas fa-key me-2"></i>Attendance Levels</h6>
            <div class="row">
                <div class="col-md-3">
                    <i class="fas fa-check text-success me-2"></i>
                    <strong>80%+</strong> - Excellent attendance
                </div>
                <div class="col-md-3">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    <strong>60-79%</strong> - Acceptable attendance
                </div>
                <div class="col-md-3">
                    <i class="fas fa-times text-danger me-2"></i>
                    <strong>&lt;60%</strong> - Action required
                </div>
            </div>
        </div>

    <?php elseif ($selectedClass): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No students enrolled in this class yet.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Select a class to view attendance summary.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
