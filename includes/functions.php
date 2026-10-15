<?php

require_once dirname(__FILE__) . '/config.php';

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function flash($key, $value = null)
{
    if ($value === null) {
        $v = isset($_SESSION['flash'][$key]) ? $_SESSION['flash'][$key] : null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    $_SESSION['flash'][$key] = $value;
    return null;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf()
{
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    return is_string($token) && hash_equals_safe($_SESSION['csrf_token'], $token);
}

function hash_equals_safe($a, $b)
{
    // Полифилл hash_equals для PHP < 5.6
    if (function_exists('hash_equals')) {
        return hash_equals($a, $b);
    }
    if (strlen($a) !== strlen($b)) {
        return false;
    }
    $result = 0;
    for ($i = 0; $i < strlen($a); $i++) {
        $result |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $result === 0;
}

function slugify($text)
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'news-' . time();
    }
    return $text;
}

function format_date($dt)
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    $months = array(
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    );
    $m = (int) date('n', $ts);
    $monthName = isset($months[$m]) ? $months[$m] : date('F', $ts);
    return date('j', $ts) . ' ' . $monthName . ' ' . date('Y', $ts);
}

function truncate($text, $len = 180)
{
    $text = strip_tags($text);
    if (mb_strlen($text, 'UTF-8') <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len, 'UTF-8') . '…';
}

function make_password_hash($password)
{
    if (defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1) {
        $salt = '$2y$10$' . substr(str_replace('+', '.', base64_encode(md5(microtime(true), true))), 0, 22);
        return crypt($password, $salt);
    }
    // Запасной вариант
    return 'sha256:' . hash('sha256', $password . 'profkom_salt');
}

function verify_password($password, $hash)
{
    if (strpos($hash, 'sha256:') === 0) {
        return hash_equals_safe(substr($hash, 7), hash('sha256', $password . 'profkom_salt'));
    }
    // Blowfish / crypt
    return crypt($password, $hash) === $hash;
}

function upload_image($file)
{
    if (empty($file) || (isset($file['error']) && $file['error'] === UPLOAD_ERR_NO_FILE)) {
        return null;
    }
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Ошибка загрузки файла.');
    }
    if (!isset($file['size']) || $file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('Файл слишком большой (макс. 5 МБ).');
    }
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
    }
    // Запасная проверка: getimagesize надёжнее на старых хостингах
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        $info = @getimagesize($file['tmp_name']);
        if ($info !== false && isset($info['mime'])) {
            $mime = $info['mime'];
        }
    }
    // Последний шанс: по расширению, но только если это действительно изображение
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
        $info = @getimagesize($file['tmp_name']);
        if ($info !== false && isset($extMap[$ext])) {
            $mime = $extMap[$ext];
        }
    }
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        throw new Exception('Недопустимый тип файла. Разрешены JPG, PNG, GIF, WebP.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $ext = 'bin';
    switch ($mime) {
        case 'image/jpeg': $ext = 'jpg'; break;
        case 'image/png': $ext = 'png'; break;
        case 'image/gif': $ext = 'gif'; break;
        case 'image/webp': $ext = 'webp'; break;
    }
    $name = uniqid('img_', true) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Не удалось сохранить файл.');
    }
    return UPLOAD_URL . '/' . $name;
}

function delete_uploaded_image($path)
{
    if (!$path) {
        return;
    }
    $full = dirname(__FILE__) . '/../' . ltrim($path, '/');
    $realFull = realpath($full);
    $realDir = realpath(UPLOAD_DIR);
    if (is_file($full) && $realFull !== false && $realDir !== false && strpos($realFull, $realDir) === 0) {
        @unlink($full);
    }
}

function base_url()
{
    if (defined('BASE_URL') && BASE_URL !== '') {
        return rtrim(BASE_URL, '/');
    }
    static $detected = null;
    if ($detected !== null) {
        return $detected;
    }
    $d = '';
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $d = dirname($_SERVER['SCRIPT_NAME']);
        $d = str_replace('\\', '/', $d);
        if (basename($d) === 'admin') {
            $d = dirname($d);
        }
        $d = rtrim($d, '/');
        if ($d === '' || $d === '/' || $d === '.') {
            $d = '';
        }
    }
    $detected = $d;
    return $detected;
}

function asset($path)
{
    return base_url() . '/' . ltrim($path, '/');
}

function url($path = '')
{
    if ($path === '') {
        return base_url() . '/';
    }
    return base_url() . '/' . ltrim($path, '/');
}