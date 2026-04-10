<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Grades';
$pdo = getDb();

$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedSubject = intval($_GET['subject_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId = intval($_POST['class_id'] ?? 0);
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $grades = $_POST['grade'] ?? [];
    $remarks = $_POST['remark'] ?? [];

    foreach ($grades as $studentId => $gradeValue) {
        $gradeValue = trim($gradeValue);
        $remarkText = trim($remarks[$studentId] ?? '');

        if ($gradeValue === '' && $remarkText === '') {
            // no grade/remark provided: delete existing
            $stmt = $pdo->prepare('DELETE FROM grades WHERE student_id = :student_id AND class_id = :class_id AND subject_id = :subject_id');
            $stmt->execute([':student_id' => $studentId, ':class_id' => $classId, ':subject_id' => $subjectId]);
            continue;
        }

        $stmt = $pdo->prepare('REPLACE INTO grades (student_id, class_id, subject_id, grade, remark) VALUES (:student_id, :class_id, :subject_id, :grade, :remark)');
        $stmt->execute([
            ':student_id' => $studentId,
            ':class_id' => $classId,
            ':subject_id' => $subjectId,
            ':grade' => $gradeValue,
            ':remark' => $remarkText,
        ]);
    }

    flash('success', 'Grades saved.');
    redirect("grades.php?class_id={$classId}&subject_id={$subjectId}");
}

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();
$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();

$students = [];
$gradesMap = [];

if ($selectedClass && $selectedSubject) {
    $stmt = $pdo->prepare('SELECT s.* FROM students s WHERE s.class_id = :class_id ORDER BY s.name');
    $stmt->execute([':class_id' => $selectedClass]);
    $students = $stmt->fetchAll();

    $gradeStmt = $pdo->prepare('SELECT student_id, grade, remark FROM grades WHERE class_id = :class_id AND subject_id = :subject_id');
    $gradeStmt->execute([':class_id' => $selectedClass, ':subject_id' => $selectedSubject]);
    foreach ($gradeStmt->fetchAll() as $row) {
        $gradesMap[$row['student_id']] = $row;
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<!-- Page Header -->
<div class="table-header">
    <div>
        <h1 class="table-title">
            <i class="fas fa-graduation-cap me-3 text-primary"></i>Grade Management
        </h1>
        <p class="text-muted mb-0">Record and manage student grades and performance</p>
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

<!-- Class and Subject Selection -->
<div class="form-container">
    <div class="form-header">
        <h2 class="form-title">
            <i class="fas fa-filter me-2"></i>Select Class & Subject
        </h2>
        <p class="form-subtitle">Choose a class and subject to manage grades</p>
    </div>

    <form method="get" class="animate-fade-in">
        <div class="row">
            <div class="col-md-4">
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
                    <label class="form-label" for="subject_id">
                        <i class="fas fa-book me-2"></i>Select Subject *
                    </label>
                    <select name="subject_id" id="subject_id" class="form-control" required>
                        <option value="">Choose a subject...</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>" <?= $sub['id'] == $selectedSubject ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sub['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-custom flex-fill">
                            <i class="fas fa-search me-2"></i>Load Grades
                        </button>
                        <a href="grades.php" class="btn btn-secondary-custom">
                            <i class="fas fa-times me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php if ($selectedClass && $selectedSubject): ?>
    <!-- Grades Form -->
    <div class="table-container">
        <div class="table-header">
            <div>
                <h2 class="table-title">
                    <i class="fas fa-edit me-3 text-success"></i>
                    Enter Grades
                </h2>
                <p class="text-muted mb-0">
                    <?php
                    $className = '';
                    $subjectName = '';
                    foreach ($classes as $cls) {
                        if ($cls['id'] == $selectedClass) {
                            $className = $cls['name'];
                            break;
                        }
                    }
                    foreach ($subjects as $sub) {
                        if ($sub['id'] == $selectedSubject) {
                            $subjectName = $sub['name'];
                            break;
                        }
                    }
                    echo htmlspecialchars($className) . ' - ' . htmlspecialchars($subjectName);
                    ?> - <?= count($students) ?> students
                </p>
            </div>
        </div>

        <?php if (!empty($students)): ?>
            <form method="post" id="gradesForm">
                <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
                <input type="hidden" name="subject_id" value="<?= $selectedSubject ?>">

                <div class="table-responsive">
                    <table class="table grades-table">
                        <thead>
                            <tr>
                                <th>
                                    <i class="fas fa-hashtag me-2"></i>#
                                </th>
                                <th>
                                    <i class="fas fa-user me-2"></i>Student Name
                                </th>
                                <th>
                                    <i class="fas fa-star me-2"></i>Grade
                                </th>
                                <th>
                                    <i class="fas fa-comment me-2"></i>Remarks
                                </th>
                                <th>
                                    <i class="fas fa-chart-line me-2"></i>Grade Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $idx => $student):
                                $existing = $gradesMap[$student['id']] ?? ['grade' => '', 'remark' => ''];
                                $gradeValue = $existing['grade'];
                                $gradeClass = '';
                                $gradeIcon = 'fas fa-question-circle';
                                $gradeText = 'Not Graded';

                                if (!empty($gradeValue)) {
                                    $numericGrade = is_numeric($gradeValue) ? floatval($gradeValue) : 0;
                                    if ($numericGrade >= 90) {
                                        $gradeClass = 'grade-excellent';
                                        $gradeIcon = 'fas fa-trophy';
                                        $gradeText = 'Excellent';
                                    } elseif ($numericGrade >= 80) {
                                        $gradeClass = 'grade-good';
                                        $gradeIcon = 'fas fa-check-circle';
                                        $gradeText = 'Good';
                                    } elseif ($numericGrade >= 70) {
                                        $gradeClass = 'grade-average';
                                        $gradeIcon = 'fas fa-minus-circle';
                                        $gradeText = 'Average';
                                    } elseif ($numericGrade >= 60) {
                                        $gradeClass = 'grade-poor';
                                        $gradeIcon = 'fas fa-exclamation-triangle';
                                        $gradeText = 'Poor';
                                    } else {
                                        $gradeClass = 'grade-fail';
                                        $gradeIcon = 'fas fa-times-circle';
                                        $gradeText = 'Fail';
                                    }
                                }
                            ?>
                                <tr style="animation-delay: <?= $idx * 0.03 ?>s" class="<?= $gradeClass ?>">
                                    <td>
                                        <span class="badge badge-info-custom">#<?= $idx + 1 ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle-sm me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                <i class="fas fa-user-graduate"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($student['name']) ?></strong>
                                                <div class="text-muted small">ID: <?= htmlspecialchars($student['id']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="grade[<?= $student['id'] ?>]"
                                               value="<?= htmlspecialchars($existing['grade']) ?>"
                                               class="form-control grade-input"
                                               placeholder="e.g., 85, A-, Pass"
                                               maxlength="10">
                                    </td>
                                    <td>
                                        <input type="text" name="remark[<?= $student['id'] ?>]"
                                               value="<?= htmlspecialchars($existing['remark']) ?>"
                                               class="form-control remark-input"
                                               placeholder="Optional remarks..."
                                               maxlength="255">
                                    </td>
                                    <td>
                                        <div class="grade-status">
                                            <i class="<?= $gradeIcon ?> me-2"></i>
                                            <span class="grade-text"><?= $gradeText ?></span>
                                            <?php if (!empty($gradeValue)): ?>
                                                <span class="grade-value ms-2 badge badge-secondary-custom">
                                                    <?= htmlspecialchars($gradeValue) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="grade-summary">
                        <span class="badge badge-info-custom me-3">
                            <i class="fas fa-users me-1"></i>
                            Total Students: <span id="totalStudents"><?= count($students) ?></span>
                        </span>
                        <span class="badge badge-success-custom me-3">
                            <i class="fas fa-check-circle me-1"></i>
                            Graded: <span id="gradedCount">0</span>
                        </span>
                        <span class="badge badge-warning-custom">
                            <i class="fas fa-clock me-1"></i>
                            Pending: <span id="pendingCount">0</span>
                        </span>
                    </div>

                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-secondary-custom" onclick="clearAllGrades()">
                            <i class="fas fa-eraser me-2"></i>Clear All
                        </button>
                        <button type="submit" class="btn btn-primary-custom btn-submit">
                            <i class="fas fa-save me-2"></i>Save Grades
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
.grades-table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.grades-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    border: none;
    padding: 16px;
}

.grades-table td {
    padding: 16px;
    border-bottom: 1px solid #f1f3f4;
}

.grades-table tbody tr {
    animation: fadeInUp 0.6s ease-out both;
    transition: all 0.3s ease;
}

.grades-table tbody tr:hover {
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

.grade-input, .remark-input {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.grade-input:focus, .remark-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    outline: none;
}

.grade-status {
    display: flex;
    align-items: center;
    font-weight: 500;
}

.grade-excellent {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(34, 197, 94, 0.05));
}

.grade-good {
    background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(0, 123, 255, 0.05));
}

.grade-average {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));
}

