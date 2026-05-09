<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

requireRole('admin');
$pageTitle = 'Fees Section';
$pdo = getDb();

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

$studentFeeStatuses = $pdo->query(
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

include __DIR__ . '/inc/sidebar-header.php';
?>

<div class="table-header">
    <div>
        <h1 class="table-title"><i class="fas fa-money-check-alt me-2 text-success"></i>Fees Section</h1>
        <p class="text-muted mb-0">View fee targets per class and student payment status.</p>
    </div>
</div>

<div class="card p-3 mb-4">
    <h5 class="mb-3">Amount Each Class Is To Pay (Per Student)</h5>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Active Fee Items</th>
                    <th>Amount To Pay</th>
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
                    <tr><td colspan="3" class="text-center text-muted">No class fee setup available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3">
    <h5 class="mb-3">Student Fee Status</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Total Due</th>
                    <th>Total Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studentFeeStatuses as $row): ?>
                    <?php
                    $due = (float) $row['total_due'];
                    $paid = (float) $row['total_paid'];
                    $balance = max(0, $due - $paid);
                    $statusLabel = 'Not paid';
                    $statusClass = 'danger';
                    $statusIcon = '❌';
                    if ($due > 0 && $paid >= $due) {
                        $statusLabel = 'Paid in full';
                        $statusClass = 'success';
                        $statusIcon = '✅';
                    } elseif ($paid > 0) {
                        $statusLabel = 'Partially paid';
                        $statusClass = 'warning';
                        $statusIcon = '⚠️';
                    }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                        <td><?= htmlspecialchars($row['class_name'] ?? 'Unassigned') ?></td>
                        <td>GHc <?= number_format($due, 2) ?></td>
                        <td>GHc <?= number_format($paid, 2) ?></td>
                        <td>GHc <?= number_format($balance, 2) ?></td>
                        <td><span class="badge bg-<?= $statusClass ?>"><?= $statusIcon ?> <?= htmlspecialchars($statusLabel) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($studentFeeStatuses)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No student fee records available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
