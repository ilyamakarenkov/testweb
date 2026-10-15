<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// init db (creates schema)
get_db();

$currentPage = ltrim(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
$navItems = [
    ['href' => 'index.php', 'label' => 'Новости'],
    ['href' => 'about.php', 'label' => 'О профсоюзе'],
    ['href' => 'contacts.php', 'label' => 'Контакты'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e(APP_NAME); ?> — новости и информация">
    <title><?php echo e($pageTitle ?? APP_SHORT); ?> — <?php echo e(APP_SHORT); ?></title>
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/style.css')); ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?php echo e(url('index.php')); ?>">
            <span class="brand-logo">П</span>
            <span>
                <?php echo e(APP_SHORT); ?>
                <small><?php echo e(APP_TAGLINE); ?></small>
            </span>
        </a>
        <button class="menu-toggle" type="button" onclick="document.querySelector('.nav').classList.toggle('open')" aria-label="Меню">☰</button>
        <nav class="nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo e(url($item['href'])); ?>"
                   class="<?php echo $currentPage === $item['href'] ? 'active' : ''; ?>">
                    <?php echo e($item['label']); ?>
                </a>
            <?php endforeach; ?>
            <?php if (is_logged_in()): ?>
                <a href="<?php echo e(url('admin/index.php')); ?>" class="<?php echo (strpos($currentPage, 'admin') === 0) ? 'active' : ''; ?>">Админка</a>
            <?php else: ?>
                <a href="<?php echo e(url('admin/login.php')); ?>">Вход</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
<div class="container">
<?php
// Flash messages
foreach (['success', 'error', 'info'] as $fk) {
    if ($m = flash($fk)) {
        echo '<div class="alert alert-' . e($fk) . '">' . e($m) . '</div>';
    }
}
?>