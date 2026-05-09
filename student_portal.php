<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$user = currentUser();
if (($user['role'] ?? '') !== 'student') {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Student Portal';
$pdo = getDb();
$studentId = intval($user['student_id'] ?? 0);
if ($studentId <= 0) {
    $stmt = $pdo->prepare('SELECT student_id FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $user['id']]);
    $studentId = intval($stmt->fetchColumn() ?: 0);
}

if ($studentId <= 0) {
    flash('error', 'Your account is not linked to a student profile. Contact admin.');
    header('Location: login.php');
    exit;
}

$uploadDir = __DIR__ . '/uploads/submissions/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $photoPath = null;

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileName = uniqid('student_photo_') . '_' . basename($_FILES['photo']['name']);
            $target = __DIR__ . '/uploads/students/' . $fileName;
            if (!is_dir(__DIR__ . '/uploads/students/')) {
                mkdir(__DIR__ . '/uploads/students/', 0755, true);
            }
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                $photoPath = 'uploads/students/' . $fileName;
            }
        }

        if ($name === '') {
            flash('error', 'Name is required.');
            redirect('student_portal.php?section=profile');
        }

        $sql = 'UPDATE students SET name = :name, contact = :contact';
        if ($photoPath !== null) {
            $sql .= ', photo = :photo';
        }
        $sql .= ' WHERE id = :id';
        $params = [':name' => $name, ':contact' => $contact, ':id' => $studentId];
        if ($photoPath !== null) {
            $params[':photo'] = $photoPath;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmt = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
        $stmt->execute([':name' => $name, ':id' => $user['id']]);

        flash('success', 'Profile updated.');
        redirect('student_portal.php?section=profile');
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            flash('error', 'Current password is incorrect.');
            redirect('student_portal.php?section=settings');
        }
        if (strlen($newPassword) < 8) {
            flash('error', 'New password must be at least 8 characters.');
            redirect('student_portal.php?section=settings');
        }
        if ($newPassword !== $confirmPassword) {
            flash('error', 'New password and confirmation do not match.');
            redirect('student_portal.php?section=settings');
        }

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $user['id']]);
        flash('success', 'Password changed successfully.');
            redirect('student_portal.php?section=settings');
    }

    if ($action === 'submit_assignment') {
        $assignmentId = intval($_POST['assignment_id'] ?? 0);
        $submissionText = trim($_POST['submission_text'] ?? '');
        $filePath = null;

        $feeCheckStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(cf.amount), 0) AS total_due, COALESCE(SUM(sf.amount_paid), 0) AS total_paid
             FROM students s
             LEFT JOIN class_fees cf ON cf.class_id = s.class_id AND cf.is_active = 1
             LEFT JOIN student_fees sf ON sf.class_fee_id = cf.id AND sf.student_id = s.id
             WHERE s.id = :student_id'
        );
        $feeCheckStmt->execute([':student_id' => $studentId]);
        $feeStandingRow = $feeCheckStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_due' => 0, 'total_paid' => 0];
        $isUnpaidForAssignments = ((float) $feeStandingRow['total_due'] <= 0) || ((float) $feeStandingRow['total_paid'] <= 0);
        if ($isUnpaidForAssignments) {
            flash('error', 'Assignment submission is blocked until fees are paid.');
            redirect('student_portal.php?section=assignments');
        }

        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = uniqid('submission_') . '_' . basename($_FILES['submission_file']['name']);
            $target = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target)) {
                $filePath = 'uploads/submissions/' . $fileName;
            }
        }

        if ($assignmentId <= 0 || ($submissionText === '' && $filePath === null)) {
            flash('error', 'Provide text or file for assignment submission.');
            redirect('student_portal.php?section=assignments');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO student_assignment_submissions (assignment_id, student_id, submission_text, file_path, status)
             VALUES (:assignment_id, :student_id, :submission_text, :file_path, :status)
             ON DUPLICATE KEY UPDATE submission_text = VALUES(submission_text), file_path = VALUES(file_path), status = VALUES(status), submitted_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':assignment_id' => $assignmentId,
            ':student_id' => $studentId,
            ':submission_text' => $submissionText !== '' ? $submissionText : null,
            ':file_path' => $filePath,
            ':status' => 'submitted',
        ]);
        flash('success', 'Assignment submitted successfully.');
        redirect('student_portal.php?section=assignments');
    }

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
        redirect('student_portal.php?section=communication');
    }

    if ($action === 'save_notification_preferences') {
        $emailEnabled = isset($_POST['email_notifications']) ? 1 : 0;
        $smsEnabled = isset($_POST['sms_notifications']) ? 1 : 0;
        $inAppEnabled = isset($_POST['in_app_notifications']) ? 1 : 0;
        $stmt = $pdo->prepare(
            'INSERT INTO student_notification_preferences (user_id, email_notifications, sms_notifications, in_app_notifications)
             VALUES (:user_id, :email_notifications, :sms_notifications, :in_app_notifications)
             ON DUPLICATE KEY UPDATE email_notifications = VALUES(email_notifications), sms_notifications = VALUES(sms_notifications), in_app_notifications = VALUES(in_app_notifications)'
        );
        $stmt->execute([
            ':user_id' => $user['id'],
            ':email_notifications' => $emailEnabled,
            ':sms_notifications' => $smsEnabled,
            ':in_app_notifications' => $inAppEnabled,
        ]);
        flash('success', 'Notification preferences saved.');
        redirect('student_portal.php?section=notifications');
    }
}

