<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Monthly Attendance Report';
$pdo = getDb();

$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedStudent = intval($_GET['student_id'] ?? 0);

// Get classes
$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();

// Parse month
$monthDate = DateTime::createFromFormat('Y-m', $selectedMonth);
$monthStart = $monthDate->format('Y-m-01');
$monthEnd = $monthDate->format('Y-m-t');
$monthDisplay = $monthDate->format('F Y');

// Get attendance report
$report = [];
$students = [];
$studentInfo = null;
$classInfo = null;

if ($selectedClass) {
    // Get students
    $stmt = $pdo->prepare('SELECT id, name FROM students WHERE class_id = ? ORDER BY name');
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();

    // Get class info
    $stmt = $pdo->prepare('SELECT id, name FROM classes WHERE id = ?');
    $stmt->execute([$selectedClass]);
    $classInfo = $stmt->fetch();

    if ($selectedStudent) {
        // Get student info
        $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ? AND class_id = ?');
        $stmt->execute([$selectedStudent, $selectedClass]);
        $studentInfo = $stmt->fetch();

        // Get attendance for the student for the month
        $stmt = $pdo->prepare('
            SELECT date, status FROM attendance
            WHERE student_id = ? AND class_id = ? AND date BETWEEN ? AND ?
            ORDER BY date
        ');
        $stmt->execute([$selectedStudent, $selectedClass, $monthStart, $monthEnd]);
        
        foreach ($stmt->fetchAll() as $row) {
            $report[$row['date']] = $row['status'];
        }
    } else {
        // Get attendance for all students in class for overview
        $stmt = $pdo->prepare('
            SELECT student_id, date, status FROM attendance
            WHERE class_id = ? AND date BETWEEN ? AND ?
            ORDER BY date, student_id
        ');
        $stmt->execute([$selectedClass, $monthStart, $monthEnd]);
        
        foreach ($stmt->fetchAll() as $row) {
            if (!isset($report[$row['student_id']])) {
                $report[$row['student_id']] = [];
            }
            $report[$row['student_id']][$row['date']] = $row['status'];
        }
    }
}

// Get all dates in the month
$daysInMonth = [];
$current = new DateTime($monthStart);
$end = new DateTime($monthEnd);
while ($current <= $end) {
    $daysInMonth[] = $current->format('Y-m-d');
    $current->modify('+1 day');
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container-fluid mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-chart-line me-2"></i>Monthly Attendance Report
    </h1>

    <!-- Filters -->
    <form method="get" class="row mb-4">
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Select Class</label>
                <select name="class_id" class="form-control" onchange="this.form.submit()">
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
                <select name="student_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Students</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudent ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-calendar me-2"></i>Select Month</label>
                <input type="month" name="month" class="form-control" value="<?= $selectedMonth ?>" onchange="this.form.submit()">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php
                    $classNameDisplay = '';
                    foreach ($classes as $c) {
                        if ($c['id'] == $selectedClass) {
                            $classNameDisplay = $c['name'];
                            break;
                        }
                    }
                    echo $classNameDisplay ? htmlspecialchars($classNameDisplay) . ' - ' : '';
                    echo $monthDisplay;
                    ?>
                </div>
            </div>
        </div>
    </form>

    <!-- Report Table -->
    <?php if ($selectedClass && $selectedStudent && $studentInfo): ?>
        <!-- Individual Student Detailed Report -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    <?= htmlspecialchars($studentInfo['name']) ?> - Monthly Attendance (<?= $monthDisplay ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 0.9rem; color: #666;">Present Days</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #28a745;">
                                <?php 
                                $presentCount = 0;
                                foreach ($report as $status) {
                                    if ($status === 'present') $presentCount++;
                                }
                                echo $presentCount;
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 0.9rem; color: #666;">Absent Days</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #dc3545;">
                                <?php 
                                $absentCount = 0;
                                foreach ($report as $status) {
                                    if ($status === 'absent') $absentCount++;
                                }
                                echo $absentCount;
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 0.9rem; color: #666;">Total Days</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #007bff;">
                                <?php echo $presentCount + $absentCount; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 0.9rem; color: #666;">Percentage</div>
                            <div style="font-size: 2rem; font-weight: 700; color: <?= ($presentCount + $absentCount) > 0 ? (($presentCount / ($presentCount + $absentCount)) * 100 >= 80 ? '#28a745' : '#ffc107') : '#666' ?>;">
                                <?php 
                                $totalDays = $presentCount + $absentCount;
                                $percentage = $totalDays > 0 ? round(($presentCount / $totalDays) * 100, 1) : 0;
                                echo $percentage . '%';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="row">
                    <?php foreach ($daysInMonth as $date):
                        $status = $report[$date] ?? null;
                        $dayOfWeek = date('D', strtotime($date));
                        $dayNum = date('d', strtotime($date));
                        $isWeekend = in_array($dayOfWeek, ['Sat', 'Sun']);
                        
                        if ($status === 'present') {
                            $bgColor = '#d4edda';
                            $borderColor = '#28a745';
                            $icon = '✓';
                            $label = 'Present';
                        } elseif ($status === 'absent') {
                            $bgColor = '#f8d7da';
                            $borderColor = '#dc3545';
                            $icon = '✕';
                            $label = 'Absent';
                        } else {
                            $bgColor = '#f8f9fa';
                            $borderColor = '#ddd';
                            $icon = '-';
                            $label = 'No Data';
                        }
                    ?>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div style="
                                background: <?= $bgColor ?>;
                                border: 2px solid <?= $borderColor ?>;
                                border-radius: 8px;
                                padding: 15px;
                                text-align: center;
                            ">
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">
                                    <?= $dayOfWeek ?>
                                </div>
                                <div style="font-size: 1.8rem; font-weight: 700; color: <?= $borderColor ?>; margin-bottom: 8px;">
                                    <?= $dayNum ?>
                                </div>
                                <div style="font-size: 1.2rem; margin-bottom: 5px;">
                                    <?= $icon ?>
                                </div>
                                <div style="font-size: 0.9rem; color: #333; font-weight: 600;">
                                    <?= $label ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-footer bg-light">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-2"></i>
                    Complete monthly attendance record for <?= htmlspecialchars($studentInfo['name']) ?>
                </small>
            </div>
        </div>

    <?php elseif ($selectedClass && !empty($students) && empty($selectedStudent)): ?>
        <!-- Monthly Summary Statistics -->
        <div class="row mb-4">
            <?php
            $totalClassPresent = 0;
            $totalClassAbsent = 0;
            $totalClassDays = 0;
            
            foreach ($students as $student) {
                $studentReport = $report[$student['id']] ?? [];
                foreach ($studentReport as $status) {
                    if ($status === 'present') $totalClassPresent++;
                    elseif ($status === 'absent') $totalClassAbsent++;
                }
            }
            $totalClassDays = $totalClassPresent + $totalClassAbsent;
            $classPercentage = $totalClassDays > 0 ? round(($totalClassPresent / $totalClassDays) * 100, 1) : 0;
            ?>
            <div class="col-md-3">
                <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 6px;">
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px; text-transform: uppercase; font-weight: 600;">Total Present</div>
                    <div style="font-size: 2.5rem; font-weight: 700; color: #28a745;"><?= $totalClassPresent ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; border-radius: 6px;">
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px; text-transform: uppercase; font-weight: 600;">Total Absent</div>
                    <div style="font-size: 2.5rem; font-weight: 700; color: #dc3545;"><?= $totalClassAbsent ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 20px; border-radius: 6px;">
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px; text-transform: uppercase; font-weight: 600;">Total Days Recorded</div>
                    <div style="font-size: 2.5rem; font-weight: 700; color: #0c5460;"><?= $totalClassDays ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div style="background: <?= $classPercentage >= 80 ? '#d4edda' : '#fff3cd' ?>; border-left: 4px solid <?= $classPercentage >= 80 ? '#28a745' : '#856404' ?>; padding: 20px; border-radius: 6px;">
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px; text-transform: uppercase; font-weight: 600;">Class Avg %</div>
                    <div style="font-size: 2.5rem; font-weight: 700; color: <?= $classPercentage >= 80 ? '#28a745' : '#856404' ?>;"><?= $classPercentage ?>%</div>
                </div>
            </div>
        </div>

        <!-- Class Overview Summary -->
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i>
                    Attendance Summary (<?= count($students) ?> students)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="25%" style="position: sticky; left: 0; z-index: 10; background: #f8f9fa;">
                                    <i class="fas fa-user me-2"></i>Student Name
                                </th>
                                <th width="10%" class="text-center">Present</th>
                                <th width="10%" class="text-center">Absent</th>
                                <th width="10%" class="text-center">Total Days</th>
                                <th width="10%" class="text-center">Percentage</th>
                                <?php foreach (array_slice($daysInMonth, 0, 10) as $date): ?>
                                    <th class="text-center" title="<?= date('F d, Y', strtotime($date)) ?>">
                                        <?= date('d', strtotime($date)) ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student):
                                $studentReport = $report[$student['id']] ?? [];
                                $presentCount = 0;
                                $absentCount = 0;

                                foreach ($studentReport as $status) {
                                    if ($status === 'present') $presentCount++;
                                    elseif ($status === 'absent') $absentCount++;
                                }

                                $totalDays = $presentCount + $absentCount;
                                $percentage = $totalDays > 0 ? round(($presentCount / $totalDays) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td style="position: sticky; left: 0; background: white; z-index: 5;">
                                        <a href="?class_id=<?= $selectedClass ?>&student_id=<?= $student['id'] ?>&month=<?= $selectedMonth ?>" style="text-decoration: none; color: inherit;">
                                            <strong><?= htmlspecialchars($student['name']) ?></strong>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?= $presentCount ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?= $absentCount ?></span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?= $totalDays ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger') ?>">
                                            <?= $percentage ?>%
                                        </span>
                                    </td>
                                    <?php foreach (array_slice($daysInMonth, 0, 10) as $date):
                                        $status = $studentReport[$date] ?? null;
                                        $icon = '';
                                        $class = '';
                                        if ($status === 'present') {
                                            $icon = '✓';
                                            $class = 'bg-success text-white';
                                        } elseif ($status === 'absent') {
                                            $icon = '✕';
                                            $class = 'bg-danger text-white';
                                        }
                                    ?>
                                        <td class="text-center">
                                            <?php if ($icon): ?>
                                                <span class="badge <?= $class ?>"><?= $icon ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-2"></i>
                    Showing first 10 days of <?= $monthDisplay ?>. Click on a student name to view their detailed attendance.
                </small>
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
            Select a class to view attendance. Optionally select a specific student to see their detailed monthly attendance record.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