.grade-poor {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(255, 152, 0, 0.05));
}

.grade-fail {
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
}

.grade-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.table-responsive {
    border-radius: 12px;
    overflow: hidden;
}
</style>

<!-- Additional JavaScript for this page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update grade counts and status
    function updateGradeStatus() {
        const gradeInputs = document.querySelectorAll('.grade-input');
        let gradedCount = 0;

        gradeInputs.forEach((input, index) => {
            const row = input.closest('tr');
            const statusCell = row.querySelector('.grade-status');
            const gradeText = statusCell.querySelector('.grade-text');
            const gradeValue = statusCell.querySelector('.grade-value');
            const icon = statusCell.querySelector('i');

            const value = input.value.trim();
            let gradeClass = '';
            let gradeIcon = 'fas fa-question-circle';
            let gradeStatusText = 'Not Graded';

            if (value !== '') {
                gradedCount++;
                const numericGrade = isNaN(value) ? 0 : parseFloat(value);

                if (numericGrade >= 90) {
                    gradeClass = 'grade-excellent';
                    gradeIcon = 'fas fa-trophy';
                    gradeStatusText = 'Excellent';
                } else if (numericGrade >= 80) {
                    gradeClass = 'grade-good';
                    gradeIcon = 'fas fa-check-circle';
                    gradeStatusText = 'Good';
                } else if (numericGrade >= 70) {
                    gradeClass = 'grade-average';
                    gradeIcon = 'fas fa-minus-circle';
                    gradeStatusText = 'Average';
                } else if (numericGrade >= 60) {
                    gradeClass = 'grade-poor';
                    gradeIcon = 'fas fa-exclamation-triangle';
                    gradeStatusText = 'Poor';
                } else {
                    gradeClass = 'grade-fail';
                    gradeIcon = 'fas fa-times-circle';
                    gradeStatusText = 'Fail';
                }

                // Update grade value badge
                if (gradeValue) {
                    gradeValue.textContent = value;
                    gradeValue.style.display = 'inline-block';
                } else {
                    const badge = document.createElement('span');
                    badge.className = 'grade-value ms-2 badge badge-secondary-custom';
                    badge.textContent = value;
                    statusCell.appendChild(badge);
                }
            } else {
                if (gradeValue) gradeValue.style.display = 'none';
            }

            // Update row class
            row.className = row.className.replace(/grade-\w+/g, '').trim() + ' ' + gradeClass;

            // Update status text and icon
            gradeText.textContent = gradeStatusText;
            icon.className = gradeIcon + ' me-2';
        });

        // Update summary counts
        const totalStudents = gradeInputs.length;
        const pendingCount = totalStudents - gradedCount;

        document.getElementById('gradedCount').textContent = gradedCount;
        document.getElementById('pendingCount').textContent = pendingCount;
    }

    // Initial status update
    updateGradeStatus();

    // Update status when grade inputs change
    document.querySelectorAll('.grade-input').forEach(input => {
        input.addEventListener('input', updateGradeStatus);
    });
});

function clearAllGrades() {
    if (confirm('Are you sure you want to clear all grades and remarks? This action cannot be undone.')) {
        document.querySelectorAll('.grade-input, .remark-input').forEach(input => {
            input.value = '';
        });
        // Trigger status update
        document.querySelectorAll('.grade-input').forEach(input => {
            input.dispatchEvent(new Event('input'));
        });
    }
}

// Form validation
document.getElementById('gradesForm')?.addEventListener('submit', function(e) {
    const gradeInputs = document.querySelectorAll('.grade-input');
    let hasData = false;

    gradeInputs.forEach(input => {
        if (input.value.trim() !== '') {
            hasData = true;
        }
    });

    if (!hasData) {
        e.preventDefault();
        alert('Please enter at least one grade before saving.');
        return false;
    }
});
</script>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