$profileStmt = $pdo->prepare('SELECT s.*, c.name AS class_name, c.id AS class_id FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = :id LIMIT 1');
$profileStmt->execute([':id' => $studentId]);
$student = $profileStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    flash('error', 'Student profile not found.');
    header('Location: login.php');
    exit;
}

$classId = intval($student['class_id'] ?? 0);

$announcementStmt = $pdo->prepare('SELECT title, body, published_at FROM student_announcements WHERE is_published = 1 AND (class_id IS NULL OR class_id = :class_id) ORDER BY published_at DESC LIMIT 8');
$announcementStmt->execute([':class_id' => $classId]);
$announcements = $announcementStmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($announcements)) {
    $announcementStmt = $pdo->query('SELECT title, body, published_at FROM student_announcements WHERE is_published = 1 ORDER BY published_at DESC LIMIT 8');
    $announcements = $announcementStmt->fetchAll(PDO::FETCH_ASSOC);
}

$eventStmt = $pdo->prepare('SELECT title, description, event_date FROM student_events WHERE event_date >= CURDATE() AND (class_id IS NULL OR class_id = :class_id) ORDER BY event_date ASC LIMIT 8');
$eventStmt->execute([':class_id' => $classId]);
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

$subjectStmt = $pdo->prepare(
    'SELECT sub.id, sub.name, t.name AS teacher_name
     FROM class_subjects cs
     JOIN subjects sub ON sub.id = cs.subject_id
     LEFT JOIN teacher_schedules ts ON ts.class_id = cs.class_id AND ts.subject_id = cs.subject_id
     LEFT JOIN teachers t ON t.id = ts.teacher_id
     WHERE cs.class_id = :class_id
     GROUP BY sub.id, sub.name, t.name
     ORDER BY sub.name'
);
$subjectStmt->execute([':class_id' => $classId]);
$subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

$assignmentStmt = $pdo->prepare(
    'SELECT a.*, sub.name AS subject_name, t.name AS teacher_name, sbs.status AS submission_status, sbs.submitted_at
     FROM student_assignments a
     LEFT JOIN subjects sub ON sub.id = a.subject_id
     LEFT JOIN teachers t ON t.id = a.teacher_id
     LEFT JOIN student_assignment_submissions sbs ON sbs.assignment_id = a.id AND sbs.student_id = :student_id
     WHERE a.class_id = :class_id
     ORDER BY a.due_date ASC'
);
$assignmentStmt->execute([':student_id' => $studentId, ':class_id' => $classId]);
$assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

$scheduleStmt = $pdo->prepare(
    'SELECT ts.day_of_week, sp.period_name, sp.start_time, sp.end_time, sub.name AS subject_name, t.name AS teacher_name
     FROM teacher_schedules ts
     JOIN schedule_periods sp ON sp.id = ts.period_id
     JOIN subjects sub ON sub.id = ts.subject_id
     LEFT JOIN teachers t ON t.id = ts.teacher_id
     WHERE ts.class_id = :class_id AND ts.is_active = 1
     ORDER BY ts.day_of_week, sp.start_time'
);
$scheduleStmt->execute([':class_id' => $classId]);
$timetable = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

