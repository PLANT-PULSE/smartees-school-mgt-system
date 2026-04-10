<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Grades Report';
$pdo = getDb();

// First, ensure grades table has sba_score and exam_score columns
try {
    $pdo->exec("ALTER TABLE grades ADD COLUMN IF NOT EXISTS sba_score DECIMAL(5,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE grades ADD COLUMN IF NOT EXISTS exam_score DECIMAL(5,2) DEFAULT NULL");
} catch (Exception $e) {
    // Columns may already exist
}

$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedStudent = intval($_GET['student_id'] ?? 0);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $classId = intval($_POST['class_id'] ?? 0);
        $studentId = intval($_POST['student_id'] ?? 0);
        $grades = $_POST['grades'] ?? [];
        $remarks = $_POST['remarks'] ?? [];

        foreach ($grades as $subjectId => $scoreData) {
            $sbaScore = !empty($scoreData['sba']) ? floatval($scoreData['sba']) : null;
            $examScore = !empty($scoreData['exam']) ? floatval($scoreData['exam']) : null;
            $remark = trim($remarks[$subjectId] ?? '');

            // Calculate total: (SBA * 50 / 100) + (EXAM * 50 / 100) if both scores exist
            $totalScore = null;
            if ($sbaScore !== null && $examScore !== null) {
                $totalScore = ($sbaScore * 0.5) + ($examScore * 0.5);
            }

            // Determine grade based on total score
            $grade = 'N/A';
            if ($totalScore !== null) {
                if ($totalScore >= 90) $grade = 'A';
                elseif ($totalScore >= 80) $grade = 'B';
                elseif ($totalScore >= 70) $grade = 'C';
                elseif ($totalScore >= 60) $grade = 'D';
                elseif ($totalScore >= 50) $grade = 'E';
                else $grade = 'F';
            }

            // Update or insert grade record
            $stmt = $pdo->prepare('
                REPLACE INTO grades (student_id, class_id, subject_id, sba_score, exam_score, grade, remark)
                VALUES (:student_id, :class_id, :subject_id, :sba_score, :exam_score, :grade, :remark)
            ');
            $stmt->execute([
                ':student_id' => $studentId,
                ':class_id' => $classId,
                ':subject_id' => $subjectId,
                ':sba_score' => $sbaScore,
                ':exam_score' => $examScore,
                ':grade' => $grade,
                ':remark' => $remark
            ]);
        }

        flash('success', 'Grades saved successfully!');
        redirect("grades_report.php?class_id={$classId}&student_id={$studentId}");
    } catch (Exception $e) {
        flash('error', 'Error saving grades: ' . $e->getMessage());
    }
}

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();
$students = [];
$subjects = [];
$gradesData = [];

