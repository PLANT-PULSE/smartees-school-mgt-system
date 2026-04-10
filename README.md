# 🎓 School Management System

A complete, production-ready school management system built with PHP and MySQL. Includes student enrollment, teacher scheduling, automated fee tracking, and a secure parent portal.

## ✨ Features at a Glance

| Feature | Description |
|---------|-------------|
| 🎯 **Student Enrollment** | Multi-step form with parent info, medical data, photo uploads |
| 👨‍🏫 **Teacher Scheduling** | Visual timetable, conflict detection, room assignments |
| 💰 **Fee Management** | Real-time tracking, overdue alerts, payment history |
| 👨‍👩‍👧 **Parent Portal** | Secure login, view child's grades/fees/attendance/schedule |
| 📊 **Admin Dashboard** | Statistics, analytics, system-wide management |
| 🔐 **Role-Based Access** | Admin, Teacher, Student, Parent roles |
| 📱 **Responsive Design** | Mobile-friendly Bootstrap 5 UI |
| 🔧 **Service Layer** | Reusable components, clean architecture |

## 🚀 Quick Start (5 Minutes)

### 1. Setup Database
```bash
# Navigate to project
cd c:/xampp/htdocs/school

# Import database (Windows)
mysql -u root < database.sql

# Or via MySQL console
mysql > source database.sql;
```

### 2. Start XAMPP
```bash
# Start Apache and MySQL
# http://localhost/school/
```

### 3. Login
- **Admin:** `admin` / `admin123`
- **Teacher:** `teacher` / `teacher123`  
- **Student:** `student` / `student123`
- **Parent:** `parent` / `parent123`

That's it! ✅

## 📁 Key Files & Pages

### User Logins
- 🔑 `/login.php` - Admin/Teacher/Student
- 🔑 `/parent_login.php` - Parent Portal (NEW)

### Admin/Teacher Pages
- 📊 `/dashboard.php` - Main dashboard
- 👥 `/students.php` - Student management
- 👨‍🎓 `/teachers.php` - Teacher management
- 📚 `/classes.php` - Class management
- 📝 `/grades.php` - Grade entry
- ✓ `/attendance.php` - Attendance tracking
- 📅 `/schedule.php` - Teacher scheduling (NEW)
- 📋 `/enrollment.php` - Student enrollment (NEW)
- 💳 `/financial.php` - Financial dashboard (NEW)

### Parent Portal
- 🏠 `/parent_dashboard.php` - View child's info (NEW)

### Backend
- 🔧 `/inc/services.php` - Service layer (NEW)
- 🔐 `/inc/auth.php` - Authentication
- 🗄️ `/inc/db.php` - Database connection

## 🎯 Main Features Explained

### 1️⃣ Student Enrollment (`enrollment.php`)
Multi-step form collecting:
- ✓ Student profile (name, age, photo, class)
- ✓ Parent/Guardian info (contact, address, occupation)
- ✓ Medical info (blood group, allergies, emergency contact)

```php
// Usage:
$studentService = new StudentService($pdo);
$studentId = $studentService->enrollStudent(
    $studentData,
    $parentData,
    $contactData
);
```

### 2️⃣ Parent Portal (`parent_login.php`, `parent_dashboard.php`)
Parents can:
- ✓ Login with secure credentials
- ✓ View child's profile and photo
- ✓ Track grades and performance
- ✓ Monitor attendance (present/absent)
- ✓ View class schedule with teachers & rooms
- ✓ Check fees, payments, and overdue status
- ✓ See payment history

### 3️⃣ Teacher Scheduling (`schedule.php`)
- ✓ Create class-teacher-subject-period assignments
- ✓ Automatic conflict detection
- ✓ Assign classrooms/rooms
- ✓ Visual timetable view
- ✓ Manage periods and availability

```php
// Usage:
$scheduleService = new ScheduleService($pdo);
if (!$scheduleService->hasConflict($teacherId, $dayOfWeek, $periodId, $classId)) {
    $scheduleService->createSchedule($scheduleData);
}
```

### 4️⃣ Fee Tracking (`financial.php`)
- ✓ Per-student fee overview
- ✓ Real-time balance calculation
- ✓ Overdue detection and alerts
- ✓ Payment history with methods
- ✓ Financial statistics per class
- ✓ Receipt number tracking

```php
// Usage:
$feeService = new FeeService($pdo);
$overdue = $feeService->getOverdueFees($studentId);
$feeService->recordPayment($studentId, $classFeeId, $amount, 'transfer', $receipt);
```

