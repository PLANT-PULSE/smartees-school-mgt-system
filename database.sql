

CREATE DATABASE IF NOT EXISTS `school_mvp` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `school_mvp`;
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  name VARCHAR(128) NOT NULL,
  teacher_id INT DEFAULT NULL,
  student_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (role),
  INDEX (teacher_id),
  INDEX (student_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  email VARCHAR(128) DEFAULT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  teacher_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  age INT DEFAULT NULL,
  contact VARCHAR(128) DEFAULT NULL,
  class_id INT DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  `date` DATE NOT NULL,
  status ENUM('present','absent') NOT NULL DEFAULT 'present',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_student_date (student_id, class_id, `date`),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  subject_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_class_subject (class_id, subject_id),
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  subject_id INT NOT NULL,
  sba_score DECIMAL(5,2) DEFAULT NULL,
  exam_score DECIMAL(5,2) DEFAULT NULL,
  grade VARCHAR(32) DEFAULT NULL,
  remark VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_student_subject (student_id, class_id, subject_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  fee_name VARCHAR(128) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description TEXT DEFAULT NULL,
  due_date DATE DEFAULT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  INDEX (class_id, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_fee_id INT NOT NULL,
  amount_paid DECIMAL(10,2) DEFAULT 0,
  payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  payment_method VARCHAR(64) DEFAULT NULL,
  receipt_number VARCHAR(128) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_fee_id) REFERENCES class_fees(id) ON DELETE CASCADE,
  UNIQUE KEY u_student_fee (student_id, class_fee_id)
) ENGINE=InnoDB;

-- Seed data

INSERT IGNORE INTO teachers (id, name, email, phone) VALUES
  (1, 'Demo Teacher', 'teacher@example.com', '555-0100');

INSERT IGNORE INTO classes (id, name, teacher_id) VALUES
  (1, 'Grade 1', 1),
  (2, 'Grade 2', 1);

INSERT IGNORE INTO students (id, name, age, contact, class_id) VALUES
  (1, 'Alice Johnson', 10, 'alice@example.com', 1),
  (2, 'Bob Martinez', 11, 'bob@example.com', 1),
  (3, 'Cathy Lee', 12, 'cathy@example.com', 2);

INSERT IGNORE INTO subjects (id, name) VALUES
  (1, 'Mathematics'),
  (2, 'Science'),
  (3, 'English');

INSERT IGNORE INTO class_fees (id, class_id, fee_name, amount, description, due_date) VALUES
  (1, 1, 'Tuition Fee', 500.00, 'Monthly tuition fee for Grade 1', '2026-04-30'),
  (2, 1, 'Books & Materials', 150.00, 'Textbooks and learning materials', '2026-04-15'),
  (3, 1, 'Transportation', 100.00, 'School bus transportation fee', '2026-04-30'),
  (4, 2, 'Tuition Fee', 550.00, 'Monthly tuition fee for Grade 2', '2026-04-30'),
  (5, 2, 'Books & Materials', 180.00, 'Textbooks and learning materials', '2026-04-15'),
  (6, 2, 'Transportation', 100.00, 'School bus transportation fee', '2026-04-30');

INSERT IGNORE INTO student_fees (student_id, class_fee_id, amount_paid, payment_date) VALUES
  (1, 1, 500.00, '2026-04-01'),
  (1, 2, 150.00, '2026-04-01'),
  (1, 3, 100.00, '2026-04-01'),
  (2, 1, 250.00, '2026-04-15'),
  (2, 2, 75.00, '2026-04-15'),
  (3, 4, 550.00, '2026-04-01'),
  (3, 5, 180.00, '2026-04-01'),
  (3, 6, 50.00, '2026-04-15');

-- Default users
INSERT IGNORE INTO users (id, username, password_hash, role, name, teacher_id, student_id) VALUES
  (1, 'admin', '$2y$10$uTTP2oS4GM4FxMtn8qW0cugMLIvPxtN81kGWFxwEEazQz6jC6Mfk6', 'admin', 'Administrator', NULL, NULL),
  (2, 'teacher', '$2y$10$AyctmFXwLDx9kJ/WZ11C/.cAIGI.emMyUZe1mP/rlRtVuoNJwYjk.', 'teacher', 'Demo Teacher', 1, NULL),
  (3, 'student', '$2y$10$4n0l8ZbeaWO8Qu9Qtj6sEur8D5bvcv2T1idvWBiTWLgrRUz8DsL2C', 'student', 'Alice Johnson', NULL, 1);

-- Add photo column to students table if it doesn't exist (for existing installations)
ALTER TABLE students ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL;

-- =================================================================
-- PARENT/GUARDIAN MANAGEMENT TABLES
-- =================================================================

CREATE TABLE IF NOT EXISTS parents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(128) NOT NULL,
  last_name VARCHAR(128) NOT NULL,
  relationship VARCHAR(64) DEFAULT NULL,
  email VARCHAR(128) DEFAULT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  city VARCHAR(128) DEFAULT NULL,
  state VARCHAR(128) DEFAULT NULL,
  zip_code VARCHAR(16) DEFAULT NULL,
  occupation VARCHAR(128) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (email),
  INDEX (phone)
) ENGINE=InnoDB;

