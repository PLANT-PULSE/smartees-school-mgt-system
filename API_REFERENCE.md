# School Management System - API Reference

## Service Layer Architecture

All business logic is centralized in the service layer located in `inc/services.php`. This provides a clean, reusable interface for all operations.

## Core Services

### 1. StudentService

**File:** `inc/services.php`  
**Extends:** `BaseService`

#### Methods

##### `getStudentDetails(int $studentId): ?array`
Retrieve complete student information including parents, contacts, grades, and enrollment status.

```php
$service = new StudentService($pdo);
$student = $service->getStudentDetails(1);

// Returns:
// [
//     'id' => 1,
//     'name' => 'Alice Johnson',
//     'photo' => 'uploads/students/...',
//     'parents' => [...],
//     'contacts' => [...],
//     'enrollment' => [...],
//     'class' => [...]
// ]
```

##### `enrollStudent(array $studentData, array $parentData = [], array $contactData = []): int`
Complete enrollment process: student + parent + contact information in a transaction.

```php
$studentData = [
    'name' => 'John Smith',
    'age' => 10,
    'contact' => 'john@school.com',
    'class_id' => 1
];

$parentData = [[
    'first_name' => 'James',
    'last_name' => 'Smith',
    'relationship' => 'Father',
    'email' => 'james@email.com',
    'phone' => '555-1234',
    'is_primary_contact' => true
]];

$contactData = [
    'emergency_contact_name' => 'Mary Smith',
    'emergency_contact_phone' => '555-5678',
    'blood_group' => 'O+',
    'allergies' => 'Peanuts'
];

$studentId = $service->enrollStudent($studentData, $parentData, $contactData);
```

##### `linkParentToStudent(int $studentId, array $parentData): int`
Link or update a parent-student relationship.

```php
$parentId = $service->linkParentToStudent($studentId, [
    'first_name' => 'Sarah',
    'last_name' => 'Smith',
    'relationship' => 'Mother',
    'email' => 'sarah@email.com',
    'phone' => '555-9999',
    'is_primary_contact' => true
]);
```

##### `createParent(array $parentData): int`
Create a new parent record.

```php
$parentId = $service->createParent([
    'first_name' => 'David',
    'last_name' => 'Lee',
    'relationship' => 'Father',
    'email' => 'david@email.com',
    'phone' => '555-0000',
    'occupation' => 'Engineer',
    'address' => '123 Main St',
    'city' => 'Lagos',
    'state' => 'Lagos',
    'zip_code' => '10001'
]);
```

##### `updateStudentContact(int $studentId, array $contactData): void`
Update medical and emergency contact information.

```php
$service->updateStudentContact($studentId, [
    'emergency_contact_name' => 'Michael Gray',
    'emergency_contact_phone' => '555-7777',
    'emergency_contact_relation' => 'Uncle',
    'medical_condition' => 'Asthma',
    'allergies' => 'Dairy',
    'blood_group' => 'A+'
]);
```

##### `getStudentsByClass(int $classId): array`
Get all students in a specific class.

```php
$students = $service->getStudentsByClass(1); // Grade 1
```

##### `getStudentWithGrades(int $studentId): ?array`
Get student with all grades and subject names.

```php
$student = $service->getStudentWithGrades(1);
// Includes 'grades' key with subject-grade mapping
```

---

### 2. ParentService

**File:** `inc/services.php`  
**Extends:** `BaseService`

#### Methods

##### `getParentWithStudents(int $parentId): ?array`
Get parent information with all linked students.

```php
$parent = $service->getParentWithStudents(1);
// Includes 'students' key with array of linked students
```

##### `getStudentsByParent(int $parentId): array`
Get only the students linked to a parent.

```php
$students = $service->getStudentsByParent(1);
```

##### `findByEmail(string $email): ?array`
Find parent by email address.

```php
$parent = $service->findByEmail('parent@email.com');
```

##### `createParentWithAccount(array $parentData, string $username, string $password): int`
Create parent with user account for login.

