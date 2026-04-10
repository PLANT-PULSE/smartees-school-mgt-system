<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Attendance';
$pdo = getDb();

$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedDate = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId = intval($_POST['class_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $statuses = $_POST['status'] ?? [];

    foreach ($statuses as $studentId => $status) {
        $stmt = $pdo->prepare('REPLACE INTO attendance (student_id, class_id, `date`, status) VALUES (:student_id, :class_id, :date, :status)');
        $stmt->execute([':student_id' => $studentId, ':class_id' => $classId, ':date' => $date, ':status' => $status]);
    }

    flash('success', 'Attendance saved.');
    redirect("attendance.php?class_id={$classId}&date={$date}");
}

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();

$students = [];
if ($selectedClass) {
    $stmt = $pdo->prepare('SELECT s.* FROM students s WHERE s.class_id = :class_id ORDER BY s.name');
    $stmt->execute([':class_id' => $selectedClass]);
    $students = $stmt->fetchAll();

    $attendanceStmt = $pdo->prepare('SELECT student_id, status FROM attendance WHERE class_id = :class_id AND `date` = :date');
    $attendanceStmt->execute([':class_id' => $selectedClass, ':date' => $selectedDate]);

    $attendanceMap = [];
    foreach ($attendanceStmt->fetchAll() as $row) {
        $attendanceMap[$row['student_id']] = $row['status'];
    }
} else {
    $attendanceMap = [];
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<!-- Page Header -->
<div class="table-header">
    <div>
        <h1 class="table-title">
            <i class="fas fa-calendar-check me-3 text-primary"></i>Attendance Management
        </h1>
        <p class="text-muted mb-0">Track and manage student attendance records</p>
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

<!-- Class and Date Selection -->
<div class="form-container">
    <div class="form-header">
        <h2 class="form-title">
            <i class="fas fa-filter me-2"></i>Select Class & Date
        </h2>
        <p class="form-subtitle">Choose a class and date to manage attendance</p>
    </div>

    <form method="get" class="animate-fade-in">
        <div class="row">
            <div class="col-md-5">
                <div class="form-group">
                    <label class="form-label" for="class_id">
                        <i class="fas fa-graduation-cap me-2"></i>Select Class *
                    </label>
                    <select name="class_id" id="class_id" class="form-control" required>
                        <option value="">Choose a class...</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>" <?= $cls['id'] == $selectedClass ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group">
                    <label class="form-label" for="date">
                        <i class="fas fa-calendar-alt me-2"></i>Select Date *
                    </label>
                    <input type="date" id="date" name="date" class="form-control"
                           value="<?= htmlspecialchars($selectedDate) ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary-custom w-100">
                        <i class="fas fa-search me-2"></i>Load Attendance
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php if ($selectedClass): ?>
    <!-- Attendance Form -->
    <div class="table-container">
        <div class="table-header">
            <div>
                <h2 class="table-title">
                    <i class="fas fa-users me-3 text-success"></i>
                    Attendance for <?= htmlspecialchars($selectedDate) ?>
                </h2>
                <p class="text-muted mb-0">
                    <?php
                    $className = '';
                    foreach ($classes as $cls) {
                        if ($cls['id'] == $selectedClass) {
                            $className = $cls['name'];
                            break;
                        }
                    }
                    echo htmlspecialchars($className);
                    ?> - <?= count($students) ?> students
                </p>
            </div>
        </div>

        <?php if (!empty($students)): ?>
            <form method="post" id="attendanceForm">
                <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
                <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">

                <div class="table-responsive">
                    <table class="table attendance-table">
                        <thead>
                            <tr>
                                <th>
                                    <i class="fas fa-hashtag me-2"></i>#
                                </th>
                                <th>
                                    <i class="fas fa-user me-2"></i>Student Name
                                </th>
                                <th class="text-center">
                                    <i class="fas fa-check-circle me-2 text-success"></i>Present
                                </th>
                                <th class="text-center">
                                    <i class="fas fa-times-circle me-2 text-danger"></i>Absent
                                </th>
                                <th class="text-center">
                                    <i class="fas fa-clock me-2 text-warning"></i>Late
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student):
                                $status = $attendanceMap[$student['id']] ?? 'present';
                            ?>
                                <tr style="animation-delay: <?= $index * 0.03 ?>s">
                                    <td>
                                        <span class="badge badge-info-custom">#<?= $index + 1 ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle-sm me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($student['name']) ?></strong>
                                                <div class="text-muted small">Student ID: <?= htmlspecialchars($student['id']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check">
                                            <input type="radio" name="status[<?= $student['id'] ?>]" value="present"
                                                   class="form-check-input attendance-radio present-radio"
                                                   <?= $status === 'present' ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check">
                                            <input type="radio" name="status[<?= $student['id'] ?>]" value="absent"
                                                   class="form-check-input attendance-radio absent-radio"
                                                   <?= $status === 'absent' ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check">
                                            <input type="radio" name="status[<?= $student['id'] ?>]" value="late"
                                                   class="form-check-input attendance-radio late-radio"
                                                   <?= $status === 'late' ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="attendance-summary">
                        <span class="badge badge-info-custom me-3">
                            <i class="fas fa-users me-1"></i>
                            Total Students: <span id="totalStudents"><?= count($students) ?></span>
                        </span>
                        <span class="badge badge-success-custom me-3">
                            <i class="fas fa-check-circle me-1"></i>
                            Present: <span id="presentCount">0</span>
                        </span>
                        <span class="badge badge-danger-custom me-3">
                            <i class="fas fa-times-circle me-1"></i>
                            Absent: <span id="absentCount">0</span>
                        </span>
                        <span class="badge badge-warning-custom">
                            <i class="fas fa-clock me-1"></i>
                            Late: <span id="lateCount">0</span>
                        </span>
                    </div>

                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-secondary-custom" onclick="markAllPresent()">
                            <i class="fas fa-check-double me-2"></i>Mark All Present
                        </button>
                        <button type="submit" class="btn btn-primary-custom btn-submit">
                            <i class="fas fa-save me-2"></i>Save Attendance
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No Students Found</h3>
                <p>This class doesn't have any enrolled students yet.</p>
                <a href="students.php" class="btn btn-primary-custom">
                    <i class="fas fa-plus me-2"></i>Add Students
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Additional CSS for this page -->
<style>
.attendance-table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.attendance-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    border: none;
    padding: 16px;
}

.attendance-table td {
    padding: 16px;
    border-bottom: 1px solid #f1f3f4;
}

.attendance-table tbody tr {
    animation: fadeInUp 0.6s ease-out both;
    transition: all 0.3s ease;
}

.attendance-table tbody tr:hover {
    background-color: #f8f9ff;
    transform: translateY(-1px);
}

.avatar-circle-sm {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
}

.attendance-radio {
    width: 20px;
    height: 20px;
    margin: 0 auto;
    cursor: pointer;
    transition: all 0.3s ease;
}

.attendance-radio:checked {
    transform: scale(1.2);
}

.present-radio:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.absent-radio:checked {
    background-color: #dc3545;
    border-color: #dc3545;
}

.late-radio:checked {
    background-color: #ffc107;
    border-color: #ffc107;
}

.attendance-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.form-check {
    margin: 0;
}

.table-responsive {
    border-radius: 12px;
    overflow: hidden;
}
</style>

<!-- Additional JavaScript for this page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update attendance counts
    function updateCounts() {
        const presentRadios = document.querySelectorAll('.present-radio:checked');
        const absentRadios = document.querySelectorAll('.absent-radio:checked');
        const lateRadios = document.querySelectorAll('.late-radio:checked');

        document.getElementById('presentCount').textContent = presentRadios.length;
        document.getElementById('absentCount').textContent = absentRadios.length;
        document.getElementById('lateCount').textContent = lateRadios.length;
    }

    // Initial count update
    updateCounts();

    // Update counts when radio buttons change
    document.querySelectorAll('.attendance-radio').forEach(radio => {
        radio.addEventListener('change', updateCounts);
    });

    // Auto-select present for unchecked students
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const studentId = this.name.match(/status\[(\d+)\]/)[1];
            const studentRadios = document.querySelectorAll(`input[name="status[${studentId}]"]`);

            // Ensure only one radio is checked per student
            studentRadios.forEach(r => {
                if (r !== this) r.checked = false;
            });
        });
    });
});

function markAllPresent() {
    document.querySelectorAll('.present-radio').forEach(radio => {
        radio.checked = true;
    });
    document.querySelectorAll('.absent-radio, .late-radio').forEach(radio => {
        radio.checked = false;
    });

    // Update counts
    const totalStudents = document.getElementById('totalStudents').textContent;
    document.getElementById('presentCount').textContent = totalStudents;
    document.getElementById('absentCount').textContent = '0';
    document.getElementById('lateCount').textContent = '0';
}

// Form validation
document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
    const radios = document.querySelectorAll('input[type="radio"]:checked');
    if (radios.length === 0) {
        e.preventDefault();
        alert('Please mark attendance for at least one student.');
        return false;
    }
});
</script>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
