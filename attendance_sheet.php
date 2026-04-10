<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Comprehensive Attendance Sheet';
$pdo = getDb();

$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedMonth = $_GET['month'] ?? date('Y-m');
$viewType = $_GET['view_type'] ?? 'monthly'; // monthly or semester

// Get classes
$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();

// Define semesters
$semesters = [
    'Semester 1' => ['start' => '2026-01-01', 'end' => '2026-04-30'],
    'Semester 2' => ['start' => '2026-05-01', 'end' => '2026-08-31'],
    'Semester 3' => ['start' => '2026-09-01', 'end' => '2026-12-31']
];

// Prepare data
$students = [];
$attendanceData = [];
$classInfo = null;
$daysInMonth = [];
$periodDisplay = '';
$startDate = '';
$endDate = '';

if ($selectedClass) {
    // Get class info
    $stmt = $pdo->prepare('SELECT id, name FROM classes WHERE id = ?');
    $stmt->execute([$selectedClass]);
    $classInfo = $stmt->fetch();

    // Get students
    $stmt = $pdo->prepare('SELECT id, name FROM students WHERE class_id = ? ORDER BY name');
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();

    if ($viewType === 'semester') {
        // Get semester dates
        $semesterKey = array_keys($semesters)[$_GET['semester_index'] ?? 0] ?? 'Semester 1';
        $semesterDates = $semesters[$semesterKey];
        $startDate = $semesterDates['start'];
        $endDate = $semesterDates['end'];
        $periodDisplay = $semesterKey . ' (' . date('M d', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
    } else {
        // Get monthly dates
        $monthDate = DateTime::createFromFormat('Y-m', $selectedMonth);
        $startDate = $monthDate->format('Y-m-01');
        $endDate = $monthDate->format('Y-m-t');
        $periodDisplay = $monthDate->format('F Y');
    }

    // Get all dates for the period
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);
    while ($current <= $end) {
        $daysInMonth[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }

    // Get attendance records
    $stmt = $pdo->prepare('
        SELECT student_id, date, status FROM attendance
        WHERE class_id = ? AND date BETWEEN ? AND ?
        ORDER BY date
    ');
    $stmt->execute([$selectedClass, $startDate, $endDate]);

    foreach ($stmt->fetchAll() as $row) {
        if (!isset($attendanceData[$row['student_id']])) {
            $attendanceData[$row['student_id']] = [];
        }
        $attendanceData[$row['student_id']][$row['date']] = $row['status'];
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<style>
    .attendance-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .attendance-table thead th {
        background: #333;
        color: white;
        padding: 8px 4px;
        text-align: center;
        font-weight: 600;
        border: 1px solid #ddd;
        white-space: nowrap;
    }

    .attendance-table tbody td {
        padding: 8px 4px;
        border: 1px solid #ddd;
        text-align: center;
    }

    .attendance-table tbody tr:hover {
        background: #f8f9fa;
    }

    .student-name-col {
        text-align: left;
        font-weight: 500;
        min-width: 150px;
    }

    .present-status {
        background: #d4edda;
        color: #155724;
        font-weight: 600;
    }

    .absent-status {
        background: #f8d7da;
        color: #721c24;
        font-weight: 600;
    }

    .total-col {
        background: #e7f3ff;
        color: #004085;
        font-weight: 700;
    }

    @media print {
        .filter-section {
            display: none;
        }
        .print-btn {
            display: none;
        }
        body {
            background: white;
        }
        .attendance-table {
            font-size: 0.75rem;
        }
        .attendance-table thead th {
            padding: 4px 2px;
        }
        .attendance-table tbody td {
            padding: 4px 2px;
        }
    }

    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 6px;
        text-align: center;
        border: 1px solid #ddd;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 5px;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #333;
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-sheet-icon me-2"></i>Comprehensive Attendance Sheet
    </h1>

    <!-- Filter Section -->
    <div class="filter-section" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filters</h5>
        <div class="row">
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Class/Department</label>
                <select name="class_id" class="form-control" onchange="
                    const url = new URL(window.location);
                    url.searchParams.set('class_id', this.value);
                    url.searchParams.set('view_type', document.querySelector('select[name=view_type]').value);
                    if (document.querySelector('select[name=view_type]').value === 'monthly') {
                        url.searchParams.set('month', document.getElementById('monthInput').value);
                    }
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

            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-calendar me-2"></i>View Type</label>
                <select name="view_type" class="form-control" onchange="
                    const url = new URL(window.location);
                    url.searchParams.set('class_id', document.querySelector('select[name=class_id]').value);
                    url.searchParams.set('view_type', this.value);
                    if (this.value === 'monthly') {
                        url.searchParams.set('month', document.getElementById('monthInput').value);
                        url.searchParams.delete('semester_index');
                    } else {
                        url.searchParams.set('semester_index', 0);
                        url.searchParams.delete('month');
                    }
                    window.location = url.toString();
                ">
                    <option value="monthly" <?= $viewType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="semester" <?= $viewType === 'semester' ? 'selected' : '' ?>>Semester</option>
                </select>
            </div>

            <?php if ($viewType === 'monthly'): ?>
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar-alt me-2"></i>Month</label>
                    <input type="month" id="monthInput" class="form-control" value="<?= $selectedMonth ?>" onchange="
                        const url = new URL(window.location);
                        url.searchParams.set('class_id', document.querySelector('select[name=class_id]').value);
                        url.searchParams.set('month', this.value);
                        url.searchParams.set('view_type', 'monthly');
                        window.location = url.toString();
                    ">
                </div>
            <?php else: ?>
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Semester</label>
                    <select class="form-control" onchange="
                        const url = new URL(window.location);
                        url.searchParams.set('class_id', document.querySelector('select[name=class_id]').value);
                        url.searchParams.set('view_type', 'semester');
                        url.searchParams.set('semester_index', this.value);
                        window.location = url.toString();
                    ">
                        <option value="0">Semester 1</option>
                        <option value="1">Semester 2</option>
                        <option value="2">Semester 3</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100 print-btn" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Sheet
                </button>
            </div>
        </div>
    </div>

    <?php if ($selectedClass && !empty($students)): ?>
        <!-- Summary Statistics -->
        <div class="summary-stats">
            <div class="stat-card">
                <div class="stat-label">Total Students</div>
                <div class="stat-value"><?= count($students) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Period</div>
                <div class="stat-value" style="font-size: 1.2rem;"><?= $periodDisplay ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Days</div>
                <div class="stat-value"><?= count($daysInMonth) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Class/Department</div>
                <div class="stat-value" style="font-size: 1.2rem;"><?= htmlspecialchars($classInfo['name']) ?></div>
            </div>
        </div>

        <!-- Attendance Table -->
        <div style="overflow-x: auto; background: white; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1);">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th width="3%" style="position: sticky; left: 0; z-index: 20;">S.No</th>
                        <th width="12%" style="position: sticky; left: 4%; z-index: 20; text-align: left;">Name</th>
                        <th width="8%">ID Number</th>
                        
                        <!-- Day columns (1-31) -->
                        <?php foreach ($daysInMonth as $date): ?>
                            <th width="2%" title="<?= date('l, F d, Y', strtotime($date)) ?>">
                                <?= date('d', strtotime($date)) ?>
                            </th>
                        <?php endforeach; ?>
                        
                        <th width="5%">Total<br>Present</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $serialNo = 1; ?>
                    <?php foreach ($students as $student): ?>
                        <?php 
                        $studentRecord = $attendanceData[$student['id']] ?? [];
                        $totalPresent = 0;
                        
                        foreach ($daysInMonth as $date) {
                            if (($studentRecord[$date] ?? null) === 'present') {
                                $totalPresent++;
                            }
                        }
                        ?>
                        <tr>
                            <td style="position: sticky; left: 0; background: white; z-index: 10; font-weight: 600;">
                                <?= $serialNo++ ?>
                            </td>
                            <td style="position: sticky; left: 4%; background: white; z-index: 10;" class="student-name-col">
                                <?= htmlspecialchars($student['name']) ?>
                            </td>
                            <td>#<?= $student['id'] ?></td>
                            
                            <!-- Day attendance status -->
                            <?php foreach ($daysInMonth as $date): ?>
                                <?php $status = $studentRecord[$date] ?? null; ?>
                                <td class="<?= $status === 'present' ? 'present-status' : ($status === 'absent' ? 'absent-status' : '') ?>">
                                    <?php 
                                    if ($status === 'present') {
                                        echo 'P';
                                    } elseif ($status === 'absent') {
                                        echo 'A';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            
                            <td class="total-col"><?= $totalPresent ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
            <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Legend</h6>
            <div class="row">
                <div class="col-md-6">
                    <small><span class="badge bg-success">P</span> = Present</small>
                </div>
                <div class="col-md-6">
                    <small><span class="badge bg-danger">A</span> = Absent</small>
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
            Select a class/department and view type to generate a comprehensive attendance sheet. You can view attendance by month or semester.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
