<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';

requireLogin();

$pageTitle = 'Dashboard';

$pdo = getDb();

$counts = [];
foreach (['students', 'classes', 'attendance'] as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM $table");
    $counts[$table] = $stmt->fetchColumn();
}
$counts['teachers'] = 0; // Removed

// Get recent activities (mock data for demo)
$recentActivities = [
    ['type' => 'add', 'item' => 'New student', 'details' => 'Alice Johnson enrolled in Grade 1', 'time' => '2 hours ago'],
    ['type' => 'edit', 'item' => 'Class updated', 'details' => 'Mathematics class schedule changed', 'time' => '4 hours ago'],
    ['type' => 'add', 'item' => 'Fee payment', 'details' => 'Parent paid tuition fees', 'time' => '1 day ago'],
    ['type' => 'delete', 'item' => 'Student removed', 'details' => 'Bob Martinez transferred to another school', 'time' => '2 days ago'],
];

$user = currentUser();
?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<!-- Welcome Section -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 8px; margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <p style="color: rgba(255,255,255,0.9); margin-bottom: 5px;">Administration Dashboard</p>
            <h2 style="color: white; margin-bottom: 0;">Good morning, Welcome to School MVP!</h2>
            <p style="color: rgba(255,255,255,0.85); margin-top: 10px;">Manage your school's data efficiently with our comprehensive management system.</p>
        </div>
        <div>
            <i class="fas fa-graduation-cap fa-5x text-white opacity-50"></i>
        </div>
    </div>
</div>

<div class="dashboard-container">
<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stats-card">
        <div class="stats-icon students">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stats-number counter" data-count="<?= $counts['students'] ?>">0</div>
        <div class="stats-title">Students</div>
        <a href="/school/students.php" class="stretched-link" data-tooltip="View all students"></a>
    </div>

    <div class="stats-card">
        <div class="stats-icon classes">
            <i class="fas fa-school"></i>
        </div>
        <div class="stats-number counter" data-count="<?= $counts['classes'] ?>">0</div>
        <div class="stats-title">Classes</div>
        <a href="/school/classes.php" class="stretched-link" data-tooltip="View all classes"></a>
    </div>
    <div class="stats-card">
        <div class="stats-icon attendance">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stats-number counter" data-count="<?= $counts['attendance'] ?>">0</div>
        <div class="stats-title">Attendance Records</div>
        <a href="/school/attendance.php" class="stretched-link" data-tooltip="View attendance records"></a>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions" style="background: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
    <h3 style="margin-bottom: 20px; color: #333;"><i class="fas fa-bolt me-2"></i>Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
        <a href="/school/students.php?action=add" class="action-btn" data-tooltip="Add a new student">
            <i class="fas fa-user-plus"></i>
            <span>Add Student</span>
        </a>

        <a href="/school/classes.php?action=add" class="action-btn" data-tooltip="Create a new class">
            <i class="fas fa-plus-circle"></i>
            <span>Add Class</span>
        </a>
        <a href="/school/attendance.php" class="action-btn" data-tooltip="Mark attendance">
            <i class="fas fa-clipboard-check"></i>
            <span>Take Attendance</span>
        </a>
        <a href="/school/grades.php" class="action-btn" data-tooltip="Manage grades">
            <i class="fas fa-chart-line"></i>
            <span>View Grades</span>
        </a>
        <a href="/school/admin_student_portal.php" class="action-btn" data-tooltip="Manage student portal content">
            <i class="fas fa-user-graduate"></i>
            <span>Student Portal Admin</span>
        </a>
        <a href="/school/students.php" class="action-btn" data-tooltip="Search students">
            <i class="fas fa-search"></i>
            <span>Search Records</span>
        </a>
    </div>
</div>

<!-- Recent Activity -->
<div class="recent-activity" style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
    <h3 style="margin-bottom: 20px; color: #333;"><i class="fas fa-history me-2"></i>Recent Activity</h3>
    <?php foreach ($recentActivities as $activity): ?>
        <div class="activity-item">
            <div class="activity-icon <?= $activity['type'] ?>">
                <i class="fas fa-<?= $activity['type'] === 'add' ? 'plus' : ($activity['type'] === 'edit' ? 'edit' : 'trash') ?>"></i>
            </div>
            <div class="activity-content">
                <h6><?= htmlspecialchars($activity['item']) ?></h6>
                <p><?= htmlspecialchars($activity['details']) ?></p>
            </div>
            <div class="activity-time">
                <i class="fas fa-clock me-1"></i>
                <?= htmlspecialchars($activity['time']) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
