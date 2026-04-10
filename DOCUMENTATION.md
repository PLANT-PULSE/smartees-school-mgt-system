# School Management System - Complete Documentation

## 📋 System Overview

This is a comprehensive school management system built with PHP and MySQL, designed to handle student enrollment, teacher scheduling, fee tracking, and parent communication.

## 🎯 Key Features

### 1. **Student Enrollment & Profile Management**
- Multi-step enrollment form with parent information collection
- Student photo upload with validation
- Medical information and emergency contact tracking
- Complete parent-student relationship management
- Enrollment status tracking

### 2. **Parent Portal with Secure Login**
- Dedicated parent login page (`parent_login.php`)
- Secure parent dashboard (`parent_dashboard.php`)
- View multiple child records
- Access to grades, attendance, and fees
- View class schedules and teacher information

### 3. **Teacher Scheduling System**
- Complete timetable management (`schedule.php`)
- Schedule conflict detection
- Classroom room assignments
- Period-based scheduling (customizable)
- Teacher availability tracking
- Visual timetable display

### 4. **Advanced Fee Tracking**
- Financial dashboard with real-time statistics (`financial.php`)
- Per-student fee tracking with balance calculation
- Overdue fee alerts and tracking
- Payment history record
- Payment method tracking (cash, check, transfer, online)
- Receipt number generation

### 5. **Comprehensive Service Layer**
- Reusable service classes for all modules
- Centralized business logic
- Transaction support for complex operations
- Error handling and validation

## 📁 Project Structure

```
school/
├── index.php                 # Main dashboard redirect
├── login.php                 # Admin/Teacher/Student login
├── parent_login.php          # Parent portal login
├── logout.php                # Logout handler
├── dashboard.php             # Admin dashboard
├── parent_dashboard.php       # Parent portal dashboard
├── students.php              # Student management
├── teachers.php              # Teacher management
├── classes.php               # Class management
├── subjects.php              # Subject management
├── grades.php                # Grade management
├── attendance.php            # Attendance tracking
├── schedule.php              # Teacher scheduling
├── enrollment.php            # Student enrollment (NEW)
├── financial.php             # Financial dashboard (NEW)
├── database.sql              # Database schema
├── inc/
│   ├── auth.php              # Authentication functions
│   ├── config.php            # Configuration
│   ├── db.php                # Database connection
│   ├── functions.php         # Helper functions
│   ├── services.php          # Service layer (NEW)
│   ├── header.php            # Header template
│   └── footer.php            # Footer template
├── assets/
│   ├── css/
│   │   └── style.css         # Main stylesheet
│   └── js/
│       └── script.js         # JavaScript functions
└── uploads/
    └── students/             # Student photo directory
```

## 🗄️ Database Schema

### Core Tables
- **users** - User accounts (admin, teacher, student, parent)
- **teachers** - Teacher information
- **students** - Student information
- **classes** - Class/Grade information
- **subjects** - Subject information

### NEW Tables (Enhanced Features)

#### Parent Management
- **parents** - Parent/Guardian information
- **student_parents** - Many-to-many relationship between students and parents
- **student_contacts** - Emergency contact and medical information

#### Scheduling
- **schedule_periods** - Class periods (time slots)
- **classroom_rooms** - Physical classroom information
- **teacher_schedules** - Teacher-class-subject-period assignments
- **teacher_availability** - Teacher availability by day

#### Enrollment & Tracking
- **enrollment_records** - Student enrollment history and status
- **payment_records** - Payment transaction records
- **fee_due_alerts** - Fee overdue notification tracking

## 🔐 User Roles & Access Control

### 1. Admin
- Full system access
- Dashboard with statistics
- User management
- All CRUD operations

### 2. Teacher
- View assigned classes
- Enter grades and attendance
- View class schedule
- Access student records

### 3. Student (Limited)
- View own profile
- View grades
- View attendance
- View assigned classes

### 4. Parent (NEW)
- View child's profile and photo
- Track grades and performance
- Monitor attendance
- View fee information and payment history
- Access class schedule
- View emergency contact information

## 🚀 Getting Started

### 1. Installation

```bash
# Clone or download project
cd /c/xampp/htdocs/school

# Import database
mysql -u root < database.sql

# Verify database
mysql -u root
> USE school_mvp;
> SHOW TABLES;
```

### 2. Configuration

Edit `inc/config.php`:
```php
define('APP_NAME', 'School MVP');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'school_mvp');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Default Login Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123`

**Teacher Account:**
- Username: `teacher`
- Password: `teacher123`

**Student Account:**
- Username: `student`
- Password: `student123`

**Parent Account:**
- Username: `parent`
- Password: `parent123`

## 🔧 Service Layer (NEW)

The system includes a comprehensive service layer for business logic:

### BaseService
Abstract base class with common CRUD operations:
```php
$service = new StudentService($pdo);
$student = $service->getById($id);
$students = $service->getAll(['class_id' => 1]);
$newId = $service->create($data);
$service->update($id, $data);
$service->delete($id);
```

### StudentService
```php
$service = new StudentService($pdo);

// Get complete student details
$details = $service->getStudentDetails($studentId);

// Enroll new student with parent info
$studentId = $service->enrollStudent($studentData, $parentData, $contactData);

// Get students by class
$students = $service->getStudentsByClass($classId);

// Get student with grades
$student = $service->getStudentWithGrades($studentId);
```

### ParentService
```php
$service = new ParentService($pdo);

// Get parent with linked students
$parent = $service->getParentWithStudents($parentId);

// Get students for parent
$students = $service->getStudentsByParent($parentId);

// Create parent account
$parentId = $service->createParentWithAccount($data, $username, $password);
```

