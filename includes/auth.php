<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Требуется авторизация.');
        redirect(url('admin/login.php'));
    }
}

function attempt_login(string $username, string $password): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}