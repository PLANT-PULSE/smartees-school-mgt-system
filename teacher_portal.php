<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$user = currentUser();
if (($user['role'] ?? '') !== 'teacher') {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Teacher Portal';
$pdo = getDb();
$teacherId = intval($user['teacher_id'] ?? 0);
if ($teacherId <= 0) {
    $stmt = $pdo->prepare('SELECT teacher_id FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $user['id']]);
    $teacherId = intval($stmt->fetchColumn() ?: 0);
}

if ($teacherId <= 0) {
    flash('error', 'Your account is not linked to a teacher profile. Contact admin.');
    header('Location: login.php');
    exit;
}

// Handle forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        $receiverId = intval($_POST['receiver_user_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($receiverId > 0 && $subject !== '' && $message !== '') {
            $stmt = $pdo->prepare('INSERT INTO student_messages (sender_user_id, receiver_user_id, subject, message) VALUES (:sender, :receiver, :subject, :message)');
            $stmt->execute([
                ':sender' => $user['id'],
                ':receiver' => $receiverId,
                ':subject' => $subject,
                ':message' => $message,
            ]);
            flash('success', 'Message sent.');
        } else {
            flash('error', 'Receiver, subject, and message are required.');
        }
        redirect('teacher_portal.php?section=communication');
    }

    if ($action === 'send_fee_reminder') {
        $receiverId = intval($_POST['receiver_user_id'] ?? 0);
        $studentName = trim($_POST['student_name'] ?? 'Student');
        $balance = (float) ($_POST['balance'] ?? 0);
        if ($receiverId > 0 && $balance > 0) {
            $subject = 'School Fees Reminder';
            $message = 'Dear Parent/Student, ' . $studentName . '\'s fees are pending. Outstanding amount: GHc ' . number_format($balance, 2) . '. Kindly settle before the due date.';
            $stmt = $pdo->prepare('INSERT INTO student_messages (sender_user_id, receiver_user_id, subject, message) VALUES (:sender, :receiver, :subject, :message)');
            $stmt->execute([
                ':sender' => $user['id'],
                ':receiver' => $receiverId,
                ':subject' => $subject,
                ':message' => $message,
            ]);
            flash('success', 'Fee reminder sent.');
        } else {
            flash('error', 'Unable to send reminder for this student.');
        }
        redirect('teacher_portal.php?section=fees');
    }

    // Notification preferences (reuse from student)
    if ($action === 'save_notification_preferences') {
        $emailEnabled = isset($_POST['email_notifications']) ? 1 : 0;
        $smsEnabled = isset($_POST['sms_notifications']) ? 1 : 0;
        $inAppEnabled = isset($_POST['in_app_notifications']) ? 1 : 0;
        $stmt = $pdo->prepare(
            'INSERT INTO student_notification_preferences (user_id, email_notifications, sms_notifications, in_app_notifications) VALUES (:user_id, :email, :sms, :in_app) ON DUPLICATE KEY UPDATE email_notifications = VALUES(email_notifications), sms_notifications = VALUES(sms_notifications), in_app_notifications = VALUES(in_app_notifications)'
        );
        $stmt->execute([
            ':user_id' => $user['id'],
            ':email' => $emailEnabled,
            ':sms' => $smsEnabled,
            ':in_app' => $inAppEnabled,
        ]);
        flash('success', 'Preferences saved.');
        redirect('teacher_portal.php?section=notifications');
    }
}

// Data queries
$teacherStmt = $pdo->prepare('SELECT t.*, u.name FROM teachers t JOIN users u ON u.teacher_id = t.id WHERE t.id = :id');
$teacherStmt->execute([':id' => $teacherId]);
$teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);

$classesStmt = $pdo->prepare('SELECT c.*, COUNT(s.id) as student_count FROM classes c LEFT JOIN students s ON s.class_id = c.id WHERE c.teacher_id = :teacher_id GROUP BY c.id ORDER BY c.name');
$classesStmt->execute([':teacher_id' => $teacherId]);
$classes = $classesStmt->fetchAll();