### ScheduleService
```php
$service = new ScheduleService($pdo);

// Get class schedule
$schedule = $service->getClassSchedule($classId);

// Get schedule by day
$daySchedule = $service->getScheduleByDay($classId, 1); // Monday

// Check for conflicts
$hasConflict = $service->hasConflict($teacherId, $dayOfWeek, $periodId, $classId);

// Get available resources
$periods = $service->getPeriods();
$rooms = $service->getRooms();
```

### FeeService
```php
$service = new FeeService($pdo);

// Get student fees overview
$fees = $service->getStudentFeesOverview($studentId);

// Get overdue fees
$overdue = $service->getOverdueFees($studentId);

// Record payment
$paymentId = $service->recordPayment($studentId, $classFeeId, $amount, $method);

// Get payment history
$history = $service->getPaymentHistory($studentId);

// Get class fees
$classFees = $service->getClassFees($classId);
```

### GradeService
```php
$service = new GradeService($pdo);

// Get student grades
$grades = $service->getStudentGrades($studentId);

// Get class grades
$classGrades = $service->getClassGrades($classId, $subjectId);

// Record grade
$service->recordGrade($studentId, $classId, $subjectId, 'A', 'Excellent');
```

### AttendanceService
```php
$service = new AttendanceService($pdo);

// Record attendance
$service->recordAttendance($studentId, $classId, 'present');

// Get student attendance
$attendance = $service->getStudentAttendance($studentId, $fromDate, $toDate);

// Get statistics
$stats = $service->getAttendanceStats($studentId, 3); // 3 months

// Get class attendance
$classAttendance = $service->getClassAttendance($classId, $date);
```

## 📊 Key Pages

### Public
- `/login.php` - Main login
- `/parent_login.php` - Parent portal login

### Admin Dashboard
- `/dashboard.php` - Main dashboard with statistics
- `/students.php` - Student management
- `/teachers.php` - Teacher management
- `/classes.php` - Class management
- `/subjects.php` - Subject management

### Academic
- `/grades.php` - Grade management
- `/attendance.php` - Attendance tracking
- `/schedule.php` - Teacher scheduling (NEW)
- `/enrollment.php` - New student enrollment (NEW)

### Financial
- `/fees.php` - Fee management
- `/financial.php` - Financial dashboard (NEW)

### Parent Portal
- `/parent_dashboard.php` - View child's records (NEW)

## 🎨 UI/UX Features

- Bootstrap 5 responsive design
- Font Awesome 6 icons
- Custom color scheme with primary/success/warning/danger colors
- Mobile-friendly interface
- Tab-based forms for multi-step processes
- Real-time statistics cards
- Visual progress indicators
- Confirmation dialogs for destructive actions
- Toast notifications for feedback

## 🔒 Security Features

- Password hashing with `password_hash()` and `password_verify()`
- Session-based authentication
- SQL prepared statements (prevent SQL injection)
- CSRF token support (ready to implement)
- File upload validation
- Role-based access control (RBAC)

## 📈 Usage Examples

### Enroll a New Student
```php
$studentService = new StudentService($pdo);

$studentData = [
    'name' => 'John Doe',
    'age' => 10,
    'contact' => 'john@example.com',
    'class_id' => 1
];

$parentData = [[
    'first_name' => 'James',
    'last_name' => 'Doe',
    'relationship' => 'Father',
    'email' => 'james@example.com',
    'phone' => '+234-xxx-xxx-xxxx',
    'is_primary_contact' => true
]];

$contactData = [
    'emergency_contact_name' => 'Mary Doe',
    'emergency_contact_phone' => '+234-yyy-yyy-yyyy',
    'blood_group' => 'O+',
    'allergies' => 'None'
];

$studentId = $studentService->enrollStudent($studentData, $parentData, $contactData);
```

### Create a Schedule
```php
$scheduleService = new ScheduleService($pdo);

$scheduleData = [
    'teacher_id' => 1,
    'class_id' => 1,
    'subject_id' => 1,
    'day_of_week' => 1, // Monday
    'period_id' => 1,   // First period
    'room_id' => 1,
    'academic_year' => '2025-2026'
];

$scheduleId = $scheduleService->createSchedule($scheduleData);
```

### Record a Payment
```php
$feeService = new FeeService($pdo);

$paymentId = $feeService->recordPayment(
    $studentId,
    $classFeeId,
    500.00,
    'transfer',
    'RCP-2026-001'
);
```

## 🐛 Troubleshooting

### Database Connection Failed
- Verify MySQL is running
- Check credentials in `inc/config.php`
- Ensure database `school_mvp` exists

### Login Not Working
- Clear browser cookies
- Check if user exists in `users` table
- Verify password hash is correctly generated

### File Upload Issues
- Create `/uploads/students/` directory
- Ensure directory is writable (chmod 755)
- Verify file size limits in PHP

## 📝 Future Enhancements

1. **Email Notifications**
   - Send fee reminders to parents
   - Grade alerts
   - Attendance reports

2. **Advanced Analytics**
   - Student performance trends
   - Financial reports
   - Attendance analytics

3. **Mobile App**
   - React Native or Flutter app
   - Real-time notifications
   - Offline capabilities

4. **API Development**
   - RESTful API for mobile
   - Third-party integrations
   - Data export (PDF, Excel)

5. **Additional Features**
   - Library management
   - Event calendar
   - Notice board
   - Assignment submission

## 📞 Support

For issues or questions, refer to:
- Database schema in `database.sql`
- Service layer documentation in `inc/services.php`
- Individual page headers for specific features

## 📄 License

This school management system is provided as-is for educational purposes.

---

**Last Updated:** April 5, 2026
**Version:** 1.0
