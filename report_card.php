<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Report Card';
$pdo = getDb();

$selectedClass = intval($_GET['class_id'] ?? 0);
$selectedStudent = intval($_GET['student_id'] ?? 0);

// Get classes
$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();

// Get students and grades
$students = [];
$subjects = [];
$grades = [];
$studentInfo = null;
$classInfo = null;

if ($selectedClass) {
    // Get students in class
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

        // Get subjects and grades
        $stmt = $pdo->prepare('
            SELECT DISTINCT s.id, s.name
            FROM subjects s
            JOIN class_subjects cs ON s.id = cs.subject_id
            WHERE cs.class_id = ?
            ORDER BY s.name
        ');
        $stmt->execute([$selectedClass]);
        $subjects = $stmt->fetchAll();

        // If no subjects linked, get all subjects
        if (empty($subjects)) {
            $subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();
        }

        // Get grades
        $stmt = $pdo->prepare('
            SELECT * FROM grades
            WHERE student_id = ? AND class_id = ?
            ORDER BY id
        ');
        $stmt->execute([$selectedStudent, $selectedClass]);
        foreach ($stmt->fetchAll() as $row) {
            $grades[$row['subject_id']] = $row;
        }
    }
}

// Calculate subject positions (rank by total score)
$subjectRanks = [];
$classPosition = null;
if ($selectedClass && $selectedStudent && !empty($subjects)) {
    foreach ($subjects as $subject) {
        // Get all total scores for this subject in the class
        $stmt = $pdo->prepare('
            SELECT g.id, 
                   CASE 
                       WHEN g.sba_score IS NOT NULL AND g.exam_score IS NOT NULL 
                       THEN (g.sba_score * 0.5 + g.exam_score * 0.5)
                       ELSE 0
                   END as total_score
            FROM grades g
            WHERE g.class_id = ? AND g.subject_id = ?
            ORDER BY total_score DESC
        ');
        $stmt->execute([$selectedClass, $subject['id']]);
        $results = $stmt->fetchAll();

        // Find the rank of current student
        $rank = 1;
        foreach ($results as $idx => $result) {
            if ($grades[$subject['id']]['id'] == $result['id']) {
                $rank = $idx + 1;
                break;
            }
        }
        $subjectRanks[$subject['id']] = $rank;
    }
    
    // Calculate class position (overall rank by average score)
    $stmt = $pdo->prepare('
        SELECT student_id,
               AVG(CASE 
                   WHEN sba_score IS NOT NULL AND exam_score IS NOT NULL 
                   THEN (sba_score * 0.5 + exam_score * 0.5)
                   ELSE 0
               END) as avg_score
        FROM grades
        WHERE class_id = ?
        GROUP BY student_id
        ORDER BY avg_score DESC
    ');
    $stmt->execute([$selectedClass]);
    $classRanks = $stmt->fetchAll();
    
    foreach ($classRanks as $idx => $rank) {
        if ($rank['student_id'] == $selectedStudent) {
            $classPosition = $idx + 1;
            break;
        }
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<style>
    .report-card-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .report-card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 30px;
        text-align: center;
    }

    .report-card-header h2 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
    }

    .student-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
        padding: 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e0e0e0;
    }

    .info-item {
        text-align: center;
    }

    .info-label {
        font-size: 0.85rem;
        color: #666;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .info-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #333;
    }

    .grades-table {
        width: 100%;
        border-collapse: collapse;
    }

    .grades-table thead th {
        background: #f8f9fa;
        padding: 15px;
        text-align: center;
        font-weight: 600;
        border-bottom: 2px solid #667eea;
        color: #333;
        font-size: 0.95rem;
    }

    .grades-table tbody td {
        padding: 15px;
        border-bottom: 1px solid #e0e0e0;
    }

    .grades-table tbody tr:hover {
        background: #f8f9fa;
    }

    .subject-name {
        font-weight: 600;
        color: #333;
    }

    .score-cell {
        text-align: center;
        font-weight: 500;
    }

    .total-score {
        background: #f0f4ff;
        font-weight: 700;
        color: #667eea;
    }

    .grade-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        text-align: center;
        min-width: 40px;
    }

    .grade-badge.excellent {
        background: #d4edda;
        color: #155724;
    }

    .grade-badge.good {
        background: #cce5ff;
        color: #004085;
    }

    .grade-badge.developing {
        background: #fff3cd;
        color: #856404;
    }

    .grade-badge.emerging {
        background: #f8d7da;
        color: #721c24;
    }

    .remarks-text {
        color: #666;
        font-style: italic;
    }

    .position-rank {
        background: #e7f3ff;
        color: #004085;
        font-weight: 700;
        text-align: center;
    }

    .report-card-footer {
        padding: 20px;
        background: #f8f9fa;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .print-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .print-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    @media print {
        .filter-section {
            display: none;
        }
        .report-card-footer {
            display: none;
        }
        body {
            background: white;
        }
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <!-- Filter Section -->
    <div class="filter-section">
        <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Select Student</h5>
        <div class="row">
            <div class="col-md-4">
                <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Class</label>
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
            <div class="col-md-4">
                <label class="form-label"><i class="fas fa-user me-2"></i>Student</label>
                <select name="student_id" class="form-control" onchange="
                    const url = new URL(window.location);
                    url.searchParams.set('class_id', document.querySelector('select[name=class_id]').value);
                    url.searchParams.set('student_id', this.value);
                    window.location = url.toString();
                ">
                    <option value="">Choose a student...</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudent ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Report Card -->
    <?php if ($selectedStudent && $studentInfo): ?>
        <div class="report-card-container">
            <!-- Header -->
            <div class="report-card-header">
                <h2>Student Report Card</h2>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">
                    <?= htmlspecialchars($classInfo['name']) ?> - Academic Report
                </p>
            </div>

            <!-- Student Information -->
            <div class="student-info">
                <div class="info-item">
                    <div class="info-label">Student Name</div>
                    <div class="info-value"><?= htmlspecialchars($studentInfo['name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Student ID</div>
                    <div class="info-value">#<?= htmlspecialchars($studentInfo['id']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Class</div>
                    <div class="info-value"><?= htmlspecialchars($classInfo['name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Report Date</div>
                    <div class="info-value"><?= date('M d, Y') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Class Position</div>
                    <div class="info-value">
                        <?php if ($classPosition): ?>
                            <span style="background: #e7f3ff; color: #004085; padding: 6px 12px; border-radius: 20px; font-weight: 700;">
                                <?= $classPosition . htmlspecialchars(getOrdinalSuffix($classPosition)) ?>
                            </span>
                        <?php else: ?>
                            #N/A
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Grades Table -->
            <table class="grades-table">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 25%;">SUBJECT</th>
                        <th style="width: 10%;">SBA<br><small>50%</small></th>
                        <th style="width: 10%;">EXAMS<br><small>50%</small></th>
                        <th style="width: 10%;">TOTAL<br><small>100%</small></th>
                        <th style="width: 10%;">GRADE</th>
                        <th style="width: 20%;">REMARKS</th>
                        <th style="width: 15%;">SUBJECT POSITION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subject):
                        $gradeData = $grades[$subject['id']] ?? null;
                        $sbaScore = $gradeData ? ($gradeData['sba_score'] ?? '') : '';
                        $examScore = $gradeData ? ($gradeData['exam_score'] ?? '') : '';
                        $grade = $gradeData ? ($gradeData['grade'] ?? '#N/A') : '#N/A';
                        $remarks = $gradeData ? ($gradeData['remark'] ?? '') : '';
                        $position = $subjectRanks[$subject['id']] ?? '';

                        // Calculate total
                        $total = '';
                        if ($sbaScore !== '' && $examScore !== '') {
                            $total = round(($sbaScore * 0.5) + ($examScore * 0.5), 2);
                        }

                        // Determine grade class
                        $gradeClass = '';
                        if ($grade === 'A' || $grade === '4') {
                            $gradeClass = 'excellent';
                        } elseif ($grade === 'B' || $grade === '5') {
                            $gradeClass = 'good';
                        } elseif ($grade === 'C' || $grade === 'D') {
                            $gradeClass = 'developing';
                        } else {
                            $gradeClass = 'emerging';
                        }
                    ?>
                        <tr>
                            <td class="subject-name"><?= htmlspecialchars($subject['name']) ?></td>
                            <td class="score-cell"><?= $sbaScore !== '' ? htmlspecialchars($sbaScore) : '#N/A' ?></td>
                            <td class="score-cell"><?= $examScore !== '' ? htmlspecialchars($examScore) : '#N/A' ?></td>
                            <td class="score-cell total-score"><?= $total !== '' ? htmlspecialchars($total) : '#N/A' ?></td>
                            <td class="score-cell">
                                <span class="grade-badge <?= $gradeClass ?>">
                                    <?= htmlspecialchars($grade) ?>
                                </span>
                            </td>
                            <td class="remarks-text"><?= htmlspecialchars($remarks ?: '---') ?></td>
                            <td class="position-rank"><?= $position ? htmlspecialchars($position) . htmlspecialchars(getOrdinalSuffix($position)) : '#N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Footer -->
            <div class="report-card-footer">
                <div>
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-info-circle me-2"></i>
                        Generated on <?= date('F d, Y \a\t h:i A') ?>
                    </p>
                </div>
                <div>
                    <button class="print-btn" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report Card
                    </button>
                </div>
            </div>
        </div>
    <?php elseif ($selectedClass): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Select a student to view their report card.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Select a class and student to generate a report card.
        </div>
    <?php endif; ?>
</div>

<?php 
// Helper function for ordinal suffixes
function getOrdinalSuffix($num) {
    $lastDigit = $num % 10;
    $lastTwoDigits = $num % 100;
    
    if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
        return 'th';
    }
    
    switch ($lastDigit) {
        case 1:
            return 'st';
        case 2:
            return 'nd';
        case 3:
            return 'rd';
        default:
            return 'th';
    }
}
?>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
