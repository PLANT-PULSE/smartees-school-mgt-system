<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireRole('admin');

$pageTitle = 'Security Portal';
$pdo = getDb();
$currentUser = currentUser();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$allowedRoles = ['student', 'admin'];
$roleFilter = trim($_GET['role'] ?? '');
if ($roleFilter !== '' && !in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formToken = $_POST['csrf_token'] ?? '';
    $targetUserId = intval($_POST['target_user_id'] ?? 0);
    $newPassword = trim($_POST['new_password'] ?? '');
    $selectedRole = trim($_POST['role_filter'] ?? '');

    if (!hash_equals($csrfToken, $formToken)) {
        flash('error', 'Invalid request token. Please try again.');
        redirect('security_portal.php');
    }

    if ($targetUserId <= 0 || $newPassword === '') {
        flash('error', 'Please select a user and provide a new password.');
        redirect('security_portal.php' . ($selectedRole !== '' ? '?role=' . urlencode($selectedRole) : ''));
    }

    if (strlen($newPassword) < 8) {
        flash('error', 'Password must be at least 8 characters long.');
        redirect('security_portal.php' . ($selectedRole !== '' ? '?role=' . urlencode($selectedRole) : ''));
    }

    $userStmt = $pdo->prepare('SELECT id, username, role, name FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute([':id' => $targetUserId]);
    $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser || !in_array($targetUser['role'], $allowedRoles, true)) {
        flash('error', 'Target account was not found or is not eligible for reset.');
        redirect('security_portal.php' . ($selectedRole !== '' ? '?role=' . urlencode($selectedRole) : ''));
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $updateStmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => $targetUserId,
        ]);

        $auditStmt = $pdo->prepare(
            'INSERT INTO password_reset_audit (target_user_id, reset_by_user_id, reset_method) VALUES (:target_user_id, :reset_by_user_id, :reset_method)'
        );
        $auditStmt->execute([
            ':target_user_id' => $targetUserId,
            ':reset_by_user_id' => intval($currentUser['id']),
            ':reset_method' => 'manual_set',
        ]);

        $pdo->commit();
        flash('success', 'Password reset successful for "' . $targetUser['username'] . '" (' . ucfirst($targetUser['role']) . ').');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Password reset failed. Please try again.');
    }

    redirect('security_portal.php' . ($selectedRole !== '' ? '?role=' . urlencode($selectedRole) : ''));
}

    $query = 'SELECT id, username, name, role, created_at FROM users WHERE role IN (\'student\', \'admin\')';
$params = [];
if ($roleFilter !== '') {
    $query .= ' AND role = :role';
    $params[':role'] = $roleFilter;
}
$query .= ' ORDER BY FIELD(role, \'admin\', \'teacher\', \'student\'), name, username';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="table-header">
    <div>
        <h1 class="table-title">
            <i class="fas fa-shield-alt me-3 text-danger"></i>Security Portal
        </h1>
        <p class="text-muted mb-0">Reset passwords for student, teacher, and admin portal accounts.</p>
    </div>
</div>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-custom alert-success">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-custom alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<div class="table-container mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="role" class="form-label fw-semibold">Filter by Role</label>
            <select id="role" name="role" class="form-control">
                <option value="">All Roles</option>
                <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="col-md-8">
            <button type="submit" class="btn btn-primary-custom">
                <i class="fas fa-filter me-2"></i>Apply Filter
            </button>
            <a href="security_portal.php" class="btn btn-secondary-custom ms-2">
                <i class="fas fa-times me-2"></i>Clear
            </a>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><i class="fas fa-user me-2"></i>Name</th>
                    <th><i class="fas fa-at me-2"></i>Username</th>
                    <th><i class="fas fa-user-tag me-2"></i>Role</th>
                    <th><i class="fas fa-key me-2"></i>Reset Password</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $account): ?>
                        <tr>
                            <td><?= htmlspecialchars($account['name']) ?></td>
                            <td><?= htmlspecialchars($account['username']) ?></td>
                            <td>
                                <span class="badge badge-info-custom"><?= htmlspecialchars(ucfirst($account['role'])) ?></span>
                            </td>
                            <td>
                                <form method="post" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="target_user_id" value="<?= intval($account['id']) ?>">
                                    <input type="hidden" name="role_filter" value="<?= htmlspecialchars($roleFilter) ?>">
                                    <input
                                        type="password"
                                        name="new_password"
                                        class="form-control"
                                        minlength="8"
                                        placeholder="New password (min 8 chars)"
                                        required
                                    >
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-sync-alt me-2"></i>Reset
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No accounts found for this role filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
