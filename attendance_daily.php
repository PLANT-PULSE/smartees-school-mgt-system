<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Daily Attendance';
$pdo = getDb();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedClass = intval($_GET['class_id'] ?? 0);

// Get classes
$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();

// Get attendance for selected date and class
$attendance = [];
$students = [];

if ($selectedClass) {
    // Get students in the class
    $stmt = $pdo->prepare('SELECT id, name FROM students WHERE class_id = ? ORDER BY name');
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();

    // Get attendance records
    $stmt = $pdo->prepare('
        SELECT student_id, status FROM attendance 
        WHERE class_id = ? AND date = ?
    ');
    $stmt->execute([$selectedClass, $selectedDate]);
    foreach ($stmt->fetchAll() as $row) {
        $attendance[$row['student_id']] = $row['status'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId = intval($_POST['class_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $statusData = $_POST['status'] ?? [];

    try {
        $pdo->beginTransaction();

        // Delete existing records for this date/class
        $stmt = $pdo->prepare('DELETE FROM attendance WHERE class_id = ? AND date = ?');
        $stmt->execute([$classId, $date]);

        // Insert new attendance records
        $stmt = $pdo->prepare('
            INSERT INTO attendance (student_id, class_id, date, status)
            VALUES (?, ?, ?, ?)
        ');

        foreach ($statusData as $studentId => $status) {
            if (!empty($status)) {
                $stmt->execute([$studentId, $classId, $date, $status]);
            }
        }

        $pdo->commit();
        
        // Set success message and refresh page
        $_SESSION['success'] = 'Attendance saved successfully!';
        header("Location: attendance_daily.php?class_id={$classId}&date={$date}");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error saving attendance: ' . $e->getMessage();
        header("Location: attendance_daily.php?class_id={$classId}&date={$date}");
        exit();
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-calendar-check me-2"></i>Daily Attendance
    </h1>

    <!-- Success/Error Messages -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Select Class</label>
                <form method="get" id="filterForm">
                    <select name="class_id" class="form-control" onchange="
                        document.getElementById('dateInput').name = 'date';
                        document.getElementById('filterForm').submit();
                    ">
                        <option value="">Choose a class...</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>" <?= $cls['id'] == $selectedClass ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-calendar me-2"></i>Select Date</label>
                <input type="date" id="dateInput" class="form-control" value="<?= $selectedDate ?>" onchange="
                    const url = new URL(window.location);
                    url.searchParams.set('class_id', document.querySelector('select[name=class_id]').value);
                    url.searchParams.set('date', this.value);
                    window.location = url.toString();
                ">
            </div>
        </div>
        <div class="col-md-6">
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
                    echo date('F d, Y', strtotime($selectedDate));
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <?php if ($selectedClass && !empty($students)): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Mark Attendance (<?= count($students) ?> students)
                </h5>
            </div>
            <div class="card-body p-0">
                <form method="post">
                    <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
                    <input type="hidden" name="date" value="<?= $selectedDate ?>">

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="45%">Student Name</th>
                                    <th width="55%">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student):
                                    $currentStatus = $attendance[$student['id']] ?? 'present';
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($student['name']) ?></strong>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <input type="radio" class="btn-check" name="status[<?= $student['id'] ?>]" 
                                                       id="present_<?= $student['id'] ?>" value="present"
                                                       <?= $currentStatus === 'present' ? 'checked' : '' ?> required>
                                                <label class="btn btn-outline-success btn-sm" for="present_<?= $student['id'] ?>">
                                                    <i class="fas fa-check me-1"></i>Present
                                                </label>

                                                <input type="radio" class="btn-check" name="status[<?= $student['id'] ?>]" 
                                                       id="absent_<?= $student['id'] ?>" value="absent"
                                                       <?= $currentStatus === 'absent' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-danger btn-sm" for="absent_<?= $student['id'] ?>">
                                                    <i class="fas fa-times me-1"></i>Absent
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-light d-flex justify-content-between">
                        <a href="attendance_daily.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-2"></i>Save Attendance
                        </button>
                    </div>
                </form>
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
            Select a class to view and manage attendance.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
