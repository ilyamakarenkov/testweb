<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';

if (is_logged_in()) {
    redirect(url('admin/index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Неверный токен безопасности. Обновите страницу.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
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
        <form method="post" action="">
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
            По умолчанию: логин <strong>admin</strong>, пароль <strong>admin123</strong>. Смените пароль после первого входа.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>