<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
require_login();

$pageTitle = 'Админка — новости';
require_once dirname(__FILE__) . '/../includes/header.php';

$pdo = get_db();
$stmt = $pdo->query("SELECT * FROM news ORDER BY COALESCE(published_at, created_at) DESC, id DESC");
$news = $stmt->fetchAll();
?>

<section class="hero">
    <div class="container">
        <h1>Управление новостями</h1>
        <p>Добавление, редактирование и удаление публикаций</p>
    </div>
</section>

<div style="margin-top:24px;">
    <div class="admin-wrap">
        <aside class="admin-sidebar">
            <a href="<?php echo e(url('admin/index.php')); ?>" class="active">Все новости</a>
            <a href="<?php echo e(url('admin/create.php')); ?>">+ Добавить новость</a>
            <a href="<?php echo e(url('admin/password.php')); ?>">Сменить пароль</a>
            <a href="<?php echo e(url('index.php')); ?>">На сайт</a>
            <a href="<?php echo e(url('admin/logout.php')); ?>">Выйти</a>
        </aside>
        <div>
            <?php if (empty($news)): ?>
                <div class="empty">
                    <h3>Новостей пока нет</h3>
                    <p>Создайте первую публикацию.</p>
                    <a class="btn btn-primary" href="<?php echo e(url('admin/create.php')); ?>">Добавить новость</a>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Заголовок</th>
                            <th>Дата</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($news as $n): ?>
                            <tr>
                                <td><?php echo (int) $n['id']; ?></td>
                                <td><?php echo e($n['title']); ?></td>
                                <td><?php echo e(format_date((isset($n['published_at']) && $n['published_at']) ? $n['published_at'] : $n['created_at'])); ?></td>
                                <td><?php echo !empty($n['is_published']) ? 'Опубликована' : 'Черновик'; ?></td>
                                <td class="actions">
                                    <a class="btn btn-sm btn-ghost" href="<?php echo e(url('news.php?id=' . (int)$n['id'])); ?>">Просмотр</a>
                                    <a class="btn btn-sm btn-primary" href="<?php echo e(url('admin/edit.php?id=' . (int)$n['id'])); ?>">Изменить</a>
                                    <form method="post" action="<?php echo e(url('admin/delete.php')); ?>" style="display:inline;" onsubmit="return confirm('Удалить новость?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Удалить</button>
                                    </form>
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