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
    <link rel="stylesheet" href="/school/assets/css/style.css" />
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-custom animated-navbar">
    <div class="container-fluid">
        <!-- Logo/Brand Section -->
        <a class="navbar-brand navbar-brand-animated" href="dashboard.php">
            <i class="fas fa-school me-2"></i>
            <span class="brand-text"><?= htmlspecialchars(APP_NAME) ?></span>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler navbar-toggler-animated" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon">
                <span class="toggler-line"></span>
                <span class="toggler-line"></span>
                <span class="toggler-line"></span>
            </span>
        </button>

        <!-- Navigation Links -->
        <div class="collapse navbar-collapse navbar-collapse-custom" id="navbarNav">
            <ul class="navbar-nav navbar-nav-main me-auto">
                <li class="nav-item nav-item-animated">
                    <a class="nav-link nav-link-custom" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- Admin/Teacher Menu Items -->
                <?php if (isset($user) && in_array($user['role'], ['admin', 'teacher'])): ?>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="students.php">
                            <i class="fas fa-user-graduate me-2"></i>
                            <span>Students</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="teachers.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            <span>Teachers</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="classes.php">
                            <i class="fas fa-school me-2"></i>
                            <span>Classes</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="attendance.php">
                            <i class="fas fa-calendar-check me-2"></i>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="grades.php">
                            <i class="fas fa-graduation-cap me-2"></i>
                            <span>Grades</span>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Grades Report (Admin, Teacher, Parent) -->
                <?php if (!isset($user) || in_array($user['role'], ['admin', 'teacher', 'parent'])): ?>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="grades_report.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            <span>Grades Report</span>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Admin/Teacher Fees & Enrollment -->
                <?php if (isset($user) && in_array($user['role'], ['admin', 'teacher'])): ?>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="fees.php">
                            <i class="fas fa-dollar-sign me-2"></i>
                            <span>Fees</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="enrollment.php">
                            <i class="fas fa-user-plus me-2"></i>
                            <span>Enrollment</span>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Parent-Specific Links -->
                <?php if (isset($user) && $user['role'] === 'parent'): ?>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="fees.php">
                            <i class="fas fa-dollar-sign me-2"></i>
                            <span>Fees</span>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Schedule & Financial (Admin/Teacher) -->
                <?php if (isset($user) && in_array($user['role'], ['admin', 'teacher'])): ?>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="schedule.php">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <span>Schedule</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom" href="financial.php">
                            <i class="fas fa-chart-line me-2"></i>
                            <span>Financial</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- User Menu -->
            <ul class="navbar-nav navbar-nav-user">
                <?php if ($user): ?>
                    <li class="nav-item dropdown user-dropdown">
                        <a class="nav-link dropdown-toggle user-menu-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <span class="user-info">
                                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                                <span class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                            </span>
                            <i class="fas fa-chevron-down ms-2 dropdown-arrow"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-custom" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item dropdown-item-custom" href="#">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item dropdown-item-custom" href="#">
                                    <i class="fas fa-cog me-2"></i>Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item dropdown-item-custom logout-link" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item nav-item-animated">
                        <a class="nav-link nav-link-custom login-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            <span>Login</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Animated Background Elements -->
    <div class="navbar-bg-elements">
        <div class="bg-element bg-element-1"></div>
        <div class="bg-element bg-element-2"></div>
        <div class="bg-element bg-element-3"></div>
    </div>
</nav>
<div class="container">
