<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user']) && isset($_SESSION['user']['id']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || ($user['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function login(string $identifier, string $password): bool
{
    $pdo = getDb();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.password_hash, u.role, u.name, u.teacher_id, u.student_id, u.parent_id
         FROM users u
         LEFT JOIN students s ON s.id = u.student_id
         LEFT JOIN teachers t ON t.id = u.teacher_id
         LEFT JOIN parents p ON p.id = u.parent_id
         WHERE u.username = :identifier_username
            OR s.contact = :identifier_student_contact
            OR t.email = :identifier_teacher_email
            OR p.email = :identifier_parent_email
         LIMIT 1'
    );
    $stmt->execute([
        ':identifier_username' => $identifier,
        ':identifier_student_contact' => $identifier,
        ':identifier_teacher_email' => $identifier,
        ':identifier_parent_email' => $identifier,
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'name' => $user['name'],
        'teacher_id' => $user['teacher_id'] ?? null,
        'student_id' => $user['student_id'] ?? null,
        'parent_id' => $user['parent_id'] ?? null,
    ];

    return true;
}

function logout(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
