<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/services.php';

requireRole('admin');
$pageTitle = 'Student Portal Manager';
$pdo = getDb();
$admin = currentUser();

// Initialize financial services
$feeService = new FeeService($pdo);
$studentService = new StudentService($pdo);

$uploadDir = __DIR__ . '/uploads/student_portal/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_announcement') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $classId = intval($_POST['class_id'] ?? 0);
        if ($title === '' || $body === '') {
            flash('error', 'Announcement title and body are required.');
            redirect('admin_student_portal.php');
        }
        $stmt = $pdo->prepare('INSERT INTO student_announcements (title, body, class_id, created_by) VALUES (:title, :body, :class_id, :created_by)');
        $stmt->execute([
            ':title' => $title,
            ':body' => $body,
            ':class_id' => $classId ?: null,
            ':created_by' => $admin['id'],
        ]);
        flash('success', 'Announcement posted.');
        redirect('admin_student_portal.php');
    }

    if ($action === 'add_event') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate = $_POST['event_date'] ?? '';
        $classId = intval($_POST['class_id'] ?? 0);
        if ($title === '' || $eventDate === '') {
            flash('error', 'Event title and date are required.');
            redirect('admin_student_portal.php');
        }
        $stmt = $pdo->prepare('INSERT INTO student_events (title, description, event_date, class_id, created_by) VALUES (:title, :description, :event_date, :class_id, :created_by)');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':event_date' => $eventDate,
            ':class_id' => $classId ?: null,
            ':created_by' => $admin['id'],
        ]);
        flash('success', 'Event added.');
        redirect('admin_student_portal.php');
    }

    if ($action === 'add_assignment') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $classId = intval($_POST['class_id'] ?? 0);
        $subjectId = intval($_POST['subject_id'] ?? 0);
        $teacherId = intval($_POST['teacher_id'] ?? 0);
        $dueDate = $_POST['due_date'] ?? '';
        if ($title === '' || $classId <= 0 || $dueDate === '') {
            flash('error', 'Assignment title, class, and due date are required.');
            redirect('admin_student_portal.php');
        }

        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $fileName = uniqid('assignment_') . '_' . basename($_FILES['attachment']['name']);
            $target = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                $attachmentPath = 'uploads/student_portal/' . $fileName;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO student_assignments (title, description, class_id, subject_id, teacher_id, due_date, attachment_path, created_by)
             VALUES (:title, :description, :class_id, :subject_id, :teacher_id, :due_date, :attachment_path, :created_by)'
        );
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':class_id' => $classId,
            ':subject_id' => $subjectId ?: null,
            ':teacher_id' => $teacherId ?: null,
            ':due_date' => $dueDate,
            ':attachment_path' => $attachmentPath,
            ':created_by' => $admin['id'],
        ]);
        flash('success', 'Assignment posted.');
        redirect('admin_student_portal.php');
    }

    if ($action === 'add_resource') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $classId = intval($_POST['class_id'] ?? 0);
        $subjectId = intval($_POST['subject_id'] ?? 0);
        $resourceUrl = trim($_POST['resource_url'] ?? '');
        if ($title === '') {
            flash('error', 'Resource title is required.');
            redirect('admin_student_portal.php');
        }

        $filePath = null;
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = uniqid('resource_') . '_' . basename($_FILES['resource_file']['name']);
            $target = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $target)) {
                $filePath = 'uploads/student_portal/' . $fileName;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO student_resources (title, description, class_id, subject_id, file_path, resource_url, created_by)
             VALUES (:title, :description, :class_id, :subject_id, :file_path, :resource_url, :created_by)'
        );
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':class_id' => $classId ?: null,
            ':subject_id' => $subjectId ?: null,
            ':file_path' => $filePath,
            ':resource_url' => $resourceUrl !== '' ? $resourceUrl : null,
            ':created_by' => $admin['id'],
        ]);
        flash('success', 'Resource published.');
        redirect('admin_student_portal.php');
    }

    if ($action === 'send_message') {
        $receiverId = intval($_POST['receiver_user_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($receiverId <= 0 || $subject === '' || $message === '') {
            flash('error', 'Receiver, subject, and message are required.');
            redirect('admin_student_portal.php');
        }
        $stmt = $pdo->prepare('INSERT INTO student_messages (sender_user_id, receiver_user_id, subject, message) VALUES (:sender, :receiver, :subject, :message)');
        $stmt->execute([
            ':sender' => $admin['id'],
            ':receiver' => $receiverId,
            ':subject' => $subject,
            ':message' => $message,
        ]);
        flash('success', 'Message sent.');
        redirect('admin_student_portal.php');
    }

    if ($action === 'record_payment') {
        $studentId = intval($_POST['student_id']);
        $classFeeId = intval($_POST['class_fee_id']);
        $amount = floatval($_POST['amount']);
        $paymentMethod = trim($_POST['payment_method']);
        $receiptNumber = trim($_POST['receipt_number'] ?? '');

        if ($amount > 0) {
            $feeService->recordPayment($studentId, $classFeeId, $amount, $paymentMethod, $receiptNumber ?: null);
            flash('success', 'Payment recorded successfully.');
            redirect('admin_student_portal.php?tab=financial');
        } else {
            flash('error', 'Invalid payment amount.');
            redirect('admin_student_portal.php?tab=financial');
        }
    }
}

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();
$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();
$teachers = $pdo->query('SELECT t.id, t.name FROM teachers t ORDER BY t.name')->fetchAll();
$students = $pdo->query("SELECT u.id, u.name, u.username FROM users u WHERE u.role = 'student' ORDER BY u.name")->fetchAll();

