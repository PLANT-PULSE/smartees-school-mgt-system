<?php
require_once __DIR__ . '/inc/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both identifier and password.';
    } elseif (login($username, $password)) {
        if ($_SESSION['user']['role'] === 'teacher') {
            header('Location: teacher_portal.php');
        } elseif ($_SESSION['user']['role'] === 'student') {
            header('Location: student_portal.php');
        } elseif ($_SESSION['user']['role'] === 'parent') {
            header('Location: parent_dashboard.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(APP_NAME) ?> - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sAh/nCjZlX3f8vLjz9p4Mg2g53ffnAaHL5p4Y/2iAbg2fiLUIy5pR3w4vNwE7Jv9" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/school/assets/css/style.css" />
</head>
<body class="bg-light">
    <div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center">
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-graduation-cap fa-3x text-primary mb-3"></i>
                <h4>Welcome Back</h4>
                <p>Please sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="post" autocomplete="off">
                <div class="form-floating mb-3">
                    <input type="text" name="username" id="username" class="form-control" placeholder="Student ID / Email / Username" required autofocus>
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Student ID / Email / Username
                    </label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                </div>
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>

            <div class="demo-accounts">
                <h6><i class="fas fa-info-circle me-2"></i>Demo Accounts</h6>
                <div class="account">
                    <strong>admin</strong> / admin123
                </div>
                <div class="account">
                    <strong>teacher</strong> / teacher123
                </div>
                <div class="account">
                    <strong>student</strong> / student123
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-FkDoDBp2iEfL+XCaf9jW58Gx0g8L8G0TcvpF+hZQjZ/fEgg7r4f07iM7POsmfP2J" crossorigin="anonymous"></script>
    <script src="/school/assets/js/script.js"></script>
</body>
</html>
