<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
require_login();

$errors = array();
$success = '';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Неверный токен безопасности.';
    } else {
        $current = (string) (isset($_POST['current_password']) ? $_POST['current_password'] : '');
        $new1 = (string) (isset($_POST['new_password']) ? $_POST['new_password'] : '');
        $new2 = (string) (isset($_POST['new_password2']) ? $_POST['new_password2'] : '');

        if ($new1 === '' || strlen($new1) < 6) {
            $errors[] = 'Новый пароль должен быть не короче 6 символов.';
        }
        if ($new1 !== $new2) {
            $errors[] = 'Пароли не совпадают.';
        }
        if (empty($errors)) {
            $pdo = get_db();
            $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE id = :id');
            $stmt->execute(array(':id' => $_SESSION['user_id']));
            $user = $stmt->fetch();
            if (!$user || !verify_password($current, $user['password_hash'])) {
                $errors[] = 'Текущий пароль указан неверно.';
            } else {
                $upd = $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $upd->execute(array(
                    ':h' => make_password_hash($new1),
                    ':id' => $user['id'],
                ));
                $success = 'Пароль успешно изменён.';
            }
        }
    }
}

$pageTitle = 'Смена пароля';
require_once dirname(__FILE__) . '/../includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Смена пароля</h1>
        <p>Обновление учётных данных администратора</p>
    </div>
</section>

<div style="margin-top:24px;">
    <div class="form-card">
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php echo e(implode(' ', $errors)); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="current_password">Текущий пароль</label>
                <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="new_password">Новый пароль (мин. 6 символов)</label>
                <input class="form-control" type="password" id="new_password" name="new_password" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="new_password2">Повторите новый пароль</label>
                <input class="form-control" type="password" id="new_password2" name="new_password2" required autocomplete="new-password">
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Сменить пароль</button>
                <a class="btn btn-ghost" href="<?php echo e(url('admin/index.php')); ?>">Назад</a>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>