$recentAnnouncements = $pdo->query('SELECT a.title, a.published_at, c.name AS class_name FROM student_announcements a LEFT JOIN classes c ON c.id = a.class_id ORDER BY a.id DESC LIMIT 5')->fetchAll();
$recentAssignments = $pdo->query('SELECT sa.title, sa.due_date, c.name AS class_name FROM student_assignments sa LEFT JOIN classes c ON c.id = sa.class_id ORDER BY sa.id DESC LIMIT 5')->fetchAll();
$feeFilter = trim($_GET['fee_filter'] ?? 'all');
$allowedFeeFilters = ['all', 'paid', 'partial', 'unpaid'];
if (!in_array($feeFilter, $allowedFeeFilters, true)) {
    $feeFilter = 'all';
}

$feeStatusRows = $pdo->query(
    "SELECT
        s.id AS student_id,
        s.name AS student_name,
        c.name AS class_name,
        COALESCE(SUM(cf.amount), 0) AS total_due,
        COALESCE(SUM(sf.amount_paid), 0) AS total_paid
     FROM students s
     LEFT JOIN classes c ON c.id = s.class_id
     LEFT JOIN class_fees cf ON cf.class_id = s.class_id AND cf.is_active = 1
     LEFT JOIN student_fees sf ON sf.student_id = s.id AND sf.class_fee_id = cf.id
     GROUP BY s.id, s.name, c.name
     ORDER BY c.name, s.name"
)->fetchAll();

$feeStats = ['paid' => 0, 'partial' => 0, 'unpaid' => 0];
$filteredFeeStatuses = [];
foreach ($feeStatusRows as $row) {
    $due = (float) $row['total_due'];
    $paid = (float) $row['total_paid'];
    $balance = max(0, $due - $paid);

    if ($due > 0 && $paid >= $due) {
        $statusKey = 'paid';
        $statusLabel = 'Paid in full';
        $statusIcon = '✅';
        $statusClass = 'success';
    } elseif ($paid > 0) {
        $statusKey = 'partial';
        $statusLabel = 'Partially paid';
        $statusIcon = '⚠️';
        $statusClass = 'warning';
    } else {
        $statusKey = 'unpaid';
        $statusLabel = 'Not paid';
        $statusIcon = '❌';
        $statusClass = 'danger';
    }

    $feeStats[$statusKey]++;
    if ($feeFilter !== 'all' && $feeFilter !== $statusKey) {
        continue;
    }

    $row['balance'] = $balance;
    $row['status_key'] = $statusKey;
    $row['status_label'] = $statusLabel;
    $row['status_icon'] = $statusIcon;
    $row['status_class'] = $statusClass;
    $filteredFeeStatuses[] = $row;
}

