<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$user = currentUser();
if ($user['role'] !== 'teacher') {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDb();
$teacherId = $user['teacher_id'] ?? null;
if (!$teacherId) {
    $stmt = $pdo->prepare('SELECT teacher_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $teacherId = $stmt->fetchColumn();
}

if (!$teacherId) {
    flash('error', 'Teacher account is not linked to a teacher profile.');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Lesson Notes';
$action = $_GET['action'] ?? 'list';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_lesson_note'])) {
    $classId = intval($_POST['class_id'] ?? 0);
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        flash('error', 'Lesson note title is required.');
        header('Location: lesson_notes.php?action=upload');
        exit;
    }

    if (!$classId || !$subjectId) {
        flash('error', 'Please select both class and subject.');
        header('Location: lesson_notes.php?action=upload');
        exit;
    }

    // Verify teacher is assigned to this class
    $stmt = $pdo->prepare('SELECT id FROM classes WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$classId, $teacherId]);
    if (!$stmt->fetch()) {
        flash('error', 'You are not assigned to this class.');
        header('Location: lesson_notes.php?action=upload');
        exit;
    }

    // Handle file upload
    if (!isset($_FILES['lesson_file']) || $_FILES['lesson_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please select a file to upload.');
        header('Location: lesson_notes.php?action=upload');
        exit;
    }

    $file = $_FILES['lesson_file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];

    // Validate file type
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];

    $fileType = $file['type'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];

    $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($fileType, $allowedMimeTypes) && !in_array($fileExt, $allowedExtensions)) {
        flash('error', 'Invalid file type. Allowed: PDF, Word, PowerPoint, Text, Images.');
        header('Location: lesson_notes.php?action=upload');
        exit;
    }

    // Validate file size (max 20MB)
    if ($fileSize > 20 * 1024 * 1024) {
        flash('error', 'File size must be less than 20MB.');
        header('Location: lesson_notes.php?action=upload');
        exit;
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/uploads/lesson_notes/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $uniqueName = uniqid('lesson_') . '_' . time() . '.' . $fileExt;
    $targetFile = $uploadDir . $uniqueName;

    if (move_uploaded_file($fileTmp, $targetFile)) {
        // Save to database
        $stmt = $pdo->prepare('
            INSERT INTO lesson_notes (teacher_id, class_id, subject_id, title, description, file_path, file_name, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $teacherId,
            $classId,
            $subjectId,
            $title,
            $description,
            'uploads/lesson_notes/' . $uniqueName,
            $fileName,
            $fileSize
        ]);

        flash('success', 'Lesson note uploaded successfully.');
        header('Location: lesson_notes.php');
        exit;
    } else {
        flash('error', 'Failed to upload file.');
        header('Location: lesson_notes.php?action=upload');
        exit;
    }
}

// Handle delete
if ($action === 'delete' && !empty($_GET['id'])) {
    $noteId = intval($_GET['id']);

    // Verify ownership
    $stmt = $pdo->prepare('SELECT file_path FROM lesson_notes WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$noteId, $teacherId]);
    $note = $stmt->fetch();

    if ($note) {
        // Delete file
        $filePath = __DIR__ . '/' . $note['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from database
        $stmt = $pdo->prepare('DELETE FROM lesson_notes WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$noteId, $teacherId]);

        flash('success', 'Lesson note deleted successfully.');
    } else {
        flash('error', 'Lesson note not found or access denied.');
    }

    header('Location: lesson_notes.php');
    exit;
}

// Get teacher's classes and subjects
$classes = $pdo->prepare('
    SELECT c.id, c.name
    FROM classes c
    WHERE c.teacher_id = ?
    ORDER BY c.name
');
$classes->execute([$teacherId]);
$classes = $classes->fetchAll();

$subjects = $pdo->prepare('
    SELECT DISTINCT s.id, s.name
    FROM subjects s
    JOIN class_subjects cs ON s.id = cs.subject_id
    JOIN classes c ON cs.class_id = c.id
    WHERE c.teacher_id = ?
    ORDER BY s.name
');
$subjects->execute([$teacherId]);
$subjects = $subjects->fetchAll();

// Get lesson notes
$lessonNotes = $pdo->prepare('
    SELECT ln.*,
           c.name as class_name,
           s.name as subject_name,
           t.name as teacher_name
    FROM lesson_notes ln
    JOIN classes c ON ln.class_id = c.id
    JOIN subjects s ON ln.subject_id = s.id
    JOIN teachers t ON ln.teacher_id = t.id
    WHERE ln.teacher_id = ?
    ORDER BY ln.uploaded_at DESC
');
$lessonNotes->execute([$teacherId]);
$lessonNotes = $lessonNotes->fetchAll();

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-2">
                <i class="fas fa-book-open me-2"></i>Lesson Notes
            </h1>
            <p class="text-muted mb-0">Upload and manage your lesson notes for assigned classes</p>
        </div>
        <div>
            <a href="lesson_notes.php?action=upload" class="btn btn-primary-custom">
                <i class="fas fa-plus me-2"></i>Upload Lesson Note
            </a>
        </div>
    </div>

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

    <?php if ($action === 'upload'): ?>
        <!-- Upload Form -->
        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-upload me-2"></i>Upload Lesson Note
                </h2>
                <p class="form-subtitle">Share your lesson materials with students</p>
            </div>

            <form method="post" enctype="multipart/form-data" class="animate-fade-in">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="class_id">
                                <i class="fas fa-school me-2"></i>Class *
                            </label>
                            <select class="form-control" id="class_id" name="class_id" required>
                                <option value="">Choose a class...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>">
                                        <?= htmlspecialchars($class['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="subject_id">
                                <i class="fas fa-book me-2"></i>Subject *
                            </label>
                            <select class="form-control" id="subject_id" name="subject_id" required>
                                <option value="">Choose a subject...</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>">
                                        <?= htmlspecialchars($subject['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label" for="title">
                                <i class="fas fa-heading me-2"></i>Lesson Title *
                            </label>
                            <input type="text" class="form-control" id="title" name="title"
                                   placeholder="e.g., Introduction to Algebra" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label" for="description">
                                <i class="fas fa-align-left me-2"></i>Description
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Brief description of the lesson content..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label" for="lesson_file">
                                <i class="fas fa-file-upload me-2"></i>Lesson File *
                            </label>
                            <input type="file" class="form-control" id="lesson_file" name="lesson_file"
                                   accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif" required>
                            <small class="text-muted">
                                Accepted formats: PDF, Word (.doc, .docx), PowerPoint (.ppt, .pptx), Text (.txt), Images (.jpg, .png, .gif).
                                Maximum size: 20MB
                            </small>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 mt-4">
                    <button type="submit" name="upload_lesson_note" class="btn btn-primary-custom btn-submit">
                        <i class="fas fa-upload me-2"></i>Upload Lesson Note
                    </button>
                    <a href="lesson_notes.php" class="btn btn-secondary-custom">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- Lesson Notes List -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th data-sort="text">
                                <i class="fas fa-heading me-2"></i>Title
                            </th>
                            <th data-sort="text">
                                <i class="fas fa-school me-2"></i>Class
                            </th>
                            <th data-sort="text">
                                <i class="fas fa-book me-2"></i>Subject
                            </th>
                            <th data-sort="date">
                                <i class="fas fa-calendar me-2"></i>Uploaded
                            </th>
                            <th data-sort="number">
                                <i class="fas fa-file me-2"></i>File Size
                            </th>
                            <th class="text-end">
                                <i class="fas fa-cogs me-2"></i>Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($lessonNotes)): ?>
                            <?php foreach ($lessonNotes as $note): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($note['title']) ?></strong>
                                            <?php if ($note['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($note['description']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-success-custom">
                                            <i class="fas fa-graduation-cap me-1"></i>
                                            <?= htmlspecialchars($note['class_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info-custom">
                                            <i class="fas fa-book me-1"></i>
                                            <?= htmlspecialchars($note['subject_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('M d, Y', strtotime($note['uploaded_at'])) ?>
                                        <br><small class="text-muted"><?= date('h:i A', strtotime($note['uploaded_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $size = $note['file_size'];
                                        if ($size < 1024) {
                                            echo $size . ' B';
                                        } elseif ($size < 1024 * 1024) {
                                            echo round($size / 1024, 1) . ' KB';
                                        } else {
                                            echo round($size / (1024 * 1024), 1) . ' MB';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <a href="<?= htmlspecialchars($note['file_path']) ?>"
                                               class="btn btn-view action-btn"
                                               target="_blank"
                                               data-tooltip="View/Download">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="lesson_notes.php?action=delete&id=<?= $note['id'] ?>"
                                               class="btn btn-delete action-btn"
                                               data-tooltip="Delete lesson note"
                                               onclick="return confirm('Are you sure you want to delete this lesson note?');">
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
                                        <i class="fas fa-book-open"></i>
                                        <h3>No Lesson Notes Yet</h3>
                                        <p>Start sharing your lesson materials with students by uploading your first lesson note.</p>
                                        <a href="lesson_notes.php?action=upload" class="btn btn-primary-custom">
                                            <i class="fas fa-plus me-2"></i>Upload First Lesson Note
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>