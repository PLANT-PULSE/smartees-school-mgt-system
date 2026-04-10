<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Schedule Management';
$pdo = getDb();

// Initialize services
$scheduleService = new ScheduleService($pdo);
$teacherService = new TeacherService($pdo);

$action = $_GET['action'] ?? 'view';
$classId = intval($_GET['class_id'] ?? 0);
$teacherId = intval($_GET['teacher_id'] ?? 0);

// Get classes and teachers
$stmt = $pdo->query('SELECT id, name FROM classes ORDER BY name');
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query('SELECT id, name FROM teachers ORDER BY name');
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query('SELECT id, name FROM subjects ORDER BY name');
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$periods = $scheduleService->getPeriods();
$rooms = $scheduleService->getRooms();

// Get class schedule if specified
$schedule = [];
if ($classId > 0) {
    $schedule = $scheduleService->getClassSchedule($classId);
    $currentClass = array_values(array_filter($classes, fn($c) => $c['id'] == $classId))[0] ?? [];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add_schedule') {
        $classId = intval($_POST['class_id']);
        $teacherId = intval($_POST['teacher_id']);
        $subjectId = intval($_POST['subject_id']);
        $dayOfWeek = intval($_POST['day_of_week']);
        $periodId = intval($_POST['period_id']);
        $roomId = intval($_POST['room_id']) ?: null;

        // Check for conflicts
        if ($scheduleService->hasConflict($teacherId, $dayOfWeek, $periodId, $classId)) {
            flash('error', 'Teacher has a scheduling conflict during this period.');
            redirect("schedule.php?action=view&class_id=$classId");
        }

        $scheduleData = [
            'teacher_id' => $teacherId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'day_of_week' => $dayOfWeek,
            'period_id' => $periodId,
            'room_id' => $roomId,
            'academic_year' => date('Y') . '-' . (date('Y') + 1)
        ];

        try {
            $scheduleId = $scheduleService->createSchedule($scheduleData);
            flash('success', 'Schedule added successfully.');
        } catch (Exception $e) {
            flash('error', 'Failed to add schedule: ' . $e->getMessage());
        }

        redirect("schedule.php?action=view&class_id=$classId");
    } elseif ($action === 'update_schedule') {
        $scheduleId = intval($_POST['schedule_id']);
        $roomId = intval($_POST['room_id']) ?: null;

        $scheduleService->update($scheduleId, ['room_id' => $roomId]);
        flash('success', 'Schedule updated successfully.');
        redirect("schedule.php?action=view&class_id=$classId");
    } elseif ($action === 'delete_schedule') {
        $scheduleId = intval($_POST['schedule_id']);
        $scheduleService->delete($scheduleId);
        flash('success', 'Schedule deleted successfully.');
        redirect("schedule.php?action=view&class_id=$classId");
    }
}

$dayOfWeekNames = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday'
];

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-calendar-alt me-2"></i>Schedule Management
    </h1>

    <?php if ($msg = flash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar: Class Selection -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Classes
                    </h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($classes as $c): ?>
                    <a href="?action=view&class_id=<?= $c['id'] ?>" 
                       class="list-group-item list-group-item-action <?= $c['id'] == $classId ? 'active' : '' ?>">
                        <i class="fas fa-graduation-cap me-2"></i><?= htmlspecialchars($c['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <?php if ($classId > 0): ?>

            <!-- Add Schedule Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Add New Schedule
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="?action=add_schedule&class_id=<?= $classId ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teacher <span class="text-danger">*</span></label>
                                <select name="teacher_id" class="form-control" required>
                                    <option value="">-- Select Teacher --</option>
                                    <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <select name="subject_id" class="form-control" required>
                                    <option value="">-- Select Subject --</option>
                                    <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Day of Week <span class="text-danger">*</span></label>
                                <select name="day_of_week" class="form-control" required>
                                    <option value="">-- Select Day --</option>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>"><?= $dayOfWeekNames[$i] ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period <span class="text-danger">*</span></label>
                                <select name="period_id" class="form-control" required>
                                    <option value="">-- Select Period --</option>
                                    <?php foreach ($periods as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['period_name']) ?> (<?= $p['start_time'] ?> - <?= $p['end_time'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Room (Optional)</label>
                                <select name="room_id" class="form-control">
                                    <option value="">-- No Room Assignment --</option>
                                    <?php foreach ($rooms as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['room_number']) ?> - <?= htmlspecialchars($r['room_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-plus me-2"></i>Add Schedule
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Class Schedule Display -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-table me-2"></i><?= htmlspecialchars($currentClass['name'] ?? 'Schedule') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($schedule)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Period</th>
                                    <th>Time</th>
                                    <th>Teacher</th>
                                    <th>Subject</th>
                                    <th>Room</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedule as $s): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($dayOfWeekNames[$s['day_of_week']] ?? 'N/A') ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($s['period_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($s['start_time']) ?> - <?= htmlspecialchars($s['end_time']) ?></td>
                                    <td><?= htmlspecialchars($s['teacher_name']) ?></td>
                                    <td><?= htmlspecialchars($s['subject_name']) ?></td>
                                    <td><?= htmlspecialchars($s['room_number'] ?? '-') ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['id'] ?>">
                                            <i class="fas fa-edit"></i>Edit
                                        </button>
                                        <form method="post" action="?action=delete_schedule&class_id=<?= $classId ?>" style="display:inline;">
                                            <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this schedule?')">
                                                <i class="fas fa-trash"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="?action=update_schedule&class_id=<?= $classId ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Schedule</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Room Assignment</label>
                                                        <select name="room_id" class="form-control">
                                                            <option value="">-- No Room Assignment --</option>
                                                            <?php foreach ($rooms as $r): ?>
                                                            <option value="<?= $r['id'] ?>" <?= $r['id'] == $s['room_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($r['room_number']) ?> - <?= htmlspecialchars($r['room_name']) ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Timetable View -->
                    <hr>
                    <h6 class="mt-3 mb-3">
                        <i class="fas fa-th-large me-2"></i>Timetable View
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-bordered text-center table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <th><?= $dayOfWeekNames[$i] ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($periods as $period): 
                                    if (in_array($period['period_name'], ['Break', 'Lunch'])) continue;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($period['period_name']) ?></strong><br><small><?= $period['start_time'] ?> - <?= $period['end_time'] ?></small></td>
                                    <?php for ($day = 1; $day <= 5; $day++): 
                                        $daySchedule = array_filter($schedule, fn($s) => $s['day_of_week'] == $day && $s['period_id'] == $period['id']);
                                        $daySchedule = reset($daySchedule);
                                    ?>
                                    <td style="height: 80px; vertical-align: middle;">
                                        <?php if ($daySchedule): ?>
                                        <small>
                                            <strong><?= htmlspecialchars($daySchedule['subject_name']) ?></strong><br>
                                            <?= htmlspecialchars(substr($daySchedule['teacher_name'], 0, 10)) ?>
                                            <?php if ($daySchedule['room_number']): ?>
                                            <br><span class="badge bg-info"><?= htmlspecialchars($daySchedule['room_number']) ?></span>
                                            <?php endif; ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No schedules created for this class yet.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Please select a class from the left to view or manage its schedule.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