if ($selectedClass) {
    // Get students in the selected class
    $stmt = $pdo->prepare('SELECT id, name FROM students WHERE class_id = :class_id ORDER BY name');
    $stmt->execute([':class_id' => $selectedClass]);
    $students = $stmt->fetchAll();

    // Get subjects for the class
    $stmt = $pdo->prepare('
        SELECT DISTINCT s.id, s.name
        FROM subjects s
        JOIN class_subjects cs ON s.id = cs.subject_id
        WHERE cs.class_id = :class_id
        ORDER BY s.name
    ');
    $stmt->execute([':class_id' => $selectedClass]);
    $subjects = $stmt->fetchAll();

    // If no subjects linked to class, get all subjects
    if (empty($subjects)) {
        $subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();
    }

    // Get grades for the selected student
    if ($selectedStudent) {
        $stmt = $pdo->prepare('
            SELECT subject_id, sba_score, exam_score, grade, remark
            FROM grades
            WHERE student_id = :student_id AND class_id = :class_id
        ');
        $stmt->execute([':student_id' => $selectedStudent, ':class_id' => $selectedClass]);
        foreach ($stmt->fetchAll() as $row) {
            $gradesData[$row['subject_id']] = $row;
        }
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-chart-bar me-2"></i>Grades Report Card
    </h1>

    <!-- Selection Filters -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Select Class</label>
                <form method="get" id="classForm">
                    <select name="class_id" class="form-control" onchange="
                        const classId = this.value;
                        window.location.href = classId ? 'grades_report.php?class_id=' + classId : 'grades_report.php';
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

        <?php if ($selectedClass): ?>
            <div class="col-md-4">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user me-2"></i>Select Student</label>
                    <form method="get">
                        <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
                        <select name="student_id" class="form-control" onchange="this.form.submit();">
                            <option value="">Choose a student...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudent ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Grades Table -->
    <?php if ($selectedClass && $selectedStudent && !empty($subjects)): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i>
                    <?php 
                        $selectedStudentName = '';
                        foreach ($students as $s) {
                            if ($s['id'] == $selectedStudent) {
                                $selectedStudentName = $s['name'];
                                break;
                            }
                        }
                        echo htmlspecialchars($selectedStudentName) . ' - Grade Report';
                    ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <form method="post">
                    <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
                    <input type="hidden" name="student_id" value="<?= $selectedStudent ?>">

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">
                                        <i class="fas fa-book me-2"></i>SUBJECT
                                    </th>
                                    <th style="width: 12%;" class="text-center">
                                        SBA 50%
                                    </th>
                                    <th style="width: 12%;" class="text-center">
                                        EXAMS 50%
                                    </th>
                                    <th style="width: 12%;" class="text-center">
                                        TOTAL 100%
                                    </th>
                                    <th style="width: 10%;" class="text-center">
                                        GRADE
                                    </th>
                                    <th style="width: 18%;">
                                        REMARKS
                                    </th>
                                    <th style="width: 11%;" class="text-center">
                                        SUBJECT POSITION
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): 
                                    $existing = $gradesData[$subject['id']] ?? [];
                                    $sbaScore = $existing['sba_score'] ?? '';
                                    $examScore = $existing['exam_score'] ?? '';
                                    $total = '';
                                    $grade = $existing['grade'] ?? '#N/A';
                                    $remark = $existing['remark'] ?? '';

                                    // Calculate total if both scores exist
                                    if ($sbaScore !== '' && $examScore !== '') {
                                        $total = round(($sbaScore * 0.5) + ($examScore * 0.5), 2);
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($subject['name']) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <input type="number" name="grades[<?= $subject['id'] ?>][sba]" 
                                                   value="<?= htmlspecialchars($sbaScore) ?>"
                                                   min="0" max="100" step="0.01" class="form-control form-control-sm text-center sba-input"
                                                   placeholder="0-100">
                                        </td>
                                        <td class="text-center">
                                            <input type="number" name="grades[<?= $subject['id'] ?>][exam]" 
                                                   value="<?= htmlspecialchars($examScore) ?>"
                                                   min="0" max="100" step="0.01" class="form-control form-control-sm text-center exam-input"
                                                   placeholder="0-100">
                                        </td>
                                        <td class="text-center">
                                            <div class="total-display fw-bold" data-subject-id="<?= $subject['id'] ?>">
                                                <?= $total !== '' ? htmlspecialchars($total) : '#N/A' ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="grade-badge badge bg-info">
                                                <?= htmlspecialchars($grade) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <input type="text" name="remarks[<?= $subject['id'] ?>]" 
                                                   value="<?= htmlspecialchars($remark) ?>"
                                                   class="form-control form-control-sm"
                                                   placeholder="e.g., Excellent, Good, Needs improvement">
                                        </td>
                                        <td class="text-center">
                                            <span class="position-display">#N/A</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Grades are automatically calculated: (SBA × 50) + (EXAMS × 50) ÷ 100
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="?class_id=<?= $selectedClass ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save me-2"></i>Save Grades
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($selectedClass && empty($subjects)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No subjects found for this class. Please set up subjects first.
        </div>
    <?php elseif ($selectedClass && empty($students)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No students enrolled in this class yet.
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.sba-input, .exam-input').forEach(input => {
    input.addEventListener('change', function() {
        const row = this.closest('tr');
        const sbaInput = row.querySelector('.sba-input');
        const examInput = row.querySelector('.exam-input');
        const totalDisplay = row.querySelector('.total-display');

        const sba = parseFloat(sbaInput.value);
        const exam = parseFloat(examInput.value);

        if (!isNaN(sba) && !isNaN(exam)) {
            const total = (sba * 0.5) + (exam * 0.5);
            totalDisplay.textContent = total.toFixed(2);
        } else {
            totalDisplay.textContent = '#N/A';
        }
    });
});
</script>

<style>
.grade-badge {
    font-size: 1.1rem;
    padding: 0.5rem 1rem;
}

.form-control-sm {
    border-radius: 0.25rem;
}

.total-display {
    padding: 0.375rem 0.75rem;
    background-color: #f8f9fa;
    border-radius: 0.25rem;
    min-width: 60px;
    display: inline-block;
}

.position-display {
    color: #666;
    font-weight: 500;
}

.sba-input, .exam-input {
    font-weight: 500;
}

table thead th {
    font-weight: 600;
    border-top: 2px solid #dee2e6;
}

table tbody tr:hover {
    background-color: #f8f9fa;
}
</style>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