$gradesStmt = $pdo->prepare('SELECT g.*, sub.name AS subject_name FROM grades g JOIN subjects sub ON sub.id = g.subject_id WHERE g.student_id = :student_id ORDER BY sub.name');
$gradesStmt->execute([':student_id' => $studentId]);
$grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceStmt = $pdo->prepare('SELECT `date`, status FROM attendance WHERE student_id = :student_id ORDER BY `date` DESC LIMIT 30');
$attendanceStmt->execute([':student_id' => $studentId]);
$attendanceRows = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
$attendanceTotal = count($attendanceRows);
$attendancePresent = 0;
foreach ($attendanceRows as $attendanceRow) {
    if (strtolower((string) $attendanceRow['status']) === 'present') {
        $attendancePresent++;
    }
}
$attendancePercent = $attendanceTotal > 0 ? round(($attendancePresent / $attendanceTotal) * 100, 1) : 0;

$feesStmt = $pdo->prepare(
    'SELECT cf.fee_name, cf.amount, IFNULL(sf.amount_paid, 0) AS amount_paid, cf.due_date
     FROM class_fees cf
     LEFT JOIN student_fees sf ON sf.class_fee_id = cf.id AND sf.student_id = :student_id
     WHERE cf.class_id = :class_id AND cf.is_active = 1
     ORDER BY cf.due_date'
);
$feesStmt->execute([':student_id' => $studentId, ':class_id' => $classId]);
$fees = $feesStmt->fetchAll(PDO::FETCH_ASSOC);
$totalFeeDue = 0.0;
$totalFeePaid = 0.0;
foreach ($fees as $feeRow) {
    $totalFeeDue += (float) $feeRow['amount'];
    $totalFeePaid += (float) $feeRow['amount_paid'];
}
$totalFeeBalance = max(0, $totalFeeDue - $totalFeePaid);
$feeStanding = 'paid';
if ($totalFeeDue <= 0 || $totalFeePaid <= 0) {
    $feeStanding = 'unpaid';
} elseif ($totalFeeBalance > 0) {
    $feeStanding = 'partial';
}
$isExamRestricted = $feeStanding === 'unpaid';
$isAssignmentRestricted = $feeStanding === 'unpaid';

$resourcesStmt = $pdo->prepare('SELECT title, description, file_path, resource_url, created_at FROM student_resources WHERE class_id IS NULL OR class_id = :class_id ORDER BY created_at DESC LIMIT 20');
$resourcesStmt->execute([':class_id' => $classId]);
$resources = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);

$notesStmt = $pdo->prepare('SELECT ln.title, ln.description, ln.file_path, ln.uploaded_at, sub.name AS subject_name FROM lesson_notes ln LEFT JOIN subjects sub ON sub.id = ln.subject_id WHERE ln.class_id = :class_id ORDER BY ln.uploaded_at DESC LIMIT 20');
$notesStmt->execute([':class_id' => $classId]);
$lessonNotes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

$inboxStmt = $pdo->prepare('SELECT m.*, u.name AS sender_name FROM student_messages m JOIN users u ON u.id = m.sender_user_id WHERE m.receiver_user_id = :user_id ORDER BY m.created_at DESC LIMIT 20');
$inboxStmt->execute([':user_id' => $user['id']]);
$inboxMessages = $inboxStmt->fetchAll(PDO::FETCH_ASSOC);

$contactList = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin','teacher') ORDER BY role, name")->fetchAll(PDO::FETCH_ASSOC);

$prefStmt = $pdo->prepare('SELECT * FROM student_notification_preferences WHERE user_id = :user_id LIMIT 1');
$prefStmt->execute([':user_id' => $user['id']]);
$notificationPrefs = $prefStmt->fetch(PDO::FETCH_ASSOC) ?: ['email_notifications' => 1, 'sms_notifications' => 0, 'in_app_notifications' => 1];

$pendingAssignments = 0;
foreach ($assignments as $assignment) {
    if (($assignment['submission_status'] ?? '') !== 'submitted') {
        $pendingAssignments++;
    }
}
$unreadMessages = 0;
foreach ($inboxMessages as $message) {
    if (!(int) $message['is_read']) {
        $unreadMessages++;
    }
}

$validSections = ['profile', 'subjects', 'assignments', 'timetable', 'grades', 'attendance', 'communication', 'fees', 'resources', 'notifications', 'settings'];
$activeSection = trim($_GET['section'] ?? 'profile');
if (!in_array($activeSection, $validSections, true)) {
    $activeSection = 'profile';
}

$dayNames = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
];

