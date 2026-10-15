<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
require_login();

$errors = array();
$values = array(
    'title' => '',
    'excerpt' => '',
    'content' => '',
    'published_at' => date('Y-m-d\TH:i'),
    'is_published' => '1',
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

        if ($values['title'] === '') {
            $errors[] = 'Укажите заголовок.';
        }
        if ($values['content'] === '') {
            $errors[] = 'Укажите текст новости.';
        }

        $imagePath = null;
        if (empty($errors) && isset($_FILES['image']) && (isset($_FILES['image']['error']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $imagePath = upload_image($_FILES['image']);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            $pdo = get_db();
            $stmt = $pdo->prepare("INSERT INTO news (title, slug, excerpt, content, image, is_published, published_at)
                VALUES (:title, :slug, :excerpt, :content, :image, :pub, :date)");
            $stmt->execute(array(
                ':title' => $values['title'],
                ':slug' => slugify($values['title']),
                ':excerpt' => $values['excerpt'] !== '' ? $values['excerpt'] : null,
                ':content' => $values['content'],
                ':image' => $imagePath,
                ':pub' => (int) $values['is_published'],
                ':date' => $values['published_at'] !== '' ? str_replace('T', ' ', $values['published_at']) : date('Y-m-d H:i:s'),
            ));
            $newId = (int) $pdo->lastInsertId();
            // Фотогалерея
            if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['error'])) {
                try {
                    save_gallery_uploads($newId, $_FILES['gallery']);
                } catch (Exception $e) {
                    flash('error', 'Галерея: ' . $e->getMessage());
                }
            }
            flash('success', 'Новость создана.');
            redirect(url('admin/index.php'));
        }
    }
}

$pageTitle = 'Добавить новость';
require_once dirname(__FILE__) . '/../includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Добавить новость</h1>
        <p>Заполните поля и сохраните публикацию</p>
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
                <small style="color:var(--muted);">Пустая строка разделяет абзацы.</small>
            </div>
            <div class="form-group">
                <label for="image">Обложка</label>
                <input class="form-control" type="file" id="image" name="image" accept="image/*">
            </div>
            <div class="form-group">
                <label for="gallery">Фотогалерея (несколько файлов)</label>
                <input class="form-control" type="file" id="gallery" name="gallery[]" accept="image/*" multiple>
                <small style="color:var(--muted);">Можно выбрать сразу несколько изображений — они появятся под текстом новости.</small>
            </div>
            <div class="form-group">
                <label for="published_at">Дата публикации</label>
                <input class="form-control" type="datetime-local" id="published_at" name="published_at" value="<?php echo e($values['published_at']); ?>">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_published" value="1" <?php echo $values['is_published'] === '1' ? 'checked' : ''; ?>> Опубликовать сразу</label>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Сохранить</button>
                <a class="btn btn-ghost" href="<?php echo e(url('admin/index.php')); ?>">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>