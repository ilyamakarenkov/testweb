<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
require_login();
require_once dirname(__FILE__) . '/../includes/import.php';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Неверный токен безопасности.');
    } else {
        try {
            $rep = run_news_import();
            $_SESSION['import_report'] = $rep;
            flash('success', 'Импорт завершён: добавлено ' . (int) $rep['imported']
                . ', уже импортировано ' . (int) $rep['skipped']
                . ', ошибок ' . (int) $rep['errors'] . '.');
        } catch (Exception $ex) {
            flash('error', 'Импорт не выполнен: ' . $ex->getMessage());
        }
    }
    redirect(url('admin/import.php'));
}

$report = null;
if (isset($_SESSION['import_report'])) {
    $report = $_SESSION['import_report'];
    unset($_SESSION['import_report']);
}

$pdo = get_db();
$state = import_get_state($pdo);
$newsCountRow = $pdo->query('SELECT COUNT(*) AS c FROM news')->fetch();
$newsCount = isset($newsCountRow['c']) ? (int) $newsCountRow['c'] : 0;

$pageTitle = 'Импорт новостей';
require_once dirname(__FILE__) . '/../includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Импорт новостей</h1>
        <p>Загрузка публикаций со старого сайта profkomntmk.ru</p>
    </div>
</section>

<div style="margin-top:24px;">
    <div class="admin-wrap">
        <aside class="admin-sidebar">
            <a href="<?php echo e(url('admin/index.php')); ?>">Все новости</a>
            <a href="<?php echo e(url('admin/create.php')); ?>">+ Добавить новость</a>
            <a href="<?php echo e(url('admin/import.php')); ?>" class="active">Импорт</a>
            <a href="<?php echo e(url('admin/pages.php')); ?>">Страницы</a>
            <a href="<?php echo e(url('admin/password.php')); ?>">Сменить пароль</a>
            <a href="<?php echo e(url('index.php')); ?>">На сайт</a>
            <a href="<?php echo e(url('admin/logout.php')); ?>">Выйти</a>
            <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--line);font-size:.75rem;color:var(--muted);">Сборка: <?php echo defined('APP_BUILD') ? e(APP_BUILD) : '—'; ?></div>
        </aside>
        <div>
            <div class="form-card" style="margin-bottom:16px;">
                <p style="margin-top:0;">
                    Кнопка забирает новости со старого сайта profkomntmk.ru.
                    <strong>Первый запуск</strong> удалит демо-новости и добавит последние 20 статей;
                    <strong>каждый следующий</strong> — только новые статьи, появившиеся после последнего импорта.
                </p>
                <p style="color:var(--muted);font-size:.85rem;">
                    Последний импорт: <?php echo ($state && !empty($state['last_import'])) ? e($state['last_import']) : 'ещё не выполнялся'; ?> ·
                    Импортировано статей: <?php echo ($state && isset($state['imported_total'])) ? (int) $state['imported_total'] : 0; ?> ·
                    Новостей на сайте: <?php echo $newsCount; ?> ·
                    Источник: profkomntmk.ru
                </p>
                <form method="post" action="" onsubmit="return confirm('Запустить импорт последних новостей? Первый запуск удалит демо-новости и загрузит последние 20 статей; далее — только новые.');">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-primary" type="submit">Импорт последних новостей</button>
                </form>
            </div>

            <?php if ($report !== null): ?>
                <div class="form-card">
                    <h3 style="margin-top:0;">Результат импорта (<?php echo e($report['finished']); ?>)</h3>
                    <p style="font-size:.85rem;color:var(--muted);">
                        Режим: <?php
                            $modeLabels = array(
                                'first' => 'первый запуск',
                                'incremental' => 'только новые',
                                'recovered' => 'восстановление состояния',
                            );
                            $mk = isset($report['mode']) ? $report['mode'] : '';
                            echo isset($modeLabels[$mk]) ? e($modeLabels[$mk]) : e($mk);
                        ?> ·
                        добавлено: <?php echo (int) $report['imported']; ?> ·
                        удалено демо: <?php echo (int) $report['cleared']; ?> ·
                        пропущено: <?php echo (int) $report['skipped']; ?> ·
                        ошибок: <?php echo (int) $report['errors']; ?>
                    </p>
                    <?php if (!empty($report['lines'])): ?>
                        <div style="font-size:.85rem;line-height:1.6;background:var(--paper,#fff);border:1px solid var(--line);border-radius:8px;padding:12px 14px;">
                            <?php foreach ($report['lines'] as $line): ?>
                                <div<?php echo (strpos($line, '✗') === 0) ? ' style="color:#b42318;font-weight:600;"' : ''; ?>><?php echo e($line); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
