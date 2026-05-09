<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Students';
$pdo = getDb();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Excel import
    if (isset($_POST['import_excel'])) {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please select a valid Excel file.');
            redirect('students.php');
        }

        $file = $_FILES['excel_file'];
        $fileType = $file['type'];
        $fileName = $file['name'];

        // Validate file type
        $allowedTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroEnabled.12',
            'text/csv',
            'application/csv',
            'text/plain'
        ];

        if (!in_array($fileType, $allowedTypes) && !preg_match('/\.(xls|xlsx|xlsm|csv)$/i', $fileName)) {
            flash('error', 'Please upload a valid Excel or CSV file (.xls, .xlsx, .xlsm, or .csv).');
            redirect('students.php');
        }

        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            flash('error', 'File size must be less than 10MB.');
            redirect('students.php');
        }

        try {
            $rows = [];

            if (preg_match('/\.(xls|xlsx|xlsm)$/i', $fileName)) {
                // Try to use PhpSpreadsheet for Excel files
                if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                    require_once __DIR__ . '/vendor/autoload.php';
                    if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                        try {
                            // @phpstan-ignore-next-line
                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                            $worksheet = $spreadsheet->getActiveSheet();
                            $rows = $worksheet->toArray();
                        } catch (Exception $e) {
                            flash('error', 'Failed to read Excel file: ' . $e->getMessage() . '. Please use CSV format instead.');
                            redirect('students.php');
                        }
                    } else {
                        flash('error', 'Excel file support requires PhpSpreadsheet library. Please use CSV format instead.');
                        redirect('students.php');
                    }
                } else {
                    flash('error', 'Excel file support requires PhpSpreadsheet library. Please use CSV format instead.');
                    redirect('students.php');
                }
            } elseif (preg_match('/\.(csv)$/i', $fileName)) {
                // Handle CSV files
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle === false) {
                    flash('error', 'Unable to read CSV file.');
                    redirect('students.php');
                }

                while (($row = fgetcsv($handle)) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            } else {
                flash('error', 'Unsupported file format. Please upload .xls, .xlsx, .xlsm, or .csv files.');
                redirect('students.php');
            }

            if (empty($rows) || count($rows) < 2) {
                flash('error', 'File must contain at least a header row and one data row.');
                redirect('students.php');
            }

            // Get header row
            $headers = array_map('strtolower', array_map('trim', $rows[0]));
            $requiredHeaders = ['name'];
            $optionalHeaders = ['age', 'contact', 'class'];

            // Validate headers
            foreach ($requiredHeaders as $required) {
                if (!in_array($required, $headers)) {
                    flash('error', "Required column '$required' not found. Headers found: " . implode(', ', $headers));
                    redirect('students.php');
                }
            }

            // Map column indices
            $columnMap = [];
            foreach ($headers as $index => $header) {
                $columnMap[$header] = $index;
            }

            // Get classes for lookup
            $classMap = [];
            foreach ($classes as $class) {
                $classMap[strtolower($class['name'])] = $class['id'];
            }

            $pdo->beginTransaction();
            $imported = 0;
            $errors = [];

            // Process data rows
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $name = trim($row[$columnMap['name']] ?? '');
                if (empty($name)) {
                    $errors[] = "Row " . ($i + 1) . ": Name is required";
                    continue;
                }

                $age = null;
                if (isset($columnMap['age'])) {
                    $ageValue = trim($row[$columnMap['age']] ?? '');
                    if (!empty($ageValue) && is_numeric($ageValue)) {
                        $age = intval($ageValue);
                    }
                }

                $contact = trim($row[$columnMap['contact']] ?? '');
                $classId = null;

                if (isset($columnMap['class'])) {
                    $className = trim($row[$columnMap['class']] ?? '');
                    if (!empty($className)) {
                        $classKey = strtolower($className);
                        if (isset($classMap[$classKey])) {
                            $classId = $classMap[$classKey];
                        } else {
                            $errors[] = "Row " . ($i + 1) . ": Class '$className' not found";
                            continue;
                        }
                    }
                }

                try {
                    // Check for duplicate student (same name and class)
                    $stmt = $pdo->prepare('SELECT id FROM students WHERE name = ? AND class_id <=> ?');
                    $stmt->execute([$name, $classId]);
                    $existing = $stmt->fetch();

                    if ($existing) {
                        $errors[] = "Row " . ($i + 1) . ": Student '$name' already exists in this class";
                        continue;
                    }

                    $stmt = $pdo->prepare('INSERT INTO students (name, age, contact, class_id) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$name, $age, $contact, $classId]);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Row " . ($i + 1) . ": Failed to import '$name' - " . $e->getMessage();
                }
            }

            $pdo->commit();

            if ($imported > 0) {
                flash('success', "Successfully imported $imported students.");
            }

            if (!empty($errors)) {
                flash('error', 'Import completed with errors: ' . implode('; ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' ...and ' . (count($errors) - 5) . ' more' : ''));
            }

        } catch (Exception $e) {
            flash('error', 'Failed to process file: ' . $e->getMessage());
        }

        redirect('students.php');
    }

    // Handle regular student add/edit
    $name = trim($_POST['name'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $contact = trim($_POST['contact'] ?? '');
    $classId = intval($_POST['class_id'] ?? 0);

    if ($name === '') {
        flash('error', 'Student name is required.');
        redirect('students.php');
    }

    // Handle photo upload
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/students/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
        $targetFile = $uploadDir . $fileName;

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['photo']['type'], $allowedTypes)) {
            flash('error', 'Only JPG, PNG, and GIF files are allowed.');
            redirect('students.php');
        }

        // Validate file size (max 5MB)
        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            flash('error', 'File size must be less than 5MB.');
            redirect('students.php');
        }

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
            $photoPath = 'uploads/students/' . $fileName;
        } else {
            flash('error', 'Failed to upload photo.');
            redirect('students.php');
        }
    }

    if (!empty($_POST['id'])) {
        $stmt = $pdo->prepare('UPDATE students SET name = :name, age = :age, contact = :contact, class_id = :class_id' . ($photoPath ? ', photo = :photo' : '') . ' WHERE id = :id');
        $params = [':name' => $name, ':age' => $age, ':contact' => $contact, ':class_id' => $classId ?: null, ':id' => $_POST['id']];
        if ($photoPath) {
            $params[':photo'] = $photoPath;
        }
        $stmt->execute($params);
        flash('success', 'Student updated successfully.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO students (name, age, contact, class_id' . ($photoPath ? ', photo' : '') . ') VALUES (:name, :age, :contact, :class_id' . ($photoPath ? ', :photo' : '') . ')');
        $params = [':name' => $name, ':age' => $age, ':contact' => $contact, ':class_id' => $classId ?: null];
        if ($photoPath) {
            $params[':photo'] = $photoPath;
        }
        $stmt->execute($params);
        flash('success', 'Student added successfully.');
    }

    redirect('students.php');
}