```php
$parentId = $service->createParentWithAccount([
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@email.com',
    'phone' => '555-1234'
], 'jdoe', 'secure_password_123');
```

---

### 3. ScheduleService

**File:** `inc/services.php`  
**Extends:** `BaseService`

#### Methods

##### `getClassSchedule(int $classId, int $academicYear = null): array`
Get complete schedule for a class.

```php
$schedule = $service->getClassSchedule(1);
// Returns array with teacher, subject, times, rooms
```

##### `getScheduleByDay(int $classId, int $dayOfWeek): array`
Get schedule for specific day (1-7, where 1=Monday).

```php
$mondaySchedule = $service->getScheduleByDay(1, 1); // Monday
```

##### `hasConflict(int $teacherId, int $dayOfWeek, int $periodId, int $classId, int $excludeId = null): bool`
Check if teacher has schedule conflict.

```php
if ($service->hasConflict(1, 1, 2, 1)) {
    echo "Teacher already has class during this period";
}
```

##### `createSchedule(array $scheduleData): int`
Create new schedule entry. Use `hasConflict()` before calling.

```php
$scheduleId = $service->createSchedule([
    'teacher_id' => 1,
    'class_id' => 1,
    'subject_id' => 1,
    'day_of_week' => 1,  // Monday
    'period_id' => 1,
    'room_id' => 1,
    'academic_year' => '2025-2026'
]);
```

##### `getPeriods(): array`
Get all defined periods.

```php
$periods = $service->getPeriods();
// Returns: [['id' => 1, 'period_name' => 'Period 1', 'start_time' => '08:00:00', ...], ...]
```

##### `getRooms(): array`
Get all available rooms.

```php
$rooms = $service->getRooms();
// Returns active classroom rooms
```

---

### 4. FeeService

**File:** `inc/services.php`  
**Extends:** `BaseService`

#### Methods

##### `getStudentFeesOverview(int $studentId): array`
Get all fees with paid/balance information.

```php
$fees = $service->getStudentFeesOverview(1);
// Each fee includes: amount, total_paid, balance, is_overdue
```

##### `getOverdueFees(int $studentId): array`
Get only overdue fees with outstanding balance.

```php
$overdue = $service->getOverdueFees(1);
```

##### `recordPayment(int $studentId, int $classFeeId, float $amount, string $paymentMethod = 'cash', string $receiptNumber = null): int`
Record a payment transaction.

```php
$paymentId = $service->recordPayment(
    $studentId,      // Student ID
    $classFeeId,     // Class Fee ID
    500.00,          // Amount paid
    'transfer',      // Method: cash, check, transfer, online
    'RCP-2026-001'   // Receipt number
);
```

##### `getPaymentHistory(int $studentId): array`
Get payment transaction history.

```php
$history = $service->getPaymentHistory(1);
// Returns array of payment records with fee details
```

##### `getClassFees(int $classId, bool $activeOnly = true): array`
Get all fees defined for a class.

```php
$classFees = $service->getClassFees(1);
```

---

### 5. GradeService

**File:** `inc/services.php`  
**Extends:** `BaseService`

#### Methods

##### `getStudentGrades(int $studentId): array`
Get all grades for a student with subject names.

```php
$grades = $service->getStudentGrades(1);
// Each grade includes: subject_name, grade, remark
```

##### `getClassGrades(int $classId, int $subjectId = null): array`
Get all grades for a class (optionally filtered by subject).

```php
$classGrades = $service->getClassGrades(1);
$mathGrades = $service->getClassGrades(1, 1); // Subject 1 = Math
```

##### `recordGrade(int $studentId, int $classId, int $subjectId, string $grade, string $remark = null): bool`
Insert or update a grade.

```php
$service->recordGrade($studentId, $classId, $subjectId, 'A', 'Excellent performance');
```

---

### 6. AttendanceService

**File:** `inc/services.php`  
**Extends:** `BaseService`

#### Methods

##### `recordAttendance(int $studentId, int $classId, string $status = 'present', string $date = null): bool`
Mark attendance for a student.

