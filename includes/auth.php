<?php

require_once dirname(__FILE__) . '/db.php';
require_once dirname(__FILE__) . '/functions.php';

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function current_user()
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
    $stmt->execute(array(':id' => $_SESSION['user_id']));
    $user = $stmt->fetch();
    if (!$user) {
        $user = null;
    }
    return $user;
}

function require_login()
{
    if (!is_logged_in()) {
        flash('error', 'Требуется авторизация.');
        redirect(url('admin/login.php'));
    }
}

function attempt_login($username, $password)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :u');
    $stmt->execute(array(':u' => $username));
    $user = $stmt->fetch();
    if ($user && verify_password($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    }
    return false;
}

function logout()
{
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}