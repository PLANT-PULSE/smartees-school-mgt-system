<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'All Semesters Attendance Report';
$pdo = getDb();

$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedStudent = intval($_GET['student_id'] ?? 0);

// Get classes
$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();

// Get attendance data
$semesters = [
    'Semester 1' => ['start' => '2026-01-01', 'end' => '2026-04-30'],
    'Semester 2' => ['start' => '2026-05-01', 'end' => '2026-08-31'],
    'Semester 3' => ['start' => '2026-09-01', 'end' => '2026-12-31']
];

$attendanceByStudent = [];
$students = [];
$studentInfo = null;
$classInfo = null;
$studentTotalAttendance = [];

if ($selectedClass) {
    // Get class info
    $stmt = $pdo->prepare('SELECT id, name FROM classes WHERE id = ?');
    $stmt->execute([$selectedClass]);
    $classInfo = $stmt->fetch();

    // Get students
    $stmt = $pdo->prepare('SELECT id, name FROM students WHERE class_id = ? ORDER BY name');
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();

    if ($selectedStudent) {
        // Get student info
        $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ? AND class_id = ?');
        $stmt->execute([$selectedStudent, $selectedClass]);
        $studentInfo = $stmt->fetch();

        // Get attendance for each semester for this student
        foreach ($semesters as $semesterName => $dates) {
            $stmt = $pdo->prepare('
                SELECT 
                    status,
                    COUNT(*) as count
                FROM attendance
                WHERE student_id = ? AND class_id = ? 
                AND date BETWEEN ? AND ?
                GROUP BY status
            ');
            $stmt->execute([
                $selectedStudent,
                $selectedClass,
                $dates['start'],
                $dates['end']
            ]);

            $result = [];
            foreach ($stmt->fetchAll() as $row) {
                $result[$row['status']] = $row['count'];
            }

            $studentTotalAttendance[$semesterName] = [
                'present' => $result['present'] ?? 0,
                'absent' => $result['absent'] ?? 0
            ];
        }
    } else {
        // Get attendance for each semester for all students
        foreach ($students as $student) {
            foreach ($semesters as $semesterName => $dates) {
                $stmt = $pdo->prepare('
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM attendance
                    WHERE student_id = ? AND class_id = ? 
                    AND date BETWEEN ? AND ?
                    GROUP BY status
                ');
                $stmt->execute([
                    $student['id'],
                    $selectedClass,
                    $dates['start'],
                    $dates['end']
                ]);

                $result = [];
                foreach ($stmt->fetchAll() as $row) {
                    $result[$row['status']] = $row['count'];
                }

                if (!isset($attendanceByStudent[$student['id']])) {
                    $attendanceByStudent[$student['id']] = [];
                }
                $attendanceByStudent[$student['id']][$semesterName] = [
                    'present' => $result['present'] ?? 0,
                    'absent' => $result['absent'] ?? 0
                ];
            }
        }
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container-fluid mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-history me-2"></i>All Semesters Attendance Report
    </h1>

    <!-- Filter -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Select Class</label>
                <select name="class_id" class="form-control" onchange="
                    const url = new URL(window.location);
                    url.searchParams.set('class_id', this.value);
                    url.searchParams.delete('student_id');
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
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-user me-2"></i>Select Student</label>
                <select name="student_id" class="form-control" onchange="
                    const url = new URL(window.location);
                    url.searchParams.set('class_id', document.querySelector('select[name=class_id]').value);
                    url.searchParams.set('student_id', this.value);
                    window.location = url.toString();
                ">
                    <option value="">All Students</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudent ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Semesters Overview -->
    <?php if ($selectedClass && $selectedStudent && $studentInfo): ?>
        <!-- Individual Student Semester Attendance -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    <?= htmlspecialchars($studentInfo['name']) ?> - Total Semester Attendance
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($semesters as $semesterName => $dates):
                        $studentData = $studentTotalAttendance[$semesterName] ?? ['present' => 0, 'absent' => 0];
                        $total = $studentData['present'] + $studentData['absent'];
                        $percentage = $total > 0 ? round(($studentData['present'] / $total) * 100, 1) : 0;
                    ?>
                        <div class="col-md-4 mb-4">
                            <div class="card border-left-<?= $percentage >= 80 ? 'success' : 'warning' ?>" style="border-left: 4px solid <?= $percentage >= 80 ? '#28a745' : '#ffc107' ?>;">
                                <div class="card-body">
                                    <h5 class="card-title"><?= $semesterName ?></h5>
                                    <p class="card-text text-muted">
                                        <small><?= date('M d', strtotime($dates['start'])) ?> - <?= date('M d, Y', strtotime($dates['end'])) ?></small>
                                    </p>
                                    
                                    <div class="row mt-3 text-center">
                                        <div class="col-6">
                                            <div style="background: #d4edda; padding: 15px; border-radius: 6px;">
                                                <div style="font-size: 1.5rem; font-weight: 700; color: #28a745;">
                                                    <?= $studentData['present'] ?>
                                                </div>
                                                <small style="color: #666;">Present</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div style="background: #f8d7da; padding: 15px; border-radius: 6px;">
                                                <div style="font-size: 1.5rem; font-weight: 700; color: #dc3545;">
                                                    <?= $studentData['absent'] ?>
                                                </div>
                                                <small style="color: #666;">Absent</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <small class="text-muted">Total Days: <?= $total ?></small>
                                        <div class="progress mt-2" style="height: 24px;">
                                            <div class="progress-bar bg-<?= $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger') ?>" 
                                                 style="width: <?= $percentage ?>%">
                                                <strong><?= $percentage ?>%</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Total Summary for Student -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Overall Semester Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <?php
                            $totalPresent = 0;
                            $totalAbsent = 0;
                            foreach ($studentTotalAttendance as $semesterData) {
                                $totalPresent += $semesterData['present'];
                                $totalAbsent += $semesterData['absent'];
                            }
                            $totalRecords = $totalPresent + $totalAbsent;
                            $overallPercentage = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 0;
                            ?>
                            <div class="col-md-3">
                                <h6 class="text-muted">Total Days Tracked</h6>
                                <h3 class="text-primary"><?= $totalRecords ?></h3>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">Total Present</h6>
                                <h3 class="text-success"><?= $totalPresent ?></h3>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">Total Absent</h6>
                                <h3 class="text-danger"><?= $totalAbsent ?></h3>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">Overall Percentage</h6>
                                <h3 class="text-<?= $overallPercentage >= 80 ? 'success' : 'warning' ?>"><?= $overallPercentage ?>%</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($selectedClass && !empty($students)): ?>
        <!-- Class Overview Summary -->
        <div class="row mb-4">
            <?php foreach ($semesters as $semesterName => $dates): ?>
                <?php
                $semesterPresent = 0;
                $semesterAbsent = 0;

                foreach ($attendanceByStudent as $studentData) {
                    if (isset($studentData[$semesterName])) {
                        $semesterPresent += $studentData[$semesterName]['present'];
                        $semesterAbsent += $studentData[$semesterName]['absent'];
                    }
                }

                $semesterTotal = $semesterPresent + $semesterAbsent;
                $semesterPercentage = $semesterTotal > 0 ? round(($semesterPresent / $semesterTotal) * 100, 1) : 0;
                ?>
                <div class="col-md-4">
                    <div class="card border-left-<?= $semesterPercentage >= 80 ? 'success' : 'warning' ?>" style="border-left: 4px solid <?= $semesterPercentage >= 80 ? '#28a745' : '#ffc107' ?>;">
                        <div class="card-body">
                            <h5 class="card-title"><?= $semesterName ?></h5>
                            <p class="card-text">
                                <strong><?= date('M d', strtotime($dates['start'])) ?> - <?= date('M d, Y', strtotime($dates['end'])) ?></strong>
                            </p>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <h6 class="text-success"><?= $semesterPresent ?></h6>
                                        <small>Present</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h6 class="text-danger"><?= $semesterAbsent ?></h6>
                                        <small>Absent</small>
                                    </div>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 20px;">
                                <div class="progress-bar bg-<?= $semesterPercentage >= 80 ? 'success' : 'warning' ?>" 
                                     style="width: <?= $semesterPercentage ?>%">
                                    <?= $semesterPercentage ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Student Details Table -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i>Student Attendance by Semester
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="25%"><i class="fas fa-user me-2"></i>Student Name</th>
                                <?php foreach ($semesters as $semesterName => $dates): ?>
                                    <th width="25%" class="text-center">
                                        <small><?= $semesterName ?></small><br>
                                        <strong>Present / Absent</strong>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <a href="?class_id=<?= $selectedClass ?>&student_id=<?= $student['id'] ?>" style="text-decoration: none; color: inherit;">
                                            <strong><?= htmlspecialchars($student['name']) ?></strong>
                                        </a>
                                    </td>
                                    <?php foreach ($semesters as $semesterName => $dates): ?>
                                        <?php
                                        $data = $attendanceByStudent[$student['id']][$semesterName] ?? ['present' => 0, 'absent' => 0];
                                        $total = $data['present'] + $data['absent'];
                                        $percentage = $total > 0 ? round(($data['present'] / $total) * 100, 1) : 0;
                                        ?>
                                        <td class="text-center">
                                            <div>
                                                <span class="badge bg-success me-1"><?= $data['present'] ?></span>
                                                <span class="badge bg-danger"><?= $data['absent'] ?></span>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                <?= $percentage ?>% (<?= $total ?> days)
                                            </small>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Overall Statistics (All Semesters)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <?php
                            $totalPresent = 0;
                            $totalAbsent = 0;
                            $totalRecords = 0;

                            foreach ($attendanceByStudent as $studentData) {
                                foreach ($studentData as $semesterData) {
                                    $totalPresent += $semesterData['present'];
                                    $totalAbsent += $semesterData['absent'];
                                }
                            }
                            $totalRecords = $totalPresent + $totalAbsent;
                            $overallPercentage = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 0;
                            ?>
                            <div class="col-md-3">
                                <h6 class="text-muted">Total Records</h6>
                                <h3 class="text-primary"><?= $totalRecords ?></h3>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">Total Present</h6>
                                <h3 class="text-success"><?= $totalPresent ?></h3>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">Total Absent</h6>
                                <h3 class="text-danger"><?= $totalAbsent ?></h3>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted">Overall Percentage</h6>
                                <h3 class="text-<?= $overallPercentage >= 80 ? 'success' : 'warning' ?>"><?= $overallPercentage ?>%</h3>
                            </div>
                        </div>
                    </div>
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
            Select a class to view all semesters attendance report. Optionally select a specific student to see their detailed semester attendance.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
