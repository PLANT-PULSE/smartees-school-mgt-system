<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function flash(string $type, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$type] = $message;
        return null;
    }

    if (!empty($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }

    return null;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
