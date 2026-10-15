<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value === null) {
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    $_SESSION['flash'][$key] = $value;
    return null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    $text = trim((string) $text, '-');
    if ($text === '') {
        $text = 'news-' . time();
    }
    return $text;
}

function format_date(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];
    $m = (int) date('n', $ts);
    return date('j', $ts) . ' ' . ($months[$m] ?? date('F', $ts)) . ' ' . date('Y', $ts);
}

function truncate(string $text, int $len = 180): string
{
    $text = strip_tags($text);
    if (mb_strlen($text, 'UTF-8') <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len, 'UTF-8') . '…';
}

function upload_image(array $file): ?string
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Ошибка загрузки файла.');
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Файл слишком большой (макс. 5 МБ).');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        throw new RuntimeException('Недопустимый тип файла. Разрешены JPG, PNG, GIF, WebP.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'bin',
    };
    $name = uniqid('img_', true) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Не удалось сохранить файл.');
    }
    return UPLOAD_URL . '/' . $name;
}

function delete_uploaded_image(?string $path): void
{
    if (!$path) {
        return;
    }
    $full = __DIR__ . '/../' . ltrim($path, '/');
    if (is_file($full) && str_starts_with(realpath($full) ?: '', realpath(UPLOAD_DIR) ?: '')) {
        @unlink($full);
    }
}

function base_url(): string
{
    if (defined('BASE_URL')) {
        return rtrim((string) BASE_URL, '/');
    }
    return '';
}

function asset(string $path): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    if ($path === '') {
        return base_url() . '/';
    }
    return base_url() . '/' . ltrim($path, '/');
}