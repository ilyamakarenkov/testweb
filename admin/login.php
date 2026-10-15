<?php

require_once dirname(__FILE__) . '/../includes/header.php';

if (is_logged_in()) {
    redirect(url('admin/index.php'));
}

$error = '';
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Неверный токен безопасности. Обновите страницу.';
    } else {
        $username = trim((string) (isset($_POST['username']) ? $_POST['username'] : ''));
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
        if (attempt_login($username, $password)) {
            flash('success', 'Вы вошли в систему.');
            redirect(url('admin/index.php'));
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
}
?>

<section class="hero">
    <div class="container">
        <h1>Вход в админку</h1>
        <p>Управление новостями сайта</p>
    </div>
</section>

<div style="margin-top:24px;">
    <div class="form-card">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="username">Логин</label>
                <input class="form-control" type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Войти</button>
                <a class="btn btn-ghost" href="<?php echo e(url('index.php')); ?>">На сайт</a>
            </div>
        </form>
        <p style="margin-top:14px;color:var(--muted);font-size:.85rem;">
            <?php
            // Показываем подсказку, только пока пароль не был изменён
            $showHint = false;
            try {
                $pdo = get_db();
                $st = $pdo->prepare('SELECT password_hash FROM users WHERE username = :u');
                $st->execute(array(':u' => ADMIN_DEFAULT_USER));
                $row = $st->fetch();
                if ($row && verify_password(ADMIN_DEFAULT_PASS, $row['password_hash'])) {
                    $showHint = true;
                }
            } catch (Exception $ex) {
                $showHint = false;
            }
            if ($showHint):
            ?>
                По умолчанию: логин <strong><?php echo e(ADMIN_DEFAULT_USER); ?></strong>, пароль <strong><?php echo e(ADMIN_DEFAULT_PASS); ?></strong>.
                <strong>Смените пароль сразу после входа</strong> (раздел «Смена пароля» в админке).
            <?php endif; ?>
        </p>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>