<?php
require_once __DIR__ . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(APP_NAME) ?> - <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            margin: 0 !important;
            padding: 0 !important;
        }

        body {
            display: flex !important;
            min-height: 100vh;
            background-color: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            flex-wrap: nowrap !important;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px !important;
            min-width: 280px !important;
            max-width: 280px !important;
            background: linear-gradient(180deg, #1a1a1a 0%, #2d2d2d 100%) !important;
            color: #e0e0e0;
            padding: 30px 0;
            position: fixed !important;
            height: 100vh !important;
            overflow-y: auto;
            left: 0 !important;
            top: 0 !important;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.3);
        }

        .sidebar::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #1a1a1a;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #777;
        }

        /* Brand Section */
        .sidebar-brand {
            padding: 0 20px 30px;
            border-bottom: 1px solid #404040;
            margin-bottom: 20px;
        }

        .sidebar-brand a {
            text-decoration: none !important;
            color: #fff;
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .sidebar-brand i {
            margin-right: 12px;
            color: #e74c3c;
            font-size: 1.8rem;
        }

        .sidebar-brand a:hover {
            color: #e74c3c;
        }

        /* Menu Section */
        .menu-section {
            margin-bottom: 10px;
        }

        .menu-title {
            padding: 10px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
            background: none !important;
            border: none !important;
        }

        .menu-title:hover {
            color: #aaa;
            background: rgba(255,255,255,0.05) !important;
        }

        .menu-title i {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .menu-title.collapsed i {
            transform: rotate(-90deg);
        }

        /* Menu Items */
        .menu-items {
            display: none;
            padding: 8px 0;
        }

        .menu-items.show {
            display: block;
        }

        .menu-item {
            display: flex !important;
            align-items: center;
            padding: 12px 20px;
            color: #b0b0b0;
            text-decoration: none !important;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.95rem;
            background: none !important;
            border: none !important;
        }

        .menu-item:hover {
            color: #fff;
            background: rgba(255,255,255,0.08) !important;
            padding-left: 28px;
        }

        .menu-item.active {
            color: #fff !important;
            background: linear-gradient(90deg, rgba(231,76,60,0.3) 0%, rgba(231,76,60,0.15) 100%) !important;
            border-left: 4px solid #e74c3c !important;
            padding-left: 16px !important;
            font-weight: 600;
            box-shadow: inset 0 0 10px rgba(231,76,60,0.1);
            position: relative;
        }

        .menu-item.active i {
            color: #e74c3c;
            font-weight: 700;
        }

        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #e74c3c;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                width: 0;
            }
            to {
                width: 4px;
            }
        }

        .menu-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 0.95rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px !important;
            flex: 1;
            display: flex !important;
            flex-direction: column;
            width: calc(100% - 280px) !important;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            background: #fff !important;
            padding: 15px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: flex !important;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            flex-wrap: nowrap !important;
        }

        .top-bar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 !important;
        }

        .top-bar-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .user-menu:hover {
            background: #f5f5f5;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            display: block;
            font-weight: 600;
            color: #1a1a1a;
            font-size: 0.95rem;
            margin: 0;
        }

        .user-role {
            display: block;
            font-size: 0.8rem;
            color: #888;
            margin: 0;
        }

        /* Page Content */
        .page-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background-color: #f5f7fa;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 0 !important;
                left: -280px !important;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0 !important;
                width: 280px !important;
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .mobile-toggle {
                display: block !important;
            }

            .page-content {
                padding: 20px;
            }
        }

        .mobile-toggle {
            display: none;
            background: none !important;
            border: none !important;
            color: #1a1a1a;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Dropdown Indicator */
        .dropdown-arrow {
            font-size: 0.75rem;
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .menu-title.collapsed .dropdown-arrow {
            transform: rotate(-90deg);
        }

        /* Override Bootstrap defaults */
        .d-flex {
            display: flex !important;
        }

        .align-items-center {
            align-items: center !important;
        }

        .gap-3 {
            gap: 1rem !important;
        }

        .mb-0 {
            margin-bottom: 0 !important;
        }
    </style>
</head>
<body>
<!-- Sidebar Navigation -->
<div class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <a href="dashboard.php">
            <i class="fas fa-school"></i>
            <span><?= htmlspecialchars(APP_NAME) ?></span>
        </a>
    </div>

    <!-- Menu Sections -->
    <div class="menu-section">
        <div class="menu-title" onclick="toggleMenu(this)">
            <span><i class="fas fa-calendar-check me-2"></i>ATTENDANCE</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="menu-items show">
            <a href="attendance_daily.php" class="menu-item">
                <i class="fas fa-clipboard-list"></i>Daily Attendance
            </a>
            <a href="attendance_monthly.php" class="menu-item">
                <i class="fas fa-chart-line"></i>Monthly Report
            </a>
            <a href="attendance_summary.php" class="menu-item">
                <i class="fas fa-chart-pie"></i>Summary Report
            </a>
            <a href="attendance_semesters.php" class="menu-item">
                <i class="fas fa-history"></i>All Semesters Report
            </a>
            <a href="attendance_sheet.php" class="menu-item">
                <i class="fas fa-sheet-icon"></i>Attendance Sheet
            </a>
        </div>
    </div>

    <div class="menu-section">
        <div class="menu-title" onclick="toggleMenu(this)">
            <span><i class="fas fa-graduation-cap me-2"></i>GRADES</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="menu-items show">
            <a href="grades.php" class="menu-item">
                <i class="fas fa-star"></i>Grade Entry
            </a>
            <a href="grades_report.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>Grade Report
            </a>
            <a href="report_card.php" class="menu-item">
                <i class="fas fa-file-pdf"></i>Report Card
            </a>
        </div>
    </div>

    <div class="menu-section">
        <div class="menu-title" onclick="toggleMenu(this)">
            <span><i class="fas fa-user-graduate me-2"></i>STUDENTS</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="menu-items">
            <a href="students.php" class="menu-item">
                <i class="fas fa-list"></i>All Students
            </a>
            <a href="enrollment.php" class="menu-item">
                <i class="fas fa-user-plus"></i>Enrollment
            </a>
            <a href="#" class="menu-item">
                <i class="fas fa-file-import"></i>Import Students
            </a>
        </div>
    </div>

    <div class="menu-section">
        <div class="menu-title" onclick="toggleMenu(this)">
            <span><i class="fas fa-chalkboard me-2"></i>CLASSES</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="menu-items">
            <a href="classes.php" class="menu-item">
                <i class="fas fa-list"></i>All Classes
            </a>
            <a href="schedule.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i>Schedule
            </a>
        </div>
    </div>

    <div class="menu-section">
        <div class="menu-title" onclick="toggleMenu(this)">
            <span><i class="fas fa-chalkboard-teacher me-2"></i>TEACHERS</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="menu-items">
            <a href="teachers.php" class="menu-item">
                <i class="fas fa-list"></i>All Teachers
            </a>
        </div>
    </div>

    <div class="menu-section">
        <div class="menu-title" onclick="toggleMenu(this)">
            <span><i class="fas fa-dollar-sign me-2"></i>FEES</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="menu-items">
            <a href="fees.php" class="menu-item">
                <i class="fas fa-money-bill"></i>Fee Management
            </a>
            <a href="#" class="menu-item">
                <i class="fas fa-receipt"></i>Payment Records
            </a>
            <a href="financial.php" class="menu-item">
                <i class="fas fa-chart-line"></i>Financial Report
            </a>
        </div>
    </div>

    <div class="menu-section">
        <div class="menu-title" onclick="toggleMenu(this)">
            <span><i class="fas fa-users me-2"></i>USERS</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="menu-items">
            <a href="#" class="menu-item">
                <i class="fas fa-user"></i>All Users
            </a>
            <a href="#" class="menu-item">
                <i class="fas fa-user-plus"></i>Add User
            </a>
            <a href="#" class="menu-item">
                <i class="fas fa-user-lock"></i>User Roles
            </a>
        </div>
    </div>

    <div class="menu-section">
        <div class="menu-title" onclick="toggleMenu(this)">
            <span><i class="fas fa-cog me-2"></i>SETTINGS</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="menu-items">
            <a href="#" class="menu-item">
                <i class="fas fa-sliders-h"></i>General Settings
            </a>
            <a href="#" class="menu-item">
                <i class="fas fa-lock"></i>Security
            </a>
            <a href="#" class="menu-item">
                <i class="fas fa-bell"></i>Notifications
            </a>
        </div>
    </div>

    <!-- Logout -->
    <div class="menu-section" style="margin-top: auto; border-top: 1px solid #404040; padding-top: 20px;">
        <a href="logout.php" class="menu-item" style="color: #e74c3c;">
            <i class="fas fa-sign-out-alt"></i>Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="top-bar-title mb-0"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
        </div>
        
        <?php if ($user): ?>
            <div class="top-bar-user">
                <div class="user-menu">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                        <span class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Page Content -->
    <div class="page-content">
