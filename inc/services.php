<?php
/**
 * School Management System - Service Layer
 * Provides comprehensive business logic for all modules
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// =================================================================
// BASE SERVICE CLASS
// =================================================================

abstract class BaseService
{
    protected PDO $pdo;
    protected string $table;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?? getDb();
    }

    /**
     * Get all records from table with optional filters
     */
    public function getAll(array $filters = [], string $orderBy = 'id', string $order = 'ASC'): array
    {
        $where = $this->buildWhereClause($filters);
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($where['sql'])) {
            $sql .= " WHERE " . $where['sql'];
        }
        
        $sql .= " ORDER BY {$orderBy} {$order}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($where['params']);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single record by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Build WHERE clause from filters
     */
    protected function buildWhereClause(array $filters): array
    {
        $where = ['sql' => '', 'params' => []];
        
        if (empty($filters)) {
            return $where;
        }
        
        $conditions = [];
        foreach ($filters as $key => $value) {
            // Handle array values for IN clause
            if (is_array($value)) {
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $conditions[] = "{$key} IN ({$placeholders})";
                $where['params'] = array_merge($where['params'], $value);
            } else {
                $conditions[] = "{$key} = ?";
                $where['params'][] = $value;
            }
        }
        
        $where['sql'] = implode(' AND ', $conditions);
        return $where;
    }

    /**
     * Insert new record
     */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update record by ID
     */
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        
        $sets = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE {$this->table} SET " . implode(',', $sets) . " WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete record by ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Execute custom query
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

// =================================================================
// STUDENT SERVICE
// =================================================================

class StudentService extends BaseService
{
    protected string $table = 'students';

