<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
require_login();

$pages = get_editable_pages();

$pageTitle = 'Страницы сайта';
require_once dirname(__FILE__) . '/../includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Страницы сайта</h1>
        <p>Редактирование содержимого статических страниц</p>
    </div>
</section>

<div style="margin-top:24px;">
    <div class="admin-wrap">
        <aside class="admin-sidebar">
            <a href="<?php echo e(url('admin/index.php')); ?>">Все новости</a>
            <a href="<?php echo e(url('admin/create.php')); ?>">+ Добавить новость</a>
            <a href="<?php echo e(url('admin/pages.php')); ?>" class="active">Страницы</a>
            <a href="<?php echo e(url('admin/password.php')); ?>">Сменить пароль</a>
            <a href="<?php echo e(url('index.php')); ?>">На сайт</a>
            <a href="<?php echo e(url('admin/logout.php')); ?>">Выйти</a>
        </aside>
        <div>
            <?php if (empty($pages)): ?>
                <div class="empty">
                    <h3>Страницы не найдены</h3>
                    <p>Обновите базу данных — контент страниц создаётся автоматически.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Страница</th>
                            <th>Заголовок</th>
                            <th>Обновлена</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $p): ?>
                            <tr>
                                <td><code><?php echo e($p['page_key']); ?></code></td>
                                <td><?php echo e($p['title']); ?></td>
                                <td><?php echo e(isset($p['updated_at']) && $p['updated_at'] ? date('d.m.Y H:i', strtotime($p['updated_at'])) : '—'); ?></td>
                                <td class="actions">
                                    <a class="btn btn-sm btn-primary" href="<?php echo e(url('admin/page-edit.php?key=' . urlencode($p['page_key']))); ?>">Изменить</a>
                                    <?php if ($p['page_key'] === 'about'): ?>
                                        <a class="btn btn-sm btn-ghost" href="<?php echo e(url('about.php')); ?>" target="_blank">Просмотр</a>
                                    <?php elseif ($p['page_key'] === 'contacts'): ?>
                                        <a class="btn btn-sm btn-ghost" href="<?php echo e(url('contacts.php')); ?>" target="_blank">Просмотр</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