```php
$service->recordAttendance(1, 1, 'present', '2026-04-05');
// Status: 'present' or 'absent'
```

##### `getStudentAttendance(int $studentId, string $fromDate = null, string $toDate = null): array`
Get attendance records with optional date range.

```php
$attendance = $service->getStudentAttendance(
    1,
    '2026-03-01',
    '2026-04-05'
);
```

##### `getAttendanceStats(int $studentId, int $months = 1): array`
Get attendance statistics (present/absent count).

```php
$stats = $service->getAttendanceStats(1, 3); // Last 3 months
// Returns: ['present' => 45, 'absent' => 3]
```

##### `getClassAttendance(int $classId, string $date = null): array`
Get attendance for entire class on specific date.

```php
$classAttendance = $service->getClassAttendance(1, '2026-04-05');
```

---

### 7. TeacherService

**File:** `inc/services.php`  
**Extends:** `BaseService`

#### Methods

##### `getTeacherWithSchedule(int $teacherId): ?array`
Get teacher info with complete schedule.

```php
$teacher = $service->getTeacherWithSchedule(1);
// Includes 'schedule' key with all classes-periods
```

##### `getTeacherClasses(int $teacherId): array`
Get classes taught by teacher.

```php
$classes = $service->getTeacherClasses(1);
```

---

## BaseService Methods

All services inherit these methods:

### `getAll(array $filters = [], string $orderBy = 'id', string $order = 'ASC'): array`
Get multiple records with filtering.

```php
$service = new StudentService($pdo);
$students = $service->getAll(
    ['class_id' => 1, 'age' => 10],
    'name',
    'ASC'
);
```

### `getById(int $id): ?array`
Get single record by ID.

```php
$student = $service->getById(1);
```

### `create(array $data): int`
Insert new record.

```php
$id = $service->create([
    'name' => 'John',
    'age' => 10,
    'class_id' => 1
]);
```

### `update(int $id, array $data): bool`
Update existing record.

```php
$service->update(1, ['name' => 'Jane']);
```

### `delete(int $id): bool`
Delete record.

```php
$service->delete(1);
```

---

## Error Handling

All services use exceptions for error handling:

```php
try {
    $studentId = $studentService->enrollStudent($data, $parents, $contacts);
} catch (Exception $e) {
    error_log($e->getMessage());
    // Handle error
}
```

---

## Transaction Management

Complex operations use transactions:

```php
try {
    $pdo->beginTransaction();
    // Multiple operations
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

## Common Patterns

### Bulk Operations

```php
$studentService = new StudentService($pdo);
$students = $studentService->getAll(['class_id' => 1]);

foreach ($students as $student) {
    $gradeService->recordGrade($student['id'], 1, 1, 'A');
}
```

### Fee Reports

```php
$feeService = new FeeService($pdo);
$studentService = new StudentService($pdo);

$students = $studentService->getAll(['class_id' => 1]);
foreach ($students as $student) {
    $fees = $feeService->getStudentFeesOverview($student['id']);
    $overdue = $feeService->getOverdueFees($student['id']);
    
    echo "{$student['name']}: " . count($overdue) . " overdue fees";
}
```

### Schedule Validation

```php
$scheduleService = new ScheduleService($pdo);

if ($scheduleService->hasConflict($teacherId, $dayOfWeek, $periodId, $classId)) {
    throw new Exception("Schedule conflict detected");
}

$scheduleId = $scheduleService->createSchedule($data);
```

---

## Performance Tips

1. **Use getById() for single records** instead of getAll() with filter
2. **Enable query caching** for frequently accessed data
3. **Use prepared statements** (already done in service layer)
4. **Batch operations** when possible
5. **Index frequently filtered columns** (already done in schema)

---

## Database Indexing

All performance-critical columns are indexed:
- `users.role` - for role-based queries
- `attendance.date` - for date range queries
- `fees.due_date` - for overdue detection
- `payments.payment_date` - for payment history
- Foreign keys automatically indexed

---

**Service Layer Version:** 1.0  
**Last Updated:** April 5, 2026