$recentPayments = $pdo->query(
    "SELECT s.name AS student_name, c.name AS class_name, sf.amount_paid, sf.payment_date
     FROM student_fees sf
     INNER JOIN students s ON s.id = sf.student_id
     INNER JOIN class_fees cf ON cf.id = sf.class_fee_id
     LEFT JOIN classes c ON c.id = s.class_id
     ORDER BY sf.payment_date DESC
     LIMIT 8"
)->fetchAll();

$classFeeTargets = $pdo->query(
    "SELECT
        c.id AS class_id,
        c.name AS class_name,
        COUNT(cf.id) AS active_fee_items,
        COALESCE(SUM(cf.amount), 0) AS total_class_fee_target
     FROM classes c
     LEFT JOIN class_fees cf ON cf.class_id = c.id AND cf.is_active = 1
     GROUP BY c.id, c.name
     ORDER BY c.name"
)->fetchAll();

// Financial management data
$tab = trim($_GET['tab'] ?? 'general');
$selectedClassId = intval($_GET['selected_class'] ?? 0);
$selectedStudentId = intval($_GET['selected_student'] ?? 0);
$paymentStudents = [];
$studentFeeDetails = [];
$paymentHistory = [];

if ($tab === 'financial' && $selectedStudentId > 0) {
    $paymentHistory = $feeService->getPaymentHistory($selectedStudentId);
    $studentFeeDetails = $feeService->getStudentFeesOverview($selectedStudentId);
}

if ($tab === 'financial' && $selectedClassId > 0) {
    $paymentStudents = $studentService->getStudentsByClass($selectedClassId);
}

include __DIR__ . '/inc/sidebar-header.php';
?>