$subjectsStmt = $pdo->prepare('SELECT DISTINCT s.id, s.name FROM subjects s JOIN class_subjects cs ON cs.subject_id = s.id JOIN classes c ON cs.class_id = c.id WHERE c.teacher_id = :teacher_id ORDER BY s.name');
$subjectsStmt->execute([':teacher_id' => $teacherId]);
$subjects = $subjectsStmt->fetchAll();

$lessonNotesStmt = $pdo->prepare('SELECT ln.*, c.name AS class_name, s.name AS subject_name FROM lesson_notes ln JOIN classes c ON c.id = ln.class_id JOIN subjects s ON s.id = ln.subject_id WHERE ln.teacher_id = :teacher_id ORDER BY ln.uploaded_at DESC LIMIT 10');
$lessonNotesStmt->execute([':teacher_id' => $teacherId]);
$lessonNotes = $lessonNotesStmt->fetchAll();

$inboxStmt = $pdo->prepare('SELECT m.*, u.name AS sender_name FROM student_messages m JOIN users u ON u.id = m.sender_user_id WHERE m.receiver_user_id = :user_id AND m.is_read = 0 ORDER BY m.created_at DESC LIMIT 20');
$inboxStmt->execute([':user_id' => $user['id']]);
$unreadMessages = $inboxStmt->fetchAll();

$contactListStmt = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('student', 'admin') ORDER BY role, name")->fetchAll();

$prefStmt = $pdo->prepare('SELECT * FROM student_notification_preferences WHERE user_id = :user_id');
$prefStmt->execute([':user_id' => $user['id']]);
$notificationPrefs = $prefStmt->fetch() ?: ['email_notifications' => 1, 'sms_notifications' => 0, 'in_app_notifications' => 1];

$classIds = array_map('intval', array_column($classes, 'id'));
$feeFilter = trim($_GET['fee_filter'] ?? 'all');
$allowedFeeFilters = ['all', 'paid', 'partial', 'unpaid'];
if (!in_array($feeFilter, $allowedFeeFilters, true)) {
    $feeFilter = 'all';
}

$studentFeeStatuses = [];
$recentPayments = [];
$dueSoonCount = 0;
$unpaidCount = 0;
$partiallyPaidCount = 0;
$fullyPaidCount = 0;

if (!empty($classIds)) {
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));

    $studentFeeSql = "
        SELECT
            s.id AS student_id,
            s.name AS student_name,
            c.name AS class_name,
            u.id AS student_user_id,
            COALESCE(SUM(cf.amount), 0) AS total_due,
            COALESCE(SUM(sf.amount_paid), 0) AS total_paid
        FROM students s
        INNER JOIN classes c ON c.id = s.class_id
        LEFT JOIN users u ON u.student_id = s.id AND u.role = 'student'
        LEFT JOIN class_fees cf ON cf.class_id = c.id AND cf.is_active = 1
        LEFT JOIN student_fees sf ON sf.class_fee_id = cf.id AND sf.student_id = s.id
        WHERE s.class_id IN ($placeholders)
        GROUP BY s.id, s.name, c.name, u.id
        ORDER BY c.name, s.name
    ";
    $stmt = $pdo->prepare($studentFeeSql);
    $stmt->execute($classIds);
    $rawStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawStatuses as $row) {
        $due = (float) $row['total_due'];
        $paid = (float) $row['total_paid'];
        $balance = max(0, $due - $paid);

        if ($due <= 0) {
            $statusKey = 'unpaid';
            $statusLabel = 'Not Paid';
            $statusIcon = '❌';
            $statusClass = 'danger';
        } elseif ($paid >= $due) {
            $statusKey = 'paid';
            $statusLabel = 'Paid in full';
            $statusIcon = '✅';
            $statusClass = 'success';
            $fullyPaidCount++;
        } elseif ($paid > 0) {
            $statusKey = 'partial';
            $statusLabel = 'Partially paid';
            $statusIcon = '⚠️';
            $statusClass = 'warning';
            $partiallyPaidCount++;
        } else {
            $statusKey = 'unpaid';
            $statusLabel = 'Not Paid';
            $statusIcon = '❌';
            $statusClass = 'danger';
            $unpaidCount++;
        }

        if ($feeFilter !== 'all' && $statusKey !== $feeFilter) {
            continue;
        }

        $row['balance'] = $balance;
        $row['status_key'] = $statusKey;
        $row['status_label'] = $statusLabel;
        $row['status_icon'] = $statusIcon;
        $row['status_class'] = $statusClass;
        $row['exam_eligible'] = $statusKey === 'paid';
        $row['assignment_access'] = $statusKey === 'paid' || $statusKey === 'partial';
        $studentFeeStatuses[] = $row;
    }

    $dueSoonSql = "
        SELECT COUNT(DISTINCT s.id)
        FROM students s
        INNER JOIN class_fees cf ON cf.class_id = s.class_id AND cf.is_active = 1
        LEFT JOIN student_fees sf ON sf.student_id = s.id AND sf.class_fee_id = cf.id
        WHERE s.class_id IN ($placeholders)
          AND cf.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND COALESCE(sf.amount_paid, 0) < cf.amount
    ";
    $stmt = $pdo->prepare($dueSoonSql);
    $stmt->execute($classIds);
    $dueSoonCount = (int) $stmt->fetchColumn();

    $recentPaymentsSql = "
        SELECT s.name AS student_name, c.name AS class_name, sf.amount_paid, sf.payment_date
        FROM student_fees sf
        INNER JOIN students s ON s.id = sf.student_id
        INNER JOIN class_fees cf ON cf.id = sf.class_fee_id
        INNER JOIN classes c ON c.id = s.class_id
        WHERE s.class_id IN ($placeholders)
        ORDER BY sf.payment_date DESC
        LIMIT 6
    ";
    $stmt = $pdo->prepare($recentPaymentsSql);
    $stmt->execute($classIds);
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$validSections = ['dashboard', 'classes', 'subjects', 'materials', 'fees', 'communication', 'students', 'notifications'];
$activeSection = trim($_GET['section'] ?? 'dashboard');
if (!in_array($activeSection, $validSections)) {
    $activeSection = 'dashboard';
}

