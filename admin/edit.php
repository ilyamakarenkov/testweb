<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
require_login();

$id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
if ($id <= 0) {
    redirect(url('admin/index.php'));
}

$pdo = get_db();
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id");
$stmt->execute(array(':id' => $id));
$news = $stmt->fetch();
if (!$news) {
    flash('error', 'Новость не найдена.');
    redirect(url('admin/index.php'));
}

$errors = array();
$values = array(
    'title' => isset($news['title']) ? $news['title'] : '',
    'excerpt' => isset($news['excerpt']) ? $news['excerpt'] : '',
    'content' => isset($news['content']) ? $news['content'] : '',
    'published_at' => (isset($news['published_at']) && $news['published_at']) ? date('Y-m-d\TH:i', strtotime($news['published_at'])) : date('Y-m-d\TH:i'),
    'is_published' => !empty($news['is_published']) ? '1' : '0',
);

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Неверный токен безопасности.';
    } else {
        $values['title'] = trim((string) (isset($_POST['title']) ? $_POST['title'] : ''));
        $values['excerpt'] = trim((string) (isset($_POST['excerpt']) ? $_POST['excerpt'] : ''));
        $values['content'] = trim((string) (isset($_POST['content']) ? $_POST['content'] : ''));
        $values['published_at'] = trim((string) (isset($_POST['published_at']) ? $_POST['published_at'] : ''));
        $values['is_published'] = isset($_POST['is_published']) ? '1' : '0';
        $removeImage = isset($_POST['remove_image']);

        if ($values['title'] === '') {
            $errors[] = 'Укажите заголовок.';
        }
        if ($values['content'] === '') {
            $errors[] = 'Укажите текст новости.';
        }

        $imagePath = isset($news['image']) ? $news['image'] : null;
        if ($removeImage) {
            delete_uploaded_image(isset($news['image']) ? $news['image'] : null);
            $imagePath = null;
        }
        if (empty($errors) && isset($_FILES['image']) && (isset($_FILES['image']['error']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPath = upload_image($_FILES['image']);
                delete_uploaded_image(isset($news['image']) ? $news['image'] : null);
                $imagePath = $newPath;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE news SET title=:title, slug=:slug, excerpt=:excerpt, content=:content, image=:image, is_published=:pub, published_at=:date, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
            $stmt->execute(array(
                ':title' => $values['title'],
                ':slug' => slugify($values['title']),
                ':excerpt' => $values['excerpt'] !== '' ? $values['excerpt'] : null,
                ':content' => $values['content'],
                ':image' => $imagePath,
                ':pub' => (int) $values['is_published'],
                ':date' => $values['published_at'] !== '' ? str_replace('T', ' ', $values['published_at']) : date('Y-m-d H:i:s'),
                ':id' => $id,
            ));
            flash('success', 'Новость обновлена.');
            redirect(url('admin/index.php'));
        }
    }
}

$pageTitle = 'Редактировать новость';
require_once dirname(__FILE__) . '/../includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Редактировать новость</h1>
        <p><?php echo e(isset($news['title']) ? $news['title'] : ''); ?></p>
    </div>
</section>

<div style="margin-top:24px;">
    <div class="form-card">
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php echo e(implode(' ', $errors)); ?></div>
        <?php endif; ?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="title">Заголовок *</label>
                <input class="form-control" type="text" id="title" name="title" required value="<?php echo e($values['title']); ?>">
            </div>
            <div class="form-group">
                <label for="excerpt">Краткое описание</label>
                <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo e($values['excerpt']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="content">Текст новости *</label>
                <textarea class="form-control" id="content" name="content" required rows="10"><?php echo e($values['content']); ?></textarea>
            </div>
            <?php if (!empty($news['image'])): ?>
                <div class="form-group">
                    <label>Текущее изображение</label>
                    <img src="<?php echo e(asset($news['image'])); ?>" alt="" style="max-width:240px;border-radius:8px;margin-bottom:8px;">
                    <label><input type="checkbox" name="remove_image" value="1"> Удалить изображение</label>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="image">Новое изображение</label>
                <input class="form-control" type="file" id="image" name="image" accept="image/*">
            </div>
            <div class="form-group">
                <label for="published_at">Дата публикации</label>
                <input class="form-control" type="datetime-local" id="published_at" name="published_at" value="<?php echo e($values['published_at']); ?>">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_published" value="1" <?php echo $values['is_published'] === '1' ? 'checked' : ''; ?>> Опубликована</label>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Сохранить</button>
                <a class="btn btn-ghost" href="<?php echo e(url('admin/index.php')); ?>">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>