<div class="table-header">
    <div>
        <h1 class="table-title"><i class="fas fa-user-graduate me-2 text-primary"></i>Student Portal Manager</h1>
        <p class="text-muted mb-0">Manage student announcements, assignments, resources, and financial records.</p>
    </div>
    <div>
        <a href="financial.php" class="btn btn-outline-info" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Full Financial Dashboard</a>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $tab === 'general' ? 'active' : '' ?>" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-content" type="button" role="tab">
            <i class="fas fa-home me-2"></i>General
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $tab === 'financial' ? 'active' : '' ?>" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial-content" type="button" role="tab">
            <i class="fas fa-money-bill-wave me-2"></i>Financial Management
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content">

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- General Tab Content -->
<div class="tab-pane fade <?= $tab === 'general' ? 'show active' : '' ?>" id="general-content" role="tabpanel">

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <h5>Announcements</h5>
            <form method="post">
                <input type="hidden" name="action" value="add_announcement">
                <div class="mb-2"><input class="form-control" name="title" placeholder="Title" required></div>
                <div class="mb-2"><textarea class="form-control" name="body" rows="3" placeholder="Announcement details" required></textarea></div>
                <div class="mb-2">
                    <select class="form-control" name="class_id">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Post Announcement</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <h5>Events</h5>
            <form method="post">
                <input type="hidden" name="action" value="add_event">
                <div class="mb-2"><input class="form-control" name="title" placeholder="Event title" required></div>
                <div class="mb-2"><textarea class="form-control" name="description" rows="3" placeholder="Description"></textarea></div>
                <div class="mb-2"><input class="form-control" type="date" name="event_date" required></div>
                <div class="mb-2">
                    <select class="form-control" name="class_id">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Add Event</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <h5>Assignments</h5>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_assignment">
                <div class="mb-2"><input class="form-control" name="title" placeholder="Assignment title" required></div>
                <div class="mb-2"><textarea class="form-control" name="description" rows="3" placeholder="Instructions"></textarea></div>
                <div class="mb-2">
                    <select class="form-control" name="class_id" required>
                        <option value="">Select class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <select class="form-control" name="subject_id">
                        <option value="0">Subject (optional)</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <select class="form-control" name="teacher_id">
                        <option value="0">Teacher (optional)</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2"><input class="form-control" type="datetime-local" name="due_date" required></div>
                <div class="mb-2"><input class="form-control" type="file" name="attachment"></div>
                <button class="btn btn-primary" type="submit">Publish Assignment</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3 h-100">
            <h5>Resources / Library</h5>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_resource">
                <div class="mb-2"><input class="form-control" name="title" placeholder="Resource title" required></div>
                <div class="mb-2"><textarea class="form-control" name="description" rows="3" placeholder="Description"></textarea></div>
                <div class="mb-2">
                    <select class="form-control" name="class_id">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <select class="form-control" name="subject_id">
                        <option value="0">Subject (optional)</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2"><input class="form-control" name="resource_url" placeholder="External URL (optional)"></div>
                <div class="mb-2"><input class="form-control" type="file" name="resource_file"></div>
                <button class="btn btn-primary" type="submit">Publish Resource</button>
            </form>
        </div>
    </div>
    <div class="col-12">
        <div class="card p-3">
            <h5>Message a Student</h5>
            <form method="post">
                <input type="hidden" name="action" value="send_message">
                <div class="row g-2">
                    <div class="col-md-4">
                        <select class="form-control" name="receiver_user_id" required>
                            <option value="">Select student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['name']) ?> (<?= htmlspecialchars($student['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><input class="form-control" name="subject" placeholder="Subject" required></div>
                    <div class="col-md-4"><input class="form-control" name="message" placeholder="Message" required></div>
                </div>
                <button class="btn btn-primary mt-2" type="submit">Send Message</button>
            </form>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card p-3">
            <h5 class="mb-3"><i class="fas fa-school me-2 text-primary"></i>Class Fee Targets</h5>
            <p class="text-muted">Shows the amount each class is expected to pay based on active fee items.</p>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Active Fee Items</th>
                            <th>Amount to Pay (Per Student)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classFeeTargets as $classTarget): ?>
                            <tr>
                                <td><?= htmlspecialchars($classTarget['class_name']) ?></td>
                                <td><?= intval($classTarget['active_fee_items']) ?></td>
                                <td><strong>GHc <?= number_format((float) $classTarget['total_class_fee_target'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($classFeeTargets)): ?>
                            <tr><td colspan="3" class="text-center text-muted">No class fee setup found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fas fa-money-check-alt me-2 text-success"></i>Fees Section</h5>
                <form method="get" class="d-flex gap-2">
                    <select name="fee_filter" class="form-control">
                        <option value="all" <?= $feeFilter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="paid" <?= $feeFilter === 'paid' ? 'selected' : '' ?>>Paid in full</option>
                        <option value="partial" <?= $feeFilter === 'partial' ? 'selected' : '' ?>>Partially paid</option>
                        <option value="unpaid" <?= $feeFilter === 'unpaid' ? 'selected' : '' ?>>Not paid</option>
                    </select>
                    <button class="btn btn-outline-primary" type="submit">Filter</button>
                </form>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="alert alert-success mb-0"><strong><?= $feeStats['paid'] ?></strong> Paid in full ✅</div></div>
                <div class="col-md-4"><div class="alert alert-warning mb-0"><strong><?= $feeStats['partial'] ?></strong> Partially paid ⚠️</div></div>
                <div class="col-md-4"><div class="alert alert-danger mb-0"><strong><?= $feeStats['unpaid'] ?></strong> Not paid ❌</div></div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Total Due</th>
                            <th>Total Paid</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredFeeStatuses as $feeRow): ?>
                            <tr>
                                <td><?= htmlspecialchars($feeRow['student_name']) ?></td>
                                <td><?= htmlspecialchars($feeRow['class_name'] ?? 'Unassigned') ?></td>
                                <td><span class="badge bg-<?= htmlspecialchars($feeRow['status_class']) ?>"><?= $feeRow['status_icon'] ?> <?= htmlspecialchars($feeRow['status_label']) ?></span></td>
                                <td>GHc <?= number_format((float) $feeRow['total_due'], 2) ?></td>
                                <td>GHc <?= number_format((float) $feeRow['total_paid'], 2) ?></td>
                                <td>GHc <?= number_format((float) $feeRow['balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($filteredFeeStatuses)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No records for selected filter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card p-3">
            <h6>Latest Announcements</h6>
            <ul class="mb-0">
                <?php foreach ($recentAnnouncements as $item): ?>
                    <li><?= htmlspecialchars($item['title']) ?> - <small class="text-muted"><?= htmlspecialchars($item['class_name'] ?? 'All classes') ?>, <?= htmlspecialchars($item['published_at']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-3">
            <h6>Latest Assignments</h6>
            <ul class="mb-0">
                <?php foreach ($recentAssignments as $item): ?>
                    <li><?= htmlspecialchars($item['title']) ?> - <small class="text-muted"><?= htmlspecialchars($item['class_name']) ?>, due <?= htmlspecialchars($item['due_date']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-12">
        <div class="card p-3">
            <h6>Recent Payments</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Amount Paid</th>
                            <th>Payment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['student_name']) ?></td>
                                <td><?= htmlspecialchars($payment['class_name'] ?? 'Unassigned') ?></td>
                                <td>GHc <?= number_format((float) $payment['amount_paid'], 2) ?></td>
                                <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentPayments)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No recent payments.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div><!-- End General Tab -->

<!-- Financial Management Tab -->
<div class="tab-pane fade <?= $tab === 'financial' ? 'show active' : '' ?>" id="financial-content" role="tabpanel">

<div class="row g-4">
    <div class="col-lg-3">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Select Class</h6>
            </div>
            <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                <a href="admin_student_portal.php?tab=financial" class="list-group-item list-group-item-action <?= $selectedClassId == 0 ? 'active' : '' ?>">
                    <i class="fas fa-list me-2"></i>All Classes
                </a>
                <?php foreach ($classes as $c): ?>
                <a href="admin_student_portal.php?tab=financial&selected_class=<?= $c['id'] ?>" 
                   class="list-group-item list-group-item-action <?= $c['id'] == $selectedClassId ? 'active' : '' ?>">
                    <i class="fas fa-book me-2"></i><?= htmlspecialchars($c['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <!-- Class Selection View -->
        <?php if ($selectedClassId == 0): ?>
        <div class="card shadow-sm p-4">
            <h5><i class="fas fa-layer-group me-2"></i>Overall Financial Summary</h5>
            <p class="text-muted">Select a class to view details or manage payments.</p>
            <div class="table-responsive mt-4">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Active Fee Items</th>
                            <th>Amount Per Student</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classFeeTargets as $ct): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ct['class_name']) ?></strong></td>
                            <td><?= intval($ct['active_fee_items']) ?></td>
                            <td>GHc <?= number_format((float)$ct['total_class_fee_target'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($selectedClassId > 0 && $selectedStudentId == 0): ?>
        <!-- Class Students View -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Student Fee Status</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Total Due</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentStudents as $student): 
                                $fees = $feeService->getStudentFeesOverview($student['id']);
                                $totalDue = array_sum(array_column($fees, 'amount'));
                                $totalPaid = array_sum(array_column($fees, 'total_paid'));
                                $balance = max(0, $totalDue - $totalPaid);
                                $hasOverdue = count(array_filter($fees, fn($f) => $f['is_overdue'])) > 0;
                                
                                if ($totalDue == 0) {
                                    $status = '<span class="badge bg-secondary">No Fees</span>';
                                } elseif ($balance == 0) {
                                    $status = '<span class="badge bg-success">Paid</span>';
                                } elseif ($hasOverdue) {
                                    $status = '<span class="badge bg-danger">Overdue</span>';
                                } else {
                                    $status = '<span class="badge bg-warning">Pending</span>';
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td>GHc <?= number_format($totalDue, 2) ?></td>
                                <td>GHc <?= number_format($totalPaid, 2) ?></td>
                                <td>GHc <?= number_format($balance, 2) ?></td>
                                <td><?= $status ?></td>
                                <td>
                                    <a href="admin_student_portal.php?tab=financial&selected_class=<?= $selectedClassId ?>&selected_student=<?= $student['id'] ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($selectedStudentId > 0): ?>
        <!-- Student Payment Details -->
        <?php $student = $studentService->getById($selectedStudentId); ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i><?= htmlspecialchars($student['name']) ?></h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">Contact: <?= htmlspecialchars($student['contact'] ?? 'N/A') ?></p>
            </div>
        </div>

        <!-- Fee Summary -->
        <?php 
            $totalFees = array_sum(array_column($studentFeeDetails, 'amount'));
            $totalPaid = array_sum(array_column($studentFeeDetails, 'total_paid'));
            $totalBalance = max(0, $totalFees - $totalPaid);
        ?>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Fees</h6>
                        <h4 class="text-primary">GHc <?= number_format($totalFees, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Paid</h6>
                        <h4 class="text-success">GHc <?= number_format($totalPaid, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Balance</h6>
                        <h4 class="text-warning">GHc <?= number_format($totalBalance, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Progress</h6>
                        <h4 class="text-info"><?= $totalFees > 0 ? round(($totalPaid / $totalFees) * 100) : 0 ?>%</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fee Breakdown -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Fee Breakdown</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentFeeDetails as $fee): ?>
                            <tr class="<?= $fee['is_overdue'] ? 'table-danger' : '' ?>">
                                <td><strong><?= htmlspecialchars($fee['fee_name']) ?></strong></td>
                                <td>GHc <?= number_format($fee['amount'], 2) ?></td>
                                <td>GHc <?= number_format($fee['total_paid'], 2) ?></td>
                                <td>GHc <?= number_format($fee['balance'], 2) ?></td>
                                <td><?= htmlspecialchars($fee['due_date'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($fee['balance'] == 0): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($fee['is_overdue']): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Record Payment Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-money-check-alt me-2"></i>Record Payment</h5>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                    
                    <div class="col-md-4">
                        <label class="form-label">Fee Item</label>
                        <select class="form-control" name="class_fee_id" required>
                            <option value="">Select fee to pay</option>
                            <?php foreach ($studentFeeDetails as $fee): 
                                if ($fee['balance'] > 0): ?>
                                <option value="<?= $fee['class_fee_id'] ?>">
                                    <?= htmlspecialchars($fee['fee_name']) ?> (Balance: GHc <?= number_format($fee['balance'], 2) ?>)
                                </option>
                                <?php endif;
                            endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Amount</label>
                        <input type="number" class="form-control" name="amount" step="0.01" placeholder="0.00" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-control" name="payment_method" required>
                            <option value="">Select method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Receipt #</label>
                        <input type="text" class="form-control" name="receipt_number" placeholder="Optional">
                    </div>
                    
                    <div class="col-12">
                        <button class="btn btn-success" type="submit"><i class="fas fa-save me-2"></i>Record Payment</button>
                        <a href="admin_student_portal.php?tab=financial&selected_class=<?= $selectedClassId ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment History -->
        <?php if (!empty($paymentHistory)): ?>
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Payment History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentHistory as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                                <td><?= htmlspecialchars($payment['fee_name']) ?></td>
                                <td class="text-success">GHc <?= number_format($payment['amount_paid'], 2) ?></td>
                                <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))) ?></td>
                                <td><?= htmlspecialchars($payment['receipt_number'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</div><!-- End Financial Tab -->
</div><!-- End Tab Content -->

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
