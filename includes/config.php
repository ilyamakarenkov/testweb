<?php
declare(strict_types=1);

/**
 * Базовая конфигурация сайта.
 * При необходимости переключите DB_DRIVER на 'mysql' и заполните параметры подключения.
 */
define('APP_NAME', 'Первичная профсоюзная организация ОАО «ЕВРАЗ НТМК» ГМПР');
define('APP_SHORT', 'Профком НТМК');
define('APP_TAGLINE', 'Горно-металлургический профсоюз России');

// Базовый URL (пустая строка = корень домена). При размещении в подпапке укажите, напр. '/profkom'
define('BASE_URL', '');

define('DB_DRIVER', 'sqlite'); // 'sqlite' | 'mysql'

// MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'profkom');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// SQLite
define('DB_SQLITE_PATH', __DIR__ . '/../storage/database.sqlite');

// Загрузки
define('UPLOAD_DIR', __DIR__ . '/../storage/uploads');
define('UPLOAD_URL', 'storage/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Админ по умолчанию (создаётся при первом запуске)
define('ADMIN_DEFAULT_USER', 'admin');
define('ADMIN_DEFAULT_PASS', 'admin123');

// Пагинация
define('NEWS_PER_PAGE', 6);

// Часовой пояс
date_default_timezone_set('Asia/Yekaterinburg');

// Сессия
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}