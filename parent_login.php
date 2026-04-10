<?php
require_once __DIR__ . '/inc/auth.php';

if (isLoggedIn()) {
    $user = currentUser();
    if ($user['role'] === 'parent') {
        header('Location: parent_dashboard.php');
        exit;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (login($username, $password)) {
        $user = currentUser();
        if ($user['role'] === 'parent') {
            header('Location: parent_dashboard.php');
            exit;
        } else {
            $error = 'This account is not a parent account.';
            logout();
        }
    } else {
        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Parent Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(APP_NAME) ?> - Parent Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sAh/nCjZlX3f8vLjz9p4Mg2g53ffnAaHL5p4Y/2iAbg2fiLUIy5pR3w4vNwE7Jv9" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/school/assets/css/style.css" />
    <style>
        .parent-login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 40px;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            color: white;
        }
        .parent-login-container .form-control {
            border-radius: 5px;
            border: none;
            background: rgba(255, 255, 255, 0.9);
        }
        .parent-login-container .form-control:focus {
            background: white;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .parent-login-container .btn-login {
            background: white;
            color: #667eea;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            padding: 10px;
            width: 100%;
        }
        .parent-login-container .btn-login:hover {
            background: #f0f0f0;
        }
        .parent-login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .parent-login-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .parent-login-header h4 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        .back-to-login a {
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-to-login a:hover {
            text-decoration: underline;
        }
        .demo-accounts {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 0.85rem;
        }
        .demo-accounts h6 {
            font-weight: bold;
            margin-bottom: 8px;
            color: #fff;
        }
        .demo-accounts .account {
            background: rgba(0, 0, 0, 0.2);
            padding: 8px;
            margin: 5px 0;
            border-radius: 3px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center">
        <div class="parent-login-container">
            <div class="parent-login-header">
                <i class="fas fa-user-tie"></i>
                <h4>Parent Portal</h4>
                <p>Access your child's school information</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-light alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2" style="color: #dc3545;"></i>
                    <strong style="color: #dc3545;">Error:</strong> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="post" autocomplete="off">
                <div class="form-floating mb-3">
                    <input type="text" name="username" id="username" class="form-control" placeholder="Username" required autofocus>
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Username
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
                <h6><i class="fas fa-info-circle me-2"></i>Demo Parent Account</h6>
                <div class="account">
                    <strong>parent</strong> / parent123
                </div>
                <p style="margin-top: 10px; font-size: 0.8rem;">Note: Demo account is pre-configured with sample students and fees.</p>
            </div>

            <div class="back-to-login">
                <i class="fas fa-arrow-left me-2"></i>
                <a href="/school/login.php">Back to Main Login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWLb8NVhmYQkJ2G9KhZDrtgSymQQAYAIIIyenbn4FK7FyStQ2tey6pY" crossorigin="anonymous"></script>
</body>
</html>
