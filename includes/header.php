<?php

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/db.php';
require_once dirname(__FILE__) . '/functions.php';
require_once dirname(__FILE__) . '/auth.php';

// init db (creates schema)
get_db();

$currentPage = ltrim(str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''), '/');
$navItems = array(
    array('href' => 'index.php', 'label' => 'Новости'),
    array('href' => 'about.php', 'label' => 'О профсоюзе'),
    array('href' => 'contacts.php', 'label' => 'Контакты'),
);
// Пункт «Админка» — только для авторизованных
if (is_logged_in()) {
    $navItems[] = array('href' => 'admin/index.php', 'label' => 'Админка');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e(APP_NAME); ?> — новости и информация">
    <title><?php echo e(isset($pageTitle) ? $pageTitle : APP_SHORT); ?> — <?php echo e(APP_SHORT); ?></title>
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/style.css')); ?>?v=<?php echo e(APP_BUILD); ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?php echo e(url('index.php')); ?>">
            <img class="brand-logo" src="<?php echo e(asset('images/logo.png')); ?>" alt="" width="44" height="44">
            <span>
                <?php echo e(APP_SHORT); ?>
                <small><?php echo e(APP_TAGLINE); ?></small>
            </span>
        </a>
        <button class="menu-toggle" type="button" onclick="document.querySelector('.nav').classList.toggle('open')" aria-label="Меню">☰</button>
        <nav class="nav">
            <?php foreach ($navItems as $item): ?>
                <?php
                $isActive = ($currentPage === $item['href'])
                    || ($item['href'] === 'admin/index.php' && strpos($currentPage, 'admin') === 0);
                ?>
                <a href="<?php echo e(url($item['href'])); ?>"
                   class="<?php echo $isActive ? 'active' : ''; ?>">
                    <?php echo e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main>
<div class="container">
<?php
// Flash messages
foreach (array('success', 'error', 'info') as $fk) {
    $m = flash($fk);
    if ($m) {
        echo '<div class="alert alert-' . e($fk) . '">' . e($m) . '</div>';
    }
}
?>