-- Link students to parents/guardians (many-to-many)
CREATE TABLE IF NOT EXISTS student_parents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  parent_id INT NOT NULL,
  relationship VARCHAR(64) DEFAULT NULL,
  is_primary_contact BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_student_parent (student_id, parent_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Enhanced student contact information
CREATE TABLE IF NOT EXISTS student_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  emergency_contact_name VARCHAR(128) DEFAULT NULL,
  emergency_contact_phone VARCHAR(64) DEFAULT NULL,
  emergency_contact_relation VARCHAR(64) DEFAULT NULL,
  medical_condition TEXT DEFAULT NULL,
  allergies TEXT DEFAULT NULL,
  blood_group VARCHAR(16) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY u_student_id (student_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =================================================================
-- TEACHER SCHEDULING TABLES
-- =================================================================

CREATE TABLE IF NOT EXISTS schedule_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_number INT NOT NULL,
  period_name VARCHAR(64) DEFAULT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  duration_minutes INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_period_time (start_time, end_time)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS classroom_rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_number VARCHAR(64) NOT NULL UNIQUE,
  room_name VARCHAR(128) DEFAULT NULL,
  capacity INT DEFAULT NULL,
  floor_number INT DEFAULT NULL,
  building VARCHAR(128) DEFAULT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (building, floor_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teacher_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  class_id INT NOT NULL,
  subject_id INT NOT NULL,
  day_of_week INT NOT NULL,
  period_id INT NOT NULL,
  room_id INT DEFAULT NULL,
  academic_year VARCHAR(16) DEFAULT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY u_schedule (teacher_id, class_id, subject_id, day_of_week, period_id),
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (period_id) REFERENCES schedule_periods(id) ON DELETE RESTRICT,
  FOREIGN KEY (room_id) REFERENCES classroom_rooms(id) ON DELETE SET NULL,
  INDEX (academic_year)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teacher_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  day_of_week INT NOT NULL,
  available_from TIME DEFAULT NULL,
  available_to TIME DEFAULT NULL,
  is_available BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_teacher_day (teacher_id, day_of_week),
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =================================================================
-- STUDENT ENROLLMENT TRACKING
-- =================================================================

CREATE TABLE IF NOT EXISTS enrollment_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  enrollment_date DATE NOT NULL,
  status ENUM('active','transferred','graduated','dropped') DEFAULT 'active',
  roll_number VARCHAR(64) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  INDEX (status),
  INDEX (enrollment_date)
) ENGINE=InnoDB;

-- =================================================================
-- PAYMENT TRACKING ENHANCEMENTS
-- =================================================================

CREATE TABLE IF NOT EXISTS payment_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_fee_id INT NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_method ENUM('cash','check','transfer','online') DEFAULT 'cash',
  receipt_number VARCHAR(128) UNIQUE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_fee_id) REFERENCES class_fees(id) ON DELETE CASCADE,
  INDEX (payment_date),
  INDEX (payment_method)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fee_due_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_fee_id INT NOT NULL,
  alert_date DATE DEFAULT NULL,
  is_sent BOOLEAN DEFAULT FALSE,
  sent_date TIMESTAMP DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_student_fee_alert (student_id, class_fee_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_fee_id) REFERENCES class_fees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =================================================================
-- UPDATE USERS TABLE FOR PARENT ROLE
-- =================================================================

ALTER TABLE users MODIFY role ENUM('admin','teacher','student','parent') NOT NULL DEFAULT 'student';
ALTER TABLE users ADD COLUMN parent_id INT DEFAULT NULL;
ALTER TABLE users ADD FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE;

-- =================================================================
-- SEED DATA FOR NEW TABLES
-- =================================================================

-- Add schedule periods
INSERT IGNORE INTO schedule_periods (id, period_number, period_name, start_time, end_time) VALUES
  (1, 1, 'Period 1', '08:00:00', '08:45:00'),
  (2, 2, 'Period 2', '08:45:00', '09:30:00'),
  (3, 3, 'Period 3', '09:30:00', '10:15:00'),
  (4, 4, 'Break', '10:15:00', '10:30:00'),
  (5, 5, 'Period 4', '10:30:00', '11:15:00'),
  (6, 6, 'Period 5', '11:15:00', '12:00:00'),
  (7, 7, 'Lunch', '12:00:00', '12:45:00'),
  (8, 8, 'Period 6', '12:45:00', '13:30:00');

-- Add classroom rooms
INSERT IGNORE INTO classroom_rooms (id, room_number, room_name, capacity, floor_number, building) VALUES
  (1, '101', 'Grade 1A', 30, 1, 'Main'),
  (2, '102', 'Grade 1B', 32, 1, 'Main'),
  (3, '201', 'Grade 2A', 30, 2, 'Main'),
  (4, '202', 'Grade 2B', 32, 2, 'Main'),
  (5, 'LAB-1', 'Science Lab', 25, 3, 'Main'),
  (6, 'COMP-1', 'Computer Lab', 20, 3, 'Main');

-- Add sample parents
INSERT IGNORE INTO parents (id, first_name, last_name, relationship, email, phone, occupation) VALUES
  (1, 'John', 'Johnson', 'Father', 'john.johnson@email.com', '555-0101', 'Engineer'),
  (2, 'Sarah', 'Johnson', 'Mother', 'sarah.johnson@email.com', '555-0102', 'Doctor'),
  (3, 'Robert', 'Martinez', 'Father', 'robert.martinez@email.com', '555-0201', 'Manager'),
  (4, 'Maria', 'Martinez', 'Mother', 'maria.martinez@email.com', '555-0202', 'Teacher'),
  (5, 'David', 'Lee', 'Father', 'david.lee@email.com', '555-0301', 'Architect');

-- Link sample students to parents
INSERT IGNORE INTO student_parents (student_id, parent_id, relationship, is_primary_contact) VALUES
  (1, 1, 'Father', FALSE),
  (1, 2, 'Mother', TRUE),
  (2, 3, 'Father', FALSE),
  (2, 4, 'Mother', TRUE),
  (3, 5, 'Father', TRUE);

-- Add student contact information
INSERT IGNORE INTO student_contacts (student_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relation, medical_condition, allergies, blood_group) VALUES
  (1, 'Sarah Johnson', '555-0102', 'Mother', NULL, 'None', 'O+'),
  (2, 'Maria Martinez', '555-0202', 'Mother', NULL, 'Peanut allergy', 'A+'),
  (3, 'David Lee', '555-0301', 'Father', NULL, 'None', 'B+');

-- Add teacher availability
INSERT IGNORE INTO teacher_availability (teacher_id, day_of_week, available_from, available_to, is_available) VALUES
  (1, 1, '08:00:00', '15:00:00', TRUE),
  (1, 2, '08:00:00', '15:00:00', TRUE),
  (1, 3, '08:00:00', '15:00:00', TRUE),
  (1, 4, '08:00:00', '15:00:00', TRUE),
  (1, 5, '08:00:00', '15:00:00', TRUE);

-- Add sample teacher schedules
INSERT IGNORE INTO teacher_schedules (teacher_id, class_id, subject_id, day_of_week, period_id, room_id, academic_year) VALUES
  (1, 1, 1, 1, 1, 1, '2025-2026'),
  (1, 1, 1, 1, 5, 1, '2025-2026'),
  (1, 2, 2, 2, 1, 3, '2025-2026'),
  (1, 2, 2, 2, 5, 3, '2025-2026');

-- Add enrollment records
INSERT IGNORE INTO enrollment_records (student_id, class_id, enrollment_date, status, roll_number) VALUES
  (1, 1, '2026-01-15', 'active', '001'),
  (2, 1, '2026-01-20', 'active', '002'),
  (3, 2, '2026-01-18', 'active', '003');
