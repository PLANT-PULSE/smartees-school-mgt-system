<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Teachers';
$pdo = getDb();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name === '' || $username === '') {
        flash('error', 'Name and username are required.');
        redirect('teachers.php');
    }

    if (!empty($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare('UPDATE teachers SET name = :name, email = :email, phone = :phone WHERE id = :id');
        $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':id' => $id]);

        // update user
        $userStmt = $pdo->prepare('UPDATE users SET username = :username WHERE teacher_id = :tid');
        $userStmt->execute([':username' => $username, ':tid' => $id]);

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pwStmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE teacher_id = :tid');
            $pwStmt->execute([':hash' => $hash, ':tid' => $id]);
        }

        flash('success', 'Teacher updated.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO teachers (name, email, phone) VALUES (:name, :email, :phone)');
        $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone]);
        $teacherId = $pdo->lastInsertId();

        $hash = password_hash($password ?: 'teacher123', PASSWORD_DEFAULT);
        $userStmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, name, teacher_id) VALUES (:username, :hash, :role, :name, :teacher_id)');
        $userStmt->execute([
            ':username' => $username,
            ':hash' => $hash,
            ':role' => 'teacher',
            ':name' => $name,
            ':teacher_id' => $teacherId,
        ]);

        flash('success', 'Teacher added. Default password is "teacher123" if left empty.');
    }

    redirect('teachers.php');
}

if ($action === 'delete' && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('DELETE FROM users WHERE teacher_id = :tid');
    $stmt->execute([':tid' => $id]);
    $stmt = $pdo->prepare('DELETE FROM teachers WHERE id = :id');
    $stmt->execute([':id' => $id]);
    flash('success', 'Teacher removed.');
    redirect('teachers.php');
}

$teachers = $pdo->query('SELECT t.*, u.username FROM teachers t LEFT JOIN users u ON u.teacher_id = t.id ORDER BY t.name')->fetchAll();

$editTeacher = null;
if ($action === 'edit' && !empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT t.*, u.username FROM teachers t LEFT JOIN users u ON u.teacher_id = t.id WHERE t.id = :id');
    $stmt->execute([':id' => $_GET['id']]);
    $editTeacher = $stmt->fetch();
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<!-- Page Header -->
<div class="table-header">
    <div>
        <h1 class="table-title">
            <i class="fas fa-chalkboard-teacher me-3 text-primary"></i>Teachers Management
        </h1>
        <p class="text-muted mb-0">Manage teacher information and login credentials</p>
    </div>
    <div class="table-actions">
        <a href="teachers.php?action=add" class="btn btn-primary-custom">
            <i class="fas fa-plus me-2"></i>Add Teacher
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
                <?= $action === 'edit' ? 'Edit Teacher' : 'Add New Teacher' ?>
            </h2>
            <p class="form-subtitle">
                <?= $action === 'edit' ? 'Update teacher information and credentials' : 'Enter teacher details and create login account' ?>
            </p>
        </div>

        <form method="post" class="animate-fade-in">
            <?php if ($editTeacher): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editTeacher['id']) ?>" />
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="name">
                            <i class="fas fa-user me-2"></i>Full Name *
                        </label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= htmlspecialchars($editTeacher['name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="email">
                            <i class="fas fa-envelope me-2"></i>Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($editTeacher['email'] ?? '') ?>"
                               placeholder="teacher@example.com">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="phone">
                            <i class="fas fa-phone me-2"></i>Phone Number
                        </label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               value="<?= htmlspecialchars($editTeacher['phone'] ?? '') ?>"
                               placeholder="+1 (555) 123-4567">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label" for="username">
                            <i class="fas fa-user-circle me-2"></i>Login Username *
                        </label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($editTeacher['username'] ?? '') ?>" required>
                        <small class="text-muted">Used for system login</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    <i class="fas fa-lock me-2"></i>Password
                </label>
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="<?= $editTeacher ? 'Leave blank to keep current password' : 'Enter password (defaults to "teacher123" if blank)' ?>">
                <small class="text-muted">
                    <?= $editTeacher ? 'Leave blank to keep current password' : 'Will default to "teacher123" if left blank' ?>
                </small>
            </div>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary-custom btn-submit">
                    <i class="fas fa-save me-2"></i>
                    <?= $action === 'edit' ? 'Update Teacher' : 'Add Teacher' ?>
                </button>
                <a href="teachers.php" class="btn btn-secondary-custom">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Teachers Table -->
<div class="table-container">
    <!-- Search and Filter -->
    <div class="search-container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control search-input" placeholder="Search teachers by name, email, or username...">
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
                        <i class="fas fa-user me-2"></i>Name
                    </th>
                    <th>
                        <i class="fas fa-envelope me-2"></i>Email
                    </th>
                    <th>
                        <i class="fas fa-phone me-2"></i>Phone
                    </th>
                    <th>
                        <i class="fas fa-user-circle me-2"></i>Username
                    </th>
                    <th class="text-end">
                        <i class="fas fa-cogs me-2"></i>Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($teachers)): ?>
                    <?php foreach ($teachers as $index => $teacher): ?>
                        <tr style="animation-delay: <?= $index * 0.05 ?>s">
                            <td>
                                <span class="badge badge-info-custom">#<?= htmlspecialchars($teacher['id']) ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($teacher['name']) ?></strong>
                                        <div class="text-muted small">Teacher</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($teacher['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($teacher['email']) ?>" class="text-decoration-none">
                                        <i class="fas fa-envelope me-1 text-primary"></i>
                                        <?= htmlspecialchars($teacher['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($teacher['phone']): ?>
                                    <a href="tel:<?= htmlspecialchars($teacher['phone']) ?>" class="text-decoration-none">
                                        <i class="fas fa-phone me-1 text-success"></i>
                                        <?= htmlspecialchars($teacher['phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($teacher['username']): ?>
                                    <span class="badge badge-success-custom">
                                        <i class="fas fa-user-circle me-1"></i>
                                        <?= htmlspecialchars($teacher['username']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger-custom">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        No Account
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="teachers.php?action=edit&id=<?= $teacher['id'] ?>"
                                       class="btn btn-edit action-btn"
                                       data-tooltip="Edit teacher">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="teachers.php?action=delete&id=<?= $teacher['id'] ?>"
                                       class="btn btn-delete action-btn"
                                       data-tooltip="Delete teacher"
                                       onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars($teacher['name']) ?> and their user account? This action cannot be undone.');">
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
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h3>No Teachers Found</h3>
                                <p>Get started by adding your first teacher to the system.</p>
                                <a href="teachers.php?action=add" class="btn btn-primary-custom">
                                    <i class="fas fa-plus me-2"></i>Add First Teacher
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

.text-decoration-none:hover {
    color: inherit !important;
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
});

function clearFilters() {
    document.querySelector('.search-input').value = '';
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = '';
    });
}
</script>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