## 🗄️ Database Schema (NEW TABLES)

### Parents & Guardian Management
```sql
parents                 -- Guardian information
student_parents         -- Many-to-many relationship
student_contacts        -- Emergency & medical info
```

### Scheduling System
```sql
schedule_periods        -- Class periods (08:00-08:45)
classroom_rooms         -- Physical rooms (101, 102, Lab)
teacher_schedules       -- Teacher assignments
teacher_availability    -- Availability by day
```

### Enrollment & Finance
```sql
enrollment_records      -- Student enrollment history
payment_records         -- Payment transactions
fee_due_alerts          -- Overdue notifications
```

## 🔐 Security Features

✅ Password hashing with `password_hash()`  
✅ SQL prepared statements (no injection)  
✅ Session-based authentication  
✅ Role-based access control (RBAC)  
✅ File upload validation  
✅ CSRF protection ready  

## 📊 Service Layer Classes

All business logic is in reusable services:

```php
// Student Operations
$studentService = new StudentService($pdo);
$student = $studentService->getStudentDetails($id);
$service->enrollStudent($data, $parents, $contacts);

// Parent Operations  
$parentService = new ParentService($pdo);
$parent = $parentService->getParentWithStudents($id);

// Scheduling
$scheduleService = new ScheduleService($pdo);
$schedule = $scheduleService->getClassSchedule($classId);

// Fees
$feeService = new FeeService($pdo);
$fees = $feeService->getStudentFeesOverview($studentId);

// Grades
$gradeService = new GradeService($pdo);
$grades = $gradeService->getStudentGrades($studentId);

// Attendance
$attendanceService = new AttendanceService($pdo);
$attendance = $attendanceService->getStudentAttendance($studentId);
```

## 📝 Configuration

Edit `inc/config.php`:
```php
define('APP_NAME', 'School MVP');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'school_mvp');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## 🎨 UI Highlights

- 📱 **Bootstrap 5** responsive grid
- 🎯 **Font Awesome 6** icons
- 📊 **Stats cards** with color-coded metrics
- 📈 **Progress bars** for fee tracking
- 📋 **Tabs** for multi-step forms
- 🎨 **Gradient headers** (purple/blue)
- ⚡ **Real-time feedback** with toast alerts
- ✓ **Confirmation dialogs** for actions

## 📚 Documentation Files

- **DOCUMENTATION.md** - Complete system documentation
- **API_REFERENCE.md** - Service layer API details
- **README.md** - This file

## 🆘 Common Tasks

### Create Parent Account
```php
$parentService = new ParentService($pdo);
$parentId = $parentService->createParentWithAccount(
    ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@email.com'],
    'johndoe',      // username
    'password123'   // password
);
```

### Enroll Student
1. Go to `/enrollment.php`
2. Fill 3-step form (Student → Parent → Medical)
3. Click "Complete Enrollment"
4. System creates student + parent records

### Create Class Schedule
1. Go to `/schedule.php`
2. Select class
3. Fill: Teacher, Subject, Day, Period, Room
4. Click "Add Schedule"
5. View in timetable

### Record Payment
1. Go to `/financial.php`
2. Select class → Student
3. Shows fee breakdown
4. Admin records payment → Updates balance

## ❓ Troubleshooting

| Issue | Solution |
|-------|----------|
| Database not found | Run `mysql < database.sql` |
| Can't login | Check user in `users` table |
| Photos not uploading | Check `/uploads/students/` is writable |
| Schedule conflicts | Service auto-detects - try different time |
| Fees not showing | Verify class_fees and student_fees records |

## 🚀 Next Steps

1. **Customize branding** - Update logo, colors in `assets/css/style.css`
2. **Add more users** - Admin panel for user creation
3. **Email notifications** - Send fee reminders to parents
4. **Reports** - Generate PDF reports
5. **Mobile app** - Use same API for mobile clients

## 📞 Support Resources

- Check `DOCUMENTATION.md` for detailed guides
- Review `API_REFERENCE.md` for service layer
- See `/inc/services.php` for service implementations
- Database schema in `/database.sql`

## 🎓 Educational Value

This system demonstrates:
- ✅ Multi-tier architecture (service layer)
- ✅ Object-oriented PHP
- ✅ Database design with relationships
- ✅ Security best practices
- ✅ RESTful-ready service layer
- ✅ Responsive web design
- ✅ Transaction management
- ✅ Error handling

Perfect for learning or extending into production!

---

**Version:** 1.0  
**Last Updated:** April 5, 2026  
**License:** Educational Use

Good luck! 🎉