    /**
     * Get student with full details including parents, contacts, and fees
     */
    public function getStudentDetails(int $studentId): ?array
    {
        $student = $this->getById($studentId);
        if (!$student) {
            return null;
        }

        // Get parents
        $stmt = $this->query(
            "SELECT p.*, sp.relationship FROM parents p 
             JOIN student_parents sp ON p.id = sp.parent_id 
             WHERE sp.student_id = ?",
            [$studentId]
        );
        $student['parents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get contact information
        $stmt = $this->query("SELECT * FROM student_contacts WHERE student_id = ?", [$studentId]);
        $student['contacts'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get enrollment information
        $stmt = $this->query(
            "SELECT * FROM enrollment_records WHERE student_id = ? AND status = 'active' LIMIT 1",
            [$studentId]
        );
        $student['enrollment'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get class information
        if ($student['class_id']) {
            $stmt = $this->query("SELECT * FROM classes WHERE id = ?", [$student['class_id']]);
            $student['class'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $student;
    }

    /**
     * Enroll student with parent information
     */
    public function enrollStudent(array $studentData, array $parentData = [], array $contactData = []): int
    {
        try {
            $this->pdo->beginTransaction();

            // Create/Update student
            $studentId = isset($studentData['id']) ? $studentData['id'] : $this->create($studentData);

            // Add/Update parent information
            if (!empty($parentData)) {
                foreach ($parentData as $parent) {
                    $this->linkParentToStudent($studentId, $parent);
                }
            }

            // Add/Update contact information
            if (!empty($contactData)) {
                $this->updateStudentContact($studentId, $contactData);
            }

            // Create enrollment record
            $enrollmentData = [
                'student_id' => $studentId,
                'class_id' => $studentData['class_id'],
                'enrollment_date' => date('Y-m-d'),
                'status' => 'active',
                'roll_number' => null
            ];

            $stmt = $this->query(
                "INSERT INTO enrollment_records (student_id, class_id, enrollment_date, status) 
                 VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = 'active'",
                [$studentId, $studentData['class_id'], date('Y-m-d'), 'active']
            );

            $this->pdo->commit();
            return $studentId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Link parent to student
     */
    public function linkParentToStudent(int $studentId, array $parentData): int
    {
        // Create/Find parent
        if (isset($parentData['id'])) {
            $parentId = $parentData['id'];
            $this->query(
                "UPDATE parents SET first_name = ?, last_name = ?, relationship = ?, email = ?, phone = ? WHERE id = ?",
                [$parentData['first_name'], $parentData['last_name'], $parentData['relationship'] ?? null, 
                 $parentData['email'] ?? null, $parentData['phone'] ?? null, $parentId]
            );
        } else {
            $parentId = $this->createParent($parentData);
        }

        // Link to student
        $stmt = $this->query(
            "INSERT IGNORE INTO student_parents (student_id, parent_id, relationship, is_primary_contact) 
             VALUES (?, ?, ?, ?)",
            [$studentId, $parentId, $parentData['relationship'] ?? null, $parentData['is_primary_contact'] ?? false]
        );

        return $parentId;
    }

    /**
     * Create parent record
     */
    public function createParent(array $parentData): int
    {
        $stmt = $this->query(
            "INSERT INTO parents (first_name, last_name, relationship, email, phone, address, city, state, zip_code, occupation) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$parentData['first_name'], $parentData['last_name'], $parentData['relationship'] ?? null,
             $parentData['email'] ?? null, $parentData['phone'] ?? null, $parentData['address'] ?? null,
             $parentData['city'] ?? null, $parentData['state'] ?? null, $parentData['zip_code'] ?? null,
             $parentData['occupation'] ?? null]
        );

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update student contact information
     */
    public function updateStudentContact(int $studentId, array $contactData): void
    {
        $stmt = $this->query(
            "INSERT INTO student_contacts 
             (student_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relation, 
              medical_condition, allergies, blood_group) 
             VALUES (?, ?, ?, ?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE 
             emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relation = ?,
             medical_condition = ?, allergies = ?, blood_group = ?",
            [$studentId, $contactData['emergency_contact_name'] ?? null, $contactData['emergency_contact_phone'] ?? null,
             $contactData['emergency_contact_relation'] ?? null, $contactData['medical_condition'] ?? null,
             $contactData['allergies'] ?? null, $contactData['blood_group'] ?? null,
             $contactData['emergency_contact_name'] ?? null, $contactData['emergency_contact_phone'] ?? null,
             $contactData['emergency_contact_relation'] ?? null, $contactData['medical_condition'] ?? null,
             $contactData['allergies'] ?? null, $contactData['blood_group'] ?? null]
        );
    }

    /**
     * Get students by class
     */
    public function getStudentsByClass(int $classId): array
    {
        return $this->getAll(['class_id' => $classId], 'name');
    }

    /**
     * Get student with grades
     */
    public function getStudentWithGrades(int $studentId): ?array
    {
        $student = $this->getStudentDetails($studentId);
        if (!$student) {
            return null;
        }

        $stmt = $this->query(
            "SELECT g.*, s.name as subject_name FROM grades g 
             JOIN subjects s ON g.subject_id = s.id 
             WHERE g.student_id = ? 
             ORDER BY s.name",
            [$studentId]
        );
        $student['grades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $student;
    }
}

// =================================================================
// PARENT SERVICE
// =================================================================

class ParentService extends BaseService
{
    protected string $table = 'parents';

    /**
     * Get parent with linked students
     */
    public function getParentWithStudents(int $parentId): ?array
    {
        $parent = $this->getById($parentId);
        if (!$parent) {
            return null;
        }

        $stmt = $this->query(
            "SELECT s.*, sp.relationship FROM students s 
             JOIN student_parents sp ON s.id = sp.student_id 
             WHERE sp.parent_id = ?",
            [$parentId]
        );
        $parent['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $parent;
    }

    /**
     * Get students for parent
     */
    public function getStudentsByParent(int $parentId): array
    {
        $stmt = $this->query(
            "SELECT s.* FROM students s 
             JOIN student_parents sp ON s.id = sp.student_id 
             WHERE sp.parent_id = ?",
            [$parentId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find parent by email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->query("SELECT * FROM parents WHERE email = ? LIMIT 1", [$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create parent with user account
     */
    public function createParentWithAccount(array $parentData, string $username, string $password): int
    {
        try {
            $this->pdo->beginTransaction();

            $parentId = $this->create($parentData);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->query(
                "INSERT INTO users (username, password_hash, role, name, parent_id) 
                 VALUES (?, ?, 'parent', ?, ?)",
                [$username, $hash, $parentData['first_name'] . ' ' . $parentData['last_name'], $parentId]
            );

            $this->pdo->commit();
            return $parentId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

// =================================================================
// TEACHER SERVICE
// =================================================================

class TeacherService extends BaseService
{
    protected string $table = 'teachers';

    /**
     * Get teacher with schedule
     */
    public function getTeacherWithSchedule(int $teacherId): ?array
    {
        $teacher = $this->getById($teacherId);
        if (!$teacher) {
            return null;
        }

        $stmt = $this->query(
            "SELECT ts.*, c.name as class_name, s.name as subject_name, p.period_name, r.room_number 
             FROM teacher_schedules ts 
             JOIN classes c ON ts.class_id = c.id 
             JOIN subjects s ON ts.subject_id = s.id 
             JOIN schedule_periods p ON ts.period_id = p.id 
             LEFT JOIN classroom_rooms r ON ts.room_id = r.id 
             WHERE ts.teacher_id = ? AND ts.is_active = TRUE 
             ORDER BY ts.day_of_week, p.start_time",
            [$teacherId]
        );
        $teacher['schedule'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $teacher;
    }

    /**
     * Get teacher's classes
     */
    public function getTeacherClasses(int $teacherId): array
    {
        $stmt = $this->query(
            "SELECT DISTINCT c.* FROM classes c 
             WHERE c.teacher_id = ?",
            [$teacherId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =================================================================
// SCHEDULE SERVICE
// =================================================================

class ScheduleService extends BaseService
{
    protected string $table = 'teacher_schedules';

    /**
     * Create schedule
     */
    public function createSchedule(array $scheduleData): int
    {
        return $this->create($scheduleData);
    }

    /**
     * Get schedule by class
     */
    public function getClassSchedule(int $classId, int $academicYear = null): array
    {
        $sql = "SELECT ts.*, t.name as teacher_name, s.name as subject_name, p.period_name, 
                p.start_time, p.end_time, r.room_number 
                FROM teacher_schedules ts 
                JOIN teachers t ON ts.teacher_id = t.id 
                JOIN subjects s ON ts.subject_id = s.id 
                JOIN schedule_periods p ON ts.period_id = p.id 
                LEFT JOIN classroom_rooms r ON ts.room_id = r.id 
                WHERE ts.class_id = ? AND ts.is_active = TRUE";

        $params = [$classId];

        if ($academicYear !== null) {
            $sql .= " AND ts.academic_year = ?";
            $params[] = $academicYear;
        }

        $sql .= " ORDER BY ts.day_of_week, p.start_time";

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get schedule by day of week
     */
    public function getScheduleByDay(int $classId, int $dayOfWeek): array
    {
        $stmt = $this->query(
            "SELECT ts.*, t.name as teacher_name, s.name as subject_name, p.period_name, 
                    p.start_time, p.end_time, r.room_number 
             FROM teacher_schedules ts 
             JOIN teachers t ON ts.teacher_id = t.id 
             JOIN subjects s ON ts.subject_id = s.id 
             JOIN schedule_periods p ON ts.period_id = p.id 
             LEFT JOIN classroom_rooms r ON ts.room_id = r.id 
             WHERE ts.class_id = ? AND ts.day_of_week = ? AND ts.is_active = TRUE 
             ORDER BY p.start_time",
            [$classId, $dayOfWeek]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check for schedule conflicts
     */
    public function hasConflict(int $teacherId, int $dayOfWeek, int $periodId, int $classId, int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM teacher_schedules 
                WHERE teacher_id = ? AND day_of_week = ? AND period_id = ? AND is_active = TRUE";
        $params = [$teacherId, $dayOfWeek, $periodId];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get periods
     */
    public function getPeriods(): array
    {
        return $this->query(
            "SELECT * FROM schedule_periods ORDER BY period_number"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get rooms
     */
    public function getRooms(): array
    {
        return $this->query(
            "SELECT * FROM classroom_rooms WHERE is_active = TRUE ORDER BY room_number"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =================================================================
// FEE SERVICE
// =================================================================

class FeeService extends BaseService
{
    protected string $table = 'class_fees';

    /**
     * Get student's fees overview
     */
    public function getStudentFeesOverview(int $studentId): array
    {
        $stmt = $this->query(
            "SELECT cf.*, 
                    COALESCE(SUM(pr.amount_paid), 0) as total_paid,
                    cf.amount - COALESCE(SUM(pr.amount_paid), 0) as balance,
                    CASE WHEN cf.due_date < NOW() AND cf.amount - COALESCE(SUM(pr.amount_paid), 0) > 0 THEN TRUE ELSE FALSE END as is_overdue
             FROM class_fees cf 
             JOIN students s ON cf.class_id = s.class_id 
             LEFT JOIN payment_records pr ON cf.id = pr.class_fee_id AND pr.student_id = ?
             WHERE s.id = ? AND cf.is_active = TRUE 
             GROUP BY cf.id 
             ORDER BY cf.due_date",
            [$studentId, $studentId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue fees for student
     */
    public function getOverdueFees(int $studentId): array
    {
        $stmt = $this->query(
            "SELECT cf.*, 
                    COALESCE(SUM(pr.amount_paid), 0) as total_paid,
                    cf.amount - COALESCE(SUM(pr.amount_paid), 0) as balance
             FROM class_fees cf 
             JOIN students s ON cf.class_id = s.class_id 
             LEFT JOIN payment_records pr ON cf.id = pr.class_fee_id AND pr.student_id = ?
             WHERE s.id = ? AND cf.is_active = TRUE 
             AND cf.due_date < NOW() 
             AND cf.amount > COALESCE(SUM(pr.amount_paid), 0)
             GROUP BY cf.id",
            [$studentId, $studentId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record payment
     */
    public function recordPayment(int $studentId, int $classFeeId, float $amount, string $paymentMethod = 'cash', string $receiptNumber = null): int
    {
        $paymentId = $this->query(
            "INSERT INTO payment_records (student_id, class_fee_id, amount_paid, payment_date, payment_method, receipt_number) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$studentId, $classFeeId, $amount, date('Y-m-d'), $paymentMethod, $receiptNumber]
        )->rowCount() > 0 ? (int) $this->pdo->lastInsertId() : 0;

        return $paymentId;
    }

    /**
     * Get payment history for student
     */
    public function getPaymentHistory(int $studentId): array
    {
        $stmt = $this->query(
            "SELECT pr.*, cf.fee_name, cf.amount 
             FROM payment_records pr 
             JOIN class_fees cf ON pr.class_fee_id = cf.id 
             WHERE pr.student_id = ? 
             ORDER BY pr.payment_date DESC",
            [$studentId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get class fees
     */
    public function getClassFees(int $classId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM class_fees WHERE class_id = ?";
        $params = [$classId];

        if ($activeOnly) {
            $sql .= " AND is_active = TRUE";
        }

        $sql .= " ORDER BY due_date";

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =================================================================
// GRADE SERVICE
// =================================================================

class GradeService extends BaseService
{
    protected string $table = 'grades';

    /**
     * Get student's grades
     */
    public function getStudentGrades(int $studentId): array
    {
        $stmt = $this->query(
            "SELECT g.*, s.name as subject_name 
             FROM grades g 
             JOIN subjects s ON g.subject_id = s.id 
             WHERE g.student_id = ? 
             ORDER BY s.name",
            [$studentId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get class grades
     */
    public function getClassGrades(int $classId, int $subjectId = null): array
    {
        $sql = "SELECT g.*, s.name as subject_name, st.name as student_name 
                FROM grades g 
                JOIN subjects s ON g.subject_id = s.id 
                JOIN students st ON g.student_id = st.id 
                WHERE g.class_id = ?";

        $params = [$classId];

        if ($subjectId !== null) {
            $sql .= " AND g.subject_id = ?";
            $params[] = $subjectId;
        }

        $sql .= " ORDER BY st.name, s.name";

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record grade
     */
    public function recordGrade(int $studentId, int $classId, int $subjectId, string $grade, string $remark = null): bool
    {
        $stmt = $this->query(
            "INSERT INTO grades (student_id, class_id, subject_id, grade, remark) 
             VALUES (?, ?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE grade = ?, remark = ?",
            [$studentId, $classId, $subjectId, $grade, $remark, $grade, $remark]
        );

        return true;
    }
}

// =================================================================
// ATTENDANCE SERVICE
// =================================================================

class AttendanceService extends BaseService
{
    protected string $table = 'attendance';

    /**
     * Record attendance
     */
    public function recordAttendance(int $studentId, int $classId, string $status = 'present', string $date = null): bool
    {
        $date = $date ?? date('Y-m-d');

        $stmt = $this->query(
            "INSERT INTO attendance (student_id, class_id, date, status) 
             VALUES (?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE status = ?",
            [$studentId, $classId, $date, $status, $status]
        );

        return true;
    }

    /**
     * Get student attendance
     */
    public function getStudentAttendance(int $studentId, string $fromDate = null, string $toDate = null): array
    {
        $sql = "SELECT * FROM attendance WHERE student_id = ?";
        $params = [$studentId];

        if ($fromDate) {
            $sql .= " AND date >= ?";
            $params[] = $fromDate;
        }

        if ($toDate) {
            $sql .= " AND date <= ?";
            $params[] = $toDate;
        }

        $sql .= " ORDER BY date DESC";

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance statistics
     */
    public function getAttendanceStats(int $studentId, int $months = 1): array
    {
        $stmt = $this->query(
            "SELECT status, COUNT(*) as count FROM attendance 
             WHERE student_id = ? AND date >= DATE_SUB(NOW(), INTERVAL ? MONTH) 
             GROUP BY status",
            [$studentId, $months]
        );

        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = ['present' => 0, 'absent' => 0];

        foreach ($stats as $stat) {
            $result[$stat['status']] = $stat['count'];
        }

        return $result;
    }

    /**
     * Get class attendance
     */
    public function getClassAttendance(int $classId, string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        $stmt = $this->query(
            "SELECT a.*, s.name as student_name 
             FROM attendance a 
             JOIN students s ON a.student_id = s.id 
             WHERE a.class_id = ? AND a.date = ? 
             ORDER BY s.name",
            [$classId, $date]
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