include __DIR__ . '/inc/header.php';
?>
<div class="container mt-4 mb-5">
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash('error')): ?><div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="p-4 rounded mb-4" style="background: linear-gradient(120deg, #28a745, #20c997); color: #fff;">
        <h2 class="mb-1"><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Portal</h2>
        <p class="mb-0">Welcome, <?= htmlspecialchars($teacher['name'] ?? $user['name']) ?></p>
    </div>

    <?php
    $portalNav = [
        'dashboard' => 'Dashboard',
        'classes' => 'My Classes',
        'subjects' => 'Subjects',
        'materials' => 'Course Materials',
        'fees' => 'Fees Status',
        'communication' => 'Communication',
        'students' => 'Students',
        'notifications' => 'Notifications',
    ];
    ?>
    <nav class="nav nav-pills flex-wrap gap-1 mb-3 border rounded p-2 bg-white shadow-sm">
        <?php foreach ($portalNav as $navKey => $navLabel): ?>
            <a class="nav-link py-2 px-3 <?= $activeSection === $navKey ? 'active' : '' ?>"
               href="teacher_portal.php?section=<?= urlencode($navKey) ?>"><?= htmlspecialchars($navLabel) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($activeSection === 'dashboard'): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card text-center p-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-users fa-3x mb-3 opacity-75"></i>
                    <h3><?= count($classes) ?></h3>
                    <p class="mb-0">Classes Taught</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-4" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <i class="fas fa-file-alt fa-3x mb-3 opacity-75"></i>
                    <h3><?= count($lessonNotes) ?></h3>
                    <p class="mb-0">Materials Uploaded</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-4" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <i class="fas fa-envelope fa-3x mb-3 opacity-75"></i>
                    <h3><?= count($unreadMessages) ?></h3>
                    <p class="mb-0">Unread Messages</p>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="alert alert-danger mb-0"><strong><?= $unpaidCount ?></strong> students not paid ❌</div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-warning mb-0"><strong><?= $partiallyPaidCount ?></strong> students partially paid ⚠️</div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-info mb-0"><strong><?= $dueSoonCount ?></strong> students near deadline</div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h6>Recent Lesson Notes</h6></div>
                    <?php foreach (array_slice($lessonNotes, 0, 3) as $note): ?>
                        <div class="border-bottom p-3">
                            <strong><?= htmlspecialchars($note['title']) ?></strong><br>
                            <small><?= htmlspecialchars($note['class_name']) ?> - <?= htmlspecialchars($note['subject_name']) ?> • <?= date('M j', strtotime($note['uploaded_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                    <div class="card-footer text-center">
                        <a href="?section=materials" class="btn btn-primary btn-sm">Manage Materials</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h6>Recent Messages</h6></div>
                    <?php foreach (array_slice($unreadMessages, 0, 3) as $msg): ?>
                        <div class="border-bottom p-3">
                            <strong><?= htmlspecialchars($msg['subject']) ?></strong><br>
                            <small>From: <?= htmlspecialchars($msg['sender_name']) ?> • <?= date('M j, H:i', strtotime($msg['created_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                    <div class="card-footer text-center">
                        <a href="?section=communication" class="btn btn-primary btn-sm">View All Messages</a>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($activeSection === 'classes'): ?>
        <div class="card p-4">
            <h5>My Classes</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Class</th><th>Students</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($class['name']) ?></strong></td>
                                <td><?= $class['student_count'] ?></td>
                                <td>
                                    <a href="lesson_notes.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-primary">Upload Materials</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($activeSection === 'subjects'): ?>
        <div class="card p-4">
            <h5>Subjects Taught</h5>
            <div class="row">
                <?php foreach ($subjects as $subject): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h6><?= htmlspecialchars($subject['name']) ?></h6>
                                <a href="#" class="btn btn-sm btn-outline-primary">View Classes</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php elseif ($activeSection === 'materials'): ?>
        <div class="card p-4">
            <h5>Course Materials</h5>
            <p>Upload notes, PDFs, videos for your classes:</p>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <a href="lesson_notes.php" class="btn btn-success w-100 h-100 p-4 text-center">
                        <i class="fas fa-upload fa-2x mb-2 d-block"></i>
                        <h6>Upload Lesson Notes</h6>
                    </a>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Recent Uploads</div>
                        <div class="card-body">
                            <?php foreach (array_slice($lessonNotes, 0, 5) as $note): ?>
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                    <span><?= htmlspecialchars($note['title']) ?></span>
                                    <small><?= htmlspecialchars($note['class_name']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($activeSection === 'fees'): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Student Fee Status</h5>
                        <form method="get" class="d-flex gap-2">
                            <input type="hidden" name="section" value="fees">
                            <select name="fee_filter" class="form-select">
                                <option value="all" <?= $feeFilter === 'all' ? 'selected' : '' ?>>All</option>
                                <option value="paid" <?= $feeFilter === 'paid' ? 'selected' : '' ?>>Paid in full</option>
                                <option value="partial" <?= $feeFilter === 'partial' ? 'selected' : '' ?>>Partially paid</option>
                                <option value="unpaid" <?= $feeFilter === 'unpaid' ? 'selected' : '' ?>>Not paid</option>
                            </select>
                            <button class="btn btn-outline-primary" type="submit">Filter</button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Status</th>
                                    <th>Paid</th>
                                    <th>Owes</th>
                                    <th>Exam Eligibility</th>
                                    <th>Assignment Access</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentFeeStatuses as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                                        <td><?= htmlspecialchars($row['class_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= htmlspecialchars($row['status_class']) ?>">
                                                <?= $row['status_icon'] ?> <?= htmlspecialchars($row['status_label']) ?>
                                            </span>
                                        </td>
                                        <td>GHc <?= number_format((float) $row['total_paid'], 2) ?></td>
                                        <td>GHc <?= number_format((float) $row['balance'], 2) ?></td>
                                        <td>
                                            <?= $row['exam_eligible'] ? '<span class="text-success">Eligible</span>' : '<span class="text-danger">Restricted</span>' ?>
                                        </td>
                                        <td>
                                            <?= $row['assignment_access'] ? '<span class="text-success">Allowed</span>' : '<span class="text-danger">Blocked</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ((float) $row['balance'] > 0 && !empty($row['student_user_id'])): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="send_fee_reminder">
                                                    <input type="hidden" name="receiver_user_id" value="<?= intval($row['student_user_id']) ?>">
                                                    <input type="hidden" name="student_name" value="<?= htmlspecialchars($row['student_name']) ?>">
                                                    <input type="hidden" name="balance" value="<?= htmlspecialchars((string) $row['balance']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Send Reminder</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($studentFeeStatuses)): ?>
                                    <tr><td colspan="8" class="text-center text-muted">No students matched this filter.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted mt-3 mb-0">
                        Teachers have read-only access to fee data. Payment creation/confirmation remains admin/account roles.
                    </p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card p-4 mb-4">
                    <h6>Fee Alerts</h6>
                    <ul class="mb-0">
                        <li><?= $unpaidCount ?> students not paid.</li>
                        <li><?= $partiallyPaidCount ?> students partially paid.</li>
                        <li><?= $dueSoonCount ?> students have fees due within 7 days.</li>
                    </ul>
                </div>
                <div class="card p-4">
                    <h6>Recent Payments</h6>
                    <?php if (!empty($recentPayments)): ?>
                        <?php foreach ($recentPayments as $payment): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <strong><?= htmlspecialchars($payment['student_name']) ?></strong><br>
                                <small><?= htmlspecialchars($payment['class_name']) ?> • GHc <?= number_format((float) $payment['amount_paid'], 2) ?></small><br>
                                <small class="text-muted"><?= htmlspecialchars($payment['payment_date']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No payment records yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($activeSection === 'communication'): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <h6>Inbox (<?= count($unreadMessages) ?> unread)</h6>
                        <span class="badge bg-primary"><?= count($unreadMessages) ?></span>
                    </div>
                    <div class="card-body">
                        <?php foreach ($unreadMessages as $msg): ?>
                            <div class="border-bottom py-3">
                                <h6><?= htmlspecialchars($msg['subject']) ?></h6>
                                <p class="mb-1"><?= htmlspecialchars(substr($msg['message'], 0, 100)) ?>...</p>
                                <small>From: <?= htmlspecialchars($msg['sender_name']) ?> • <?= date('M j, H:i', strtotime($msg['created_at'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($unreadMessages)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No messages yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h6>Send Message</h6></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="send_message">
                            <div class="mb-3">
                                <select class="form-control" name="receiver_user_id" required>
                                    <option value="">Select student/admin</option>
                                    <?php foreach ($contactListStmt as $contact): ?>
                                        <option value="<?= intval($contact['id']) ?>"><?= htmlspecialchars($contact['name']) ?> (<?= htmlspecialchars($contact['role']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3"><input class="form-control" name="subject" placeholder="Subject" required></div>
                            <div class="mb-2"><textarea class="form-control" name="message" rows="3" placeholder="Message" required></textarea></div>
                            <button class="btn btn-primary w-100" type="submit">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($activeSection === 'students'): ?>
        <div class="card p-4">
            <h5>Students</h5>
            <p>View students across your classes. Detailed management available via main admin pages.</p>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Class</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr><td><?= htmlspecialchars($class['name']) ?></td><td><?= $class['student_count'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($activeSection === 'notifications'): ?>
        <div class="card p-4">
            <h5>Notifications</h5>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Stats</div>
                        <ul class="list-unstyled mb-0">
                            <li><?= count($classes) ?> classes</li>
                            <li><?= count($lessonNotes) ?> materials</li>
                            <li><?= count($unreadMessages) ?> unread msgs</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Preferences</div>
                        <form method="post">
                            <input type="hidden" name="action" value="save_notification_preferences">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="email_notifications" <?= (int)$notificationPrefs['email_notifications'] ? 'checked' : '' ?>><label class="form-check-label">Email</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="sms_notifications" <?= (int)$notificationPrefs['sms_notifications'] ? 'checked' : '' ?>><label class="form-check-label">SMS</label></div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="in_app_notifications" <?= (int)$notificationPrefs['in_app_notifications'] ? 'checked' : '' ?>><label class="form-check-label">In-app</label></div>
                            <button class="btn btn-primary btn-sm" type="submit">Save</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/inc/footer.php'; ?>