if ($action === 'delete' && !empty($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
    $stmt->execute([':id' => $_GET['id']]);
    flash('success', 'Student removed.');
    redirect('students.php');
}

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();

$students = $pdo->query('SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id ORDER BY s.name')->fetchAll();

$editStudent = null;
if ($action === 'edit' && !empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute([':id' => $_GET['id']]);
    $editStudent = $stmt->fetch();
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<!-- Page Header -->
<div class="table-header">
    <div>
        <h1 class="table-title">
            <i class="fas fa-user-graduate me-3 text-primary"></i>Students Management
        </h1>
        <p class="text-muted mb-0">Manage student information and class assignments</p>
    </div>
    <div class="table-actions">
        <button type="button" class="btn btn-success-custom me-2" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-excel me-2"></i>Import from Excel
        </button>
        <a href="students.php?action=add" class="btn btn-primary-custom">
            <i class="fas fa-plus me-2"></i>Add Student
        </a>
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

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">
                    <i class="fas fa-file-excel me-2 text-success"></i>Import Students from Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>File Format Requirements:</strong>
                        <ul class="mb-0 mt-2">
                            <li>First row should contain column headers</li>
                            <li><strong>Required column:</strong> <code>name</code></li>
                            <li><strong>Optional columns:</strong> <code>age</code>, <code>contact</code>, <code>class</code></li>
                            <li>Class names must match existing classes exactly</li>
                            <li>Supported formats: .xls, .xlsx, .xlsm, .csv</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label for="excel_file" class="form-label">
                            <i class="fas fa-file-upload me-2"></i>Select File
                        </label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file"
                               accept=".xls,.xlsx,.xlsm,.csv" required>
                        <div class="form-text">Maximum file size: 10MB</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-table me-2"></i>Sample Format
                        </label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>name</th>
                                        <th>age</th>
                                        <th>contact</th>
                                        <th>class</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>John Doe</td>
                                        <td>16</td>
                                        <td>john@example.com</td>
                                        <td>Class 10A</td>
                                    </tr>
                                    <tr>
                                        <td>Jane Smith</td>
                                        <td>15</td>
                                        <td>555-0123</td>
                                        <td>Class 9B</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <a href="sample_students.csv" class="btn btn-sm btn-outline-primary" download>
                                <i class="fas fa-download me-2"></i>Download Sample CSV
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="import_excel" class="btn btn-success">
                        <i class="fas fa-upload me-2"></i>Import Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Form -->
<?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="form-container">
        <div class="form-header">
            <h2 class="form-title">
                <i class="fas fa-<?= $action === 'edit' ? 'edit' : 'plus-circle' ?> me-2"></i>
                <?= $action === 'edit' ? 'Edit Student' : 'Add New Student' ?>
            </h2>
            <p class="form-subtitle">
                <?= $action === 'edit' ? 'Update student information' : 'Enter student details to add them to the system' ?>
            </p>
        </div>

        <form method="post" enctype="multipart/form-data" class="animate-fade-in">
            <?php if ($editStudent): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editStudent['id']) ?>" />
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="name">
                            <i class="fas fa-user me-2"></i>Full Name *
                        </label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= htmlspecialchars($editStudent['name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="age">
                            <i class="fas fa-birthday-cake me-2"></i>Age
                        </label>
                        <input type="number" class="form-control" id="age" name="age" min="1" max="100"
                               value="<?= htmlspecialchars($editStudent['age'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="contact">
                            <i class="fas fa-phone me-2"></i>Contact Information
                        </label>
                        <input type="text" class="form-control" id="contact" name="contact"
                               value="<?= htmlspecialchars($editStudent['contact'] ?? '') ?>"
                               placeholder="Phone or email">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="class_id">
                            <i class="fas fa-school me-2"></i>Class Assignment
                        </label>
                        <select class="form-control" id="class_id" name="class_id">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?= $cls['id'] ?>"
                                    <?= ($editStudent['class_id'] ?? '') == $cls['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cls['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="form-label" for="photo">
                            <i class="fas fa-camera me-2"></i>Student Photo
                        </label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                        <small class="text-muted">Accepted formats: JPG, PNG, GIF. Max size: 5MB</small>
                        <?php if ($editStudent && $editStudent['photo']): ?>
                            <div class="mt-2">
                                <img src="<?= htmlspecialchars($editStudent['photo']) ?>" alt="Current photo" style="max-width: 100px; max-height: 100px; border-radius: 8px;">
                                <p class="text-muted mt-1">Leave empty to keep current photo</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary-custom btn-submit">
                    <i class="fas fa-save me-2"></i>
                    <?= $action === 'edit' ? 'Update Student' : 'Add Student' ?>
                </button>
                <a href="students.php" class="btn btn-secondary-custom">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Students Table -->
<div class="table-container">
    <!-- Search and Filter -->
    <div class="search-container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control search-input" placeholder="Search students...">
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <select class="form-control filter-select" id="classFilter">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-secondary-custom" onclick="clearFilters()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th data-sort="number">
                        <i class="fas fa-hashtag me-2"></i>ID
                    </th>
                    <th data-sort="text">
                        <i class="fas fa-user me-2"></i>Name
                    </th>
                    <th data-sort="text">
                        <i class="fas fa-school me-2"></i>Class
                    </th>
                    <th data-sort="number">
                        <i class="fas fa-birthday-cake me-2"></i>Age
                    </th>
                    <th>
                        <i class="fas fa-phone me-2"></i>Contact
                    </th>
                    <th class="text-end">
                        <i class="fas fa-cogs me-2"></i>Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): ?>
                    <?php foreach ($students as $index => $student): ?>
                        <tr data-class-id="<?= $student['class_id'] ?? '' ?>" style="animation-delay: <?= $index * 0.05 ?>s">
                            <td>
                                <span class="badge badge-info-custom">#<?= htmlspecialchars($student['id']) ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3">
                                        <?php if ($student['photo']): ?>
                                            <img src="<?= htmlspecialchars($student['photo']) ?>" alt="Student photo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($student['name']) ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($student['class_name']): ?>
                                    <span class="badge badge-success-custom">
                                        <i class="fas fa-graduation-cap me-1"></i>
                                        <?= htmlspecialchars($student['class_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger-custom">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Unassigned
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['age']): ?>
                                    <?= htmlspecialchars($student['age']) ?> years
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['contact']): ?>
                                    <i class="fas fa-phone me-1"></i>
                                    <?= htmlspecialchars($student['contact']) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="students.php?action=edit&id=<?= $student['id'] ?>"
                                       class="btn btn-edit action-btn"
                                       data-tooltip="Edit student">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="students.php?action=delete&id=<?= $student['id'] ?>"
                                       class="btn btn-delete action-btn"
                                       data-tooltip="Delete student"
                                       onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars($student['name']) ?>?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-user-graduate"></i>
                                <h3>No Students Found</h3>
                                <p>Get started by adding your first student to the system.</p>
                                <a href="students.php?action=add" class="btn btn-primary-custom">
                                    <i class="fas fa-plus me-2"></i>Add First Student
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination (if needed in future) -->
    <?php if (!empty($students) && count($students) > 10): ?>
        <nav class="pagination-custom">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                </li>
                <li class="page-item active">
                    <a class="page-link" href="#">1</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Additional CSS for this page -->
<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
    overflow: hidden;
}

.table tbody tr {
    animation: fadeInUp 0.6s ease-out both;
}

.btn-group .action-btn {
    border-radius: 8px !important;
    margin: 0 2px;
    padding: 8px 12px;
    border: none;
    transition: all 0.3s ease;
}

.btn-group .action-btn:hover {
    transform: translateY(-2px);
}
</style>

<!-- Additional JavaScript for this page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Class filter functionality
    const classFilter = document.getElementById('classFilter');
    if (classFilter) {
        classFilter.addEventListener('change', function() {
            const selectedClass = this.value;
            const rows = document.querySelectorAll('tbody tr[data-class-id]');

            rows.forEach(row => {
                const classId = row.getAttribute('data-class-id');
                if (selectedClass === '' || classId === selectedClass) {
                    row.style.display = '';
                    row.style.animation = 'fadeIn 0.3s ease-out';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});

function clearFilters() {
    document.querySelector('.search-input').value = '';
    document.getElementById('classFilter').value = '';
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = '';
    });
}
</script>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
