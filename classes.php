<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Classes';
$pdo = getDb();

// Ensure class_subjects table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        subject_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY u_class_subject (class_id, subject_id),
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {
    error_log('Error creating class_subjects table: ' . $e->getMessage());
}

$action = $_GET['action'] ?? '';

// Handle AJAX requests for subjects
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'add_subject') {
        $classId = intval($_POST['class_id'] ?? 0);
        $subjectId = intval($_POST['subject_id'] ?? 0);
        
        if ($classId && $subjectId) {
            try {
                $stmt = $pdo->prepare('INSERT INTO class_subjects (class_id, subject_id) VALUES (:class_id, :subject_id)');
                $stmt->execute([':class_id' => $classId, ':subject_id' => $subjectId]);
                echo json_encode(['success' => true, 'message' => 'Subject added to class']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Subject already exists in this class']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit;
    }
    
    if ($_GET['ajax'] === 'remove_subject') {
        $classId = intval($_POST['class_id'] ?? 0);
        $subjectId = intval($_POST['subject_id'] ?? 0);
        
        if ($classId && $subjectId) {
            $stmt = $pdo->prepare('DELETE FROM class_subjects WHERE class_id = :class_id AND subject_id = :subject_id');
            $stmt->execute([':class_id' => $classId, ':subject_id' => $subjectId]);
            echo json_encode(['success' => true, 'message' => 'Subject removed from class']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit;
    }
    
    if ($_GET['ajax'] === 'get_class_subjects') {
        $classId = intval($_GET['class_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT cs.id, s.id as subject_id, s.name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_id = :class_id');
        $stmt->execute([':class_id' => $classId]);
        $subjects = $stmt->fetchAll();
        echo json_encode(['success' => true, 'subjects' => $subjects]);
        exit;
    }
    
    if ($_GET['ajax'] === 'get_available_subjects') {
        $classId = intval($_GET['class_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT s.id, s.name FROM subjects s WHERE s.id NOT IN (SELECT subject_id FROM class_subjects WHERE class_id = :class_id) ORDER BY s.name');
        $stmt->execute([':class_id' => $classId]);
        $subjects = $stmt->fetchAll();
        echo json_encode(['success' => true, 'subjects' => $subjects]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');


    if ($name === '') {
        flash('error', 'Class name is required.');
        redirect('classes.php');
    }

    if (!empty($_POST['id'])) {
        $stmt = $pdo->prepare('UPDATE classes SET name = :name WHERE id = :id');
        $stmt->execute([':name' => $name, ':id' => $_POST['id']]);
        flash('success', 'Class updated.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO classes (name) VALUES (:name)');
        $stmt->execute([':name' => $name]);
        flash('success', 'Class added.');
    }

    redirect('classes.php');
}

if ($action === 'delete' && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('DELETE FROM classes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    flash('success', 'Class removed.');
    redirect('classes.php');
}



$classes = $pdo->query('SELECT c.* FROM classes c ORDER BY c.name')->fetchAll();

$editClass = null;
if ($action === 'edit' && !empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM classes WHERE id = :id');
    $stmt->execute([':id' => $_GET['id']]);
    $editClass = $stmt->fetch();
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<!-- Page Header -->
<div class="table-header">
    <div>
        <h1 class="table-title">
            <i class="fas fa-school me-3 text-primary"></i>Classes Management
        </h1>
        <p class="text-muted mb-0">Organize and manage class information</p>
    </div>
    <div class="table-actions">
        <a href="classes.php?action=add" class="btn btn-primary-custom">
            <i class="fas fa-plus me-2"></i>Add Class
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

<!-- Add/Edit Form -->
<?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="form-container">
        <div class="form-header">
            <h2 class="form-title">
                <i class="fas fa-<?= $action === 'edit' ? 'edit' : 'plus-circle' ?> me-2"></i>
                <?= $action === 'edit' ? 'Edit Class' : 'Add New Class' ?>
            </h2>
            <p class="form-subtitle">
                <?= $action === 'edit' ? 'Update class information' : 'Create a new class' ?>
            </p>
        </div>

        <form method="post" class="animate-fade-in">
            <?php if ($editClass): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editClass['id']) ?>" />
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="name">
                    <i class="fas fa-graduation-cap me-2"></i>Class Name *
                </label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= htmlspecialchars($editClass['name'] ?? '') ?>" required>
                <small class="text-muted">e.g., "Grade 10 - Mathematics", "English Literature"</small>
            </div>



            <?php if ($action === 'edit' && $editClass): ?>
                <div class="form-group">
                    <label class="form-label" for="subjects">
                        <i class="fas fa-book me-2"></i>Class Subjects
                    </label>
                    <div id="class-subjects-section">
                        <div id="selected-subjects" class="mb-3">
                            <!-- Subjects will be loaded here via AJAX -->
                        </div>
                        <div class="input-group">
                            <select class="form-control" id="subject_id">
                                <option value="">-- Select Subject to Add --</option>
                            </select>
                            <button type="button" class="btn btn-success-custom" id="add-subject-btn">
                                <i class="fas fa-plus me-2"></i>Add Subject
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary-custom btn-submit">
                    <i class="fas fa-save me-2"></i>
                    <?= $action === 'edit' ? 'Update Class' : 'Add Class' ?>
                </button>
                <a href="classes.php" class="btn btn-secondary-custom">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Classes Table -->
<div class="table-container">
    <!-- Search and Filter -->
    <div class="search-container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control search-input" placeholder="Search classes by name...">
                </div>
            </div>
            <div class="col-md-4">
                <button class="btn btn-secondary-custom w-100" onclick="clearFilters()">
                    <i class="fas fa-times me-2"></i>Clear Search
                </button>
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
                        <i class="fas fa-graduation-cap me-2"></i>Class Name
                    </th>

                    <th>
                        <i class="fas fa-users me-2"></i>Students
                    </th>
                    <th class="text-end">
                        <i class="fas fa-cogs me-2"></i>Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($classes)): ?>
                    <?php foreach ($classes as $index => $cls): ?>
                        <tr style="animation-delay: <?= $index * 0.05 ?>s">
                            <td>
                                <span class="badge badge-info-custom">#<?= htmlspecialchars($cls['id']) ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($cls['name']) ?></strong>
                                        <div class="text-muted small">Class</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($cls['teacher_name']): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle-sm me-2" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                        </div>
                                        <span class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cls['teacher_name']) ?>">
                                            <?= htmlspecialchars($cls['teacher_name']) ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="fas fa-user-slash me-1"></i>Unassigned
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-success-custom">
                                    <i class="fas fa-users me-1"></i>
                                    <?php
                                    // Count students in this class
                                    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM students WHERE class_id = ?');
                                    $stmt->execute([$cls['id']]);
                                    $studentCount = $stmt->fetch()['count'];
                                    echo $studentCount;
                                    ?> students
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="classes.php?action=edit&id=<?= $cls['id'] ?>"
                                       class="btn btn-edit action-btn"
                                       data-tooltip="Edit class / Manage subjects">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="classes.php?action=delete&id=<?= $cls['id'] ?>"
                                       class="btn btn-delete action-btn"
                                       data-tooltip="Delete class"
                                       onclick="return confirm('Are you sure you want to delete the class \"<?= htmlspecialchars($cls['name']) ?>\"? This will not delete enrolled students.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-school"></i>
                                <h3>No Classes Found</h3>
                                <p>Get started by creating your first class.</p>
                                <a href="classes.php?action=add" class="btn btn-primary-custom">
                                    <i class="fas fa-plus me-2"></i>Add First Class
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Additional CSS for this page -->
<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.avatar-circle-sm {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
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

.text-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>

<!-- Additional JavaScript for this page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;

                const text = row.textContent.toLowerCase();
                if (text.includes(query) || query === '') {
                    row.style.display = '';
                    row.style.animation = 'fadeIn 0.3s ease-out';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Subject management functionality
    const classId = <?= $action === 'edit' && $editClass ? $editClass['id'] : 0 ?>;
    if (classId) {
        setTimeout(() => {
            loadClassSubjects();
            loadAvailableSubjects();

            const addBtn = document.getElementById('add-subject-btn');
            if (addBtn) {
                addBtn.addEventListener('click', addSubject);
            }
        }, 100);
    }
});

function clearFilters() {
    document.querySelector('.search-input').value = '';
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = '';
    });
}

function loadClassSubjects() {
    const classId = <?= $action === 'edit' && $editClass ? $editClass['id'] : 0 ?>;
    if (!classId) return;

    fetch(`classes.php?ajax=get_class_subjects&class_id=${classId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('selected-subjects');
            if (!container) return;
            
            if (data.subjects && data.subjects.length > 0) {
                const html = data.subjects.map(subject => `
                    <div class="badge badge-info-custom d-inline-block me-2 mb-2" style="padding: 8px 12px; font-size: 14px;">
                        <i class="fas fa-book me-1"></i>${escapeHtml(subject.name)}
                        <button type="button" class="btn btn-sm btn-close-custom ms-2" onclick="removeSubject(${subject.subject_id})" style="background: none; border: none; color: #fff; cursor: pointer; padding: 0; font-size: 16px;">
                            &times;
                        </button>
                    </div>
                `).join('');
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p class="text-muted"><i class="fas fa-info-circle me-2"></i>No subjects assigned yet. Add one below.</p>';
            }
        })
        .catch(error => console.error('Error loading subjects:', error));
}

function loadAvailableSubjects() {
    const classId = <?= $action === 'edit' && $editClass ? $editClass['id'] : 0 ?>;
    if (!classId) return;

    fetch(`classes.php?ajax=get_available_subjects&class_id=${classId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('subject_id');
            if (!select) return;
            
            select.innerHTML = '<option value="">-- Select Subject to Add --</option>';
            
            if (data.subjects && data.subjects.length > 0) {
                data.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.name;
                    select.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.disabled = true;
                option.textContent = 'All subjects already assigned';
                select.appendChild(option);
            }
        })
        .catch(error => console.error('Error loading available subjects:', error));
}

function addSubject() {
    const classId = <?= $action === 'edit' && $editClass ? $editClass['id'] : 0 ?>;
    const subjectId = document.getElementById('subject_id').value;
    
    if (!subjectId) {
        alert('Please select a subject');
        return;
    }

    fetch('classes.php?ajax=add_subject', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `class_id=${classId}&subject_id=${subjectId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadClassSubjects();
            loadAvailableSubjects();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeSubject(subjectId) {
    const classId = <?= $action === 'edit' && $editClass ? $editClass['id'] : 0 ?>;
    
    if (!confirm('Are you sure you want to remove this subject from the class?')) {
        return;
    }

    fetch('classes.php?ajax=remove_subject', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `class_id=${classId}&subject_id=${subjectId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadClassSubjects();
            loadAvailableSubjects();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
