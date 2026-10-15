<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
require_login();

$key = (string) (isset($_GET['key']) ? $_GET['key'] : '');
if ($key === '' || ($key !== 'about' && $key !== 'contacts')) {
    flash('error', 'Неизвестная страница.');
    redirect(url('admin/pages.php'));
}

$row = get_page_content($key);
if (!$row) {
    flash('error', 'Страница не найдена в базе.');
    redirect(url('admin/pages.php'));
}

$errors = array();
$values = array(
    'kicker' => isset($row['kicker']) ? $row['kicker'] : '',
    'title' => isset($row['title']) ? $row['title'] : '',
    'subtitle' => isset($row['subtitle']) ? $row['subtitle'] : '',
    'content' => isset($row['content']) ? $row['content'] : '',
);

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Неверный токен безопасности.';
    } else {
        $values['kicker'] = trim((string) (isset($_POST['kicker']) ? $_POST['kicker'] : ''));
        $values['title'] = trim((string) (isset($_POST['title']) ? $_POST['title'] : ''));
        $values['subtitle'] = trim((string) (isset($_POST['subtitle']) ? $_POST['subtitle'] : ''));
        $values['content'] = trim((string) (isset($_POST['content']) ? $_POST['content'] : ''));

        if ($values['title'] === '') {
            $errors[] = 'Укажите заголовок страницы.';
        }
        if ($values['content'] === '') {
            $errors[] = 'Укажите содержимое страницы.';
        }

        if (empty($errors)) {
            // Оставляем только разрешённые теги (безопасность)
            $allowed = '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><u><a><blockquote><div><span><table><thead><tbody><tfoot><tr><td><th><caption><img>';
            $values['content'] = strip_tags($values['content'], $allowed);
            // Убираем потенциально опасные атрибуты
            $values['content'] = preg_replace('/\s*on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $values['content']);
            $values['content'] = preg_replace('/\s*javascript:/i', '', $values['content']);
        }

        if (empty($errors)) {
            $pdo = get_db();
            $upd = $pdo->prepare('UPDATE pages SET kicker=:g, title=:t, subtitle=:s, content=:c, updated_at=CURRENT_TIMESTAMP WHERE page_key=:k');
            $upd->execute(array(
                ':g' => $values['kicker'],
                ':t' => $values['title'],
                ':s' => $values['subtitle'],
                ':c' => $values['content'],
                ':k' => $key,
            ));
            flash('success', 'Страница сохранена.');
            redirect(url('admin/pages.php'));
        }
    }
}

$pageTitle = 'Редактирование страницы';
require_once dirname(__FILE__) . '/../includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1><?php echo $key === 'about' ? 'Страница «О профсоюзе»' : 'Страница «Контакты»'; ?></h1>
        <p>Правки применяются сразу после сохранения</p>
    </div>
</section>

<div style="margin-top:24px;">
    <div class="form-card">
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php echo e(implode(' ', $errors)); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="kicker">Надзаголовок (красная плашка)</label>
                <input class="form-control" type="text" id="kicker" name="kicker" value="<?php echo e($values['kicker']); ?>">
            </div>
            <div class="form-group">
                <label for="title">Заголовок страницы *</label>
                <input class="form-control" type="text" id="title" name="title" required value="<?php echo e($values['title']); ?>">
            </div>
            <div class="form-group">
                <label for="subtitle">Подзаголовок</label>
                <input class="form-control" type="text" id="subtitle" name="subtitle" value="<?php echo e($values['subtitle']); ?>">
            </div>
            <div class="form-group">
                <label for="content">Содержимое (HTML) *</label>
                <textarea class="form-control" id="content" name="content" required rows="18" style="font-family:monospace;font-size:.9rem;"><?php echo e($values['content']); ?></textarea>
                <small style="color:var(--muted);">
                    Разрешённые теги: p, h2, h3, ul, ol, li, strong, em, a, br, div, span, table и td/th.
                    Пример: &lt;h2&gt;Заголовок&lt;/h2&gt; &lt;p&gt;Абзац&lt;/p&gt; · список: &lt;li&gt;элемент&lt;/li&gt; внутри &lt;ul&gt;.
                </small>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Сохранить</button>
                <a class="btn btn-ghost" href="<?php echo e(url('admin/pages.php')); ?>">Назад</a>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