include __DIR__ . '/inc/header.php';
?>
<div class="container mt-4 mb-5">
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash('error')): ?><div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="p-4 rounded mb-4" style="background: linear-gradient(120deg, #1f7ae0, #29b6f6); color: #fff;">
        <h2 class="mb-1"><i class="fas fa-user-graduate me-2"></i>Student Portal</h2>
        <p class="mb-0">Welcome, <?= htmlspecialchars($student['name']) ?>. Class: <?= htmlspecialchars($student['class_name'] ?? 'Not assigned') ?></p>
    </div>

    <?php
    $portalNav = [
        'dashboard' => 'Dashboard',
        'profile' => 'Profile',
        'subjects' => 'Courses',
        'assignments' => 'Assignments',
        'timetable' => 'Timetable',
        'grades' => 'Grades',
        'attendance' => 'Attendance',
        'communication' => 'Communication',
        'fees' => 'Fees',
        'resources' => 'Resources',
        'notifications' => 'Notifications',
        'settings' => 'Settings',
    ];
    ?>
    <nav class="nav nav-pills flex-wrap gap-1 mb-3 border rounded p-2 bg-white shadow-sm" aria-label="Student portal sections">
        <?php foreach ($portalNav as $navKey => $navLabel): ?>
            <a class="nav-link py-2 px-3 <?= $activeSection === $navKey ? 'active' : '' ?>"
               href="student_portal.php?section=<?= urlencode($navKey) ?>"><?= htmlspecialchars($navLabel) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($activeSection === 'dashboard'): ?>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="card p-3"><small>Attendance %</small><h4><?= htmlspecialchars((string) $attendancePercent) ?>%</h4></div></div>
                <div class="col-md-3"><div class="card p-3"><small>Subjects</small><h4><?= count($subjects) ?></h4></div></div>
                <div class="col-md-3"><div class="card p-3"><small>Pending Assignments</small><h4><?= $pendingAssignments ?></h4></div></div>
                <div class="col-md-3"><div class="card p-3"><small>Unread Messages</small><h4><?= $unreadMessages ?></h4></div></div>
            </div>
            <div class="alert <?= $feeStanding === 'paid' ? 'alert-success' : ($feeStanding === 'partial' ? 'alert-warning' : 'alert-danger') ?>">
                <strong>Fee Status:</strong>
                <?php if ($feeStanding === 'paid'): ?>
                    Paid in full ✅
                <?php elseif ($feeStanding === 'partial'): ?>
                    Partially paid ⚠️ (Outstanding GHc <?= number_format($totalFeeBalance, 2) ?>)
                <?php else: ?>
                    Not paid ❌
                <?php endif; ?>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card p-3 h-100">
                        <h5>Announcements</h5>
                        <?php if (!empty($announcements)): ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="border-bottom mb-2 pb-2">
                                    <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                                    <p class="mb-1"><?= htmlspecialchars($announcement['body']) ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($announcement['published_at']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>No announcements available yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card p-3 h-100">
                        <h5>Upcoming Events / Exams</h5>
                        <?php foreach ($events as $event): ?>
                            <div class="border-bottom mb-2 pb-2">
                                <strong><?= htmlspecialchars($event['title']) ?></strong>
                                <p class="mb-1"><?= htmlspecialchars($event['description'] ?? '') ?></p>
                                <small class="text-muted"><?= htmlspecialchars($event['event_date']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
    <?php elseif ($activeSection === 'profile'): ?>
            <div class="card p-3">
                <h5>Personal Profile</h5>
                <form method="post" enctype="multipart/form-data" class="row g-2">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="col-md-6"><input class="form-control" name="name" value="<?= htmlspecialchars($student['name']) ?>" required></div>
                    <div class="col-md-6"><input class="form-control" value="<?= htmlspecialchars((string) ($student['class_name'] ?? '')) ?>" disabled></div>
                    <div class="col-md-6"><input class="form-control" name="contact" value="<?= htmlspecialchars((string) ($student['contact'] ?? '')) ?>" placeholder="Email or phone"></div>
                    <div class="col-md-6"><input class="form-control" type="file" name="photo"></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Update Profile</button></div>
                </form>
            </div>
    <?php elseif ($activeSection === 'subjects'): ?>
            <div class="card p-3">
                <h5>Enrolled Subjects</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Subject</th><th>Teacher</th></tr></thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <tr><td><?= htmlspecialchars($subject['name']) ?></td><td><?= htmlspecialchars((string) ($subject['teacher_name'] ?? 'TBA')) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    <?php elseif ($activeSection === 'assignments'): ?>
            <div class="card p-3">
                <h5>Assignments & Homework</h5>
                <?php if ($isAssignmentRestricted): ?>
                    <div class="alert alert-danger">
                        Assignment submission is currently blocked because your school fees are unpaid. Please contact administration after payment.
                    </div>
                <?php endif; ?>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-1"><?= htmlspecialchars($assignment['title']) ?></h6>
                            <span class="badge <?= ($assignment['submission_status'] ?? '') === 'submitted' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= ($assignment['submission_status'] ?? '') === 'submitted' ? 'Submitted' : 'Pending' ?>
                            </span>
                        </div>
                        <p class="mb-1"><?= htmlspecialchars((string) ($assignment['description'] ?? '')) ?></p>
                        <small class="text-muted">Subject: <?= htmlspecialchars((string) ($assignment['subject_name'] ?? 'N/A')) ?> | Due: <?= htmlspecialchars($assignment['due_date']) ?></small>
                        <?php if (!empty($assignment['attachment_path'])): ?>
                            <div><a href="<?= htmlspecialchars($assignment['attachment_path']) ?>" target="_blank">Download Assignment File</a></div>
                        <?php endif; ?>
                        <?php if (!$isAssignmentRestricted): ?>
                            <form method="post" enctype="multipart/form-data" class="mt-2">
                                <input type="hidden" name="action" value="submit_assignment">
                                <input type="hidden" name="assignment_id" value="<?= intval($assignment['id']) ?>">
                                <div class="row g-2">
                                    <div class="col-md-6"><input class="form-control" name="submission_text" placeholder="Submission text"></div>
                                    <div class="col-md-4"><input class="form-control" type="file" name="submission_file"></div>
                                    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Submit</button></div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
    <?php elseif ($activeSection === 'timetable'): ?>
            <div class="card p-3">
                <h5>Class Timetable</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Day</th><th>Period</th><th>Time</th><th>Subject</th><th>Teacher</th></tr></thead>
                        <tbody>
                            <?php foreach ($timetable as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dayNames[(int) $row['day_of_week']] ?? (string) $row['day_of_week']) ?></td>
                                    <td><?= htmlspecialchars((string) $row['period_name']) ?></td>
                                    <td><?= htmlspecialchars((string) $row['start_time']) ?> - <?= htmlspecialchars((string) $row['end_time']) ?></td>
                                    <td><?= htmlspecialchars((string) $row['subject_name']) ?></td>
                                    <td><?= htmlspecialchars((string) ($row['teacher_name'] ?? 'TBA')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    <?php elseif ($activeSection === 'grades'): ?>
            <div class="card p-3">
                <h5>Grades / Results</h5>
                <?php if ($isExamRestricted): ?>
                    <div class="alert alert-danger">
                        Results are currently restricted because your fees are unpaid. Please settle fees to view grades.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Subject</th><th>SBA</th><th>Exam</th><th>Grade</th><th>Remark</th></tr></thead>
                        <tbody>
                            <?php foreach ($grades as $grade): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $grade['subject_name']) ?></td>
                                    <td><?= htmlspecialchars((string) ($grade['sba_score'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) ($grade['exam_score'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) ($grade['grade'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) ($grade['remark'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
    <?php elseif ($activeSection === 'attendance'): ?>
            <div class="card p-3">
                <h5>Attendance</h5>
                <p>Present rate (recent): <strong><?= htmlspecialchars((string) $attendancePercent) ?>%</strong></p>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($attendanceRows as $row): ?>
                                <tr><td><?= htmlspecialchars($row['date']) ?></td><td><?= htmlspecialchars(ucfirst((string) $row['status'])) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    <?php elseif ($activeSection === 'communication'): ?>
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card p-3 h-100">
                        <h5>Messages Inbox</h5>
                        <?php foreach ($inboxMessages as $message): ?>
                            <div class="border-bottom mb-2 pb-2">
                                <strong><?= htmlspecialchars($message['subject']) ?></strong> from <?= htmlspecialchars($message['sender_name']) ?>
                                <p class="mb-1"><?= htmlspecialchars($message['message']) ?></p>
                                <small class="text-muted"><?= htmlspecialchars($message['created_at']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card p-3 h-100">
                        <h5>Send Message</h5>
                        <form method="post">
                            <input type="hidden" name="action" value="send_message">
                            <div class="mb-2">
                                <select class="form-control" name="receiver_user_id" required>
                                    <option value="">Select teacher/admin</option>
                                    <?php foreach ($contactList as $contact): ?>
                                        <option value="<?= intval($contact['id']) ?>"><?= htmlspecialchars($contact['name']) ?> (<?= htmlspecialchars($contact['role']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2"><input class="form-control" name="subject" placeholder="Subject" required></div>
                            <div class="mb-2"><textarea class="form-control" name="message" rows="4" placeholder="Message" required></textarea></div>
                            <button class="btn btn-primary" type="submit">Send</button>
                        </form>
                    </div>
                </div>
            </div>
    <?php elseif ($activeSection === 'fees'): ?>
            <div class="card p-3">
                <h5>Fees / Payments</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Fee Item</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Due Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($fees as $fee): ?>
                                <?php $balance = (float) $fee['amount'] - (float) $fee['amount_paid']; ?>
                                <tr>
                                    <td><?= htmlspecialchars($fee['fee_name']) ?></td>
                                    <td><?= number_format((float) $fee['amount'], 2) ?></td>
                                    <td><?= number_format((float) $fee['amount_paid'], 2) ?></td>
                                    <td><?= number_format($balance, 2) ?></td>
                                    <td><?= htmlspecialchars((string) $fee['due_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted mb-0">Online payment can be integrated later using a payment gateway.</p>
            </div>
    <?php elseif ($activeSection === 'resources'): ?>
            <div class="card p-3 mb-3">
                <h5>Resources / Library</h5>
                <?php foreach ($resources as $resource): ?>
                    <div class="border-bottom mb-2 pb-2">
                        <strong><?= htmlspecialchars($resource['title']) ?></strong>
                        <p class="mb-1"><?= htmlspecialchars((string) ($resource['description'] ?? '')) ?></p>
                        <?php if (!empty($resource['file_path'])): ?><a href="<?= htmlspecialchars($resource['file_path']) ?>" target="_blank">Download file</a><?php endif; ?>
                        <?php if (!empty($resource['resource_url'])): ?> | <a href="<?= htmlspecialchars($resource['resource_url']) ?>" target="_blank">Open link</a><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card p-3">
                <h6>Lesson Notes</h6>
                <?php foreach ($lessonNotes as $note): ?>
                    <div class="border-bottom mb-2 pb-2">
                        <strong><?= htmlspecialchars($note['title']) ?></strong> (<?= htmlspecialchars((string) ($note['subject_name'] ?? 'General')) ?>)
                        <div><a href="<?= htmlspecialchars($note['file_path']) ?>" target="_blank">Download note</a></div>
                    </div>
                <?php endforeach; ?>
            </div>
    <?php elseif ($activeSection === 'notifications'): ?>
            <div class="card p-3">
                <h5>Notifications</h5>
                <ul>
                    <li><?= $pendingAssignments ?> pending assignment(s).</li>
                    <li><?= $unreadMessages ?> unread message(s).</li>
                    <li><?= count($announcements) ?> recent announcement(s).</li>
                    <li><?= count($events) ?> upcoming event(s).</li>
                </ul>
                <hr>
                <h6>Notification Preferences</h6>
                <form method="post">
                    <input type="hidden" name="action" value="save_notification_preferences">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" <?= (int) $notificationPrefs['email_notifications'] ? 'checked' : '' ?>><label class="form-check-label" for="email_notifications">Email notifications</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="sms_notifications" id="sms_notifications" <?= (int) $notificationPrefs['sms_notifications'] ? 'checked' : '' ?>><label class="form-check-label" for="sms_notifications">SMS notifications</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="in_app_notifications" id="in_app_notifications" <?= (int) $notificationPrefs['in_app_notifications'] ? 'checked' : '' ?>><label class="form-check-label" for="in_app_notifications">In-app notifications</label></div>
                    <button class="btn btn-primary" type="submit">Save Preferences</button>
                </form>
            </div>
    <?php elseif ($activeSection === 'settings'): ?>
            <div class="card p-3">
                <h5>Settings</h5>
                <form method="post">
                    <input type="hidden" name="action" value="change_password">
                    <div class="row g-2">
                        <div class="col-md-4"><input class="form-control" type="password" name="current_password" placeholder="Current password" required></div>
                        <div class="col-md-4"><input class="form-control" type="password" name="new_password" placeholder="New password" required></div>
                        <div class="col-md-4"><input class="form-control" type="password" name="confirm_password" placeholder="Confirm new password" required></div>
                    </div>
                    <button class="btn btn-primary mt-2" type="submit">Change Password</button>
                </form>
            </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/inc/footer.php'; ?>
