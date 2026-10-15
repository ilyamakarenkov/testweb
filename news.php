<?php

require_once dirname(__FILE__) . '/includes/db.php';
require_once dirname(__FILE__) . '/includes/functions.php';

$id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
if ($id <= 0) {
    redirect(url('index.php'));
}

$pdo = get_db();
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id AND is_published = 1");
$stmt->execute(array(':id' => $id));
$news = $stmt->fetch();
if (!$news) {
    header('HTTP/1.0 404 Not Found');
    $pageTitle = 'Не найдено';
    require_once dirname(__FILE__) . '/includes/header.php';
    echo '<div class="empty"><h3>Новость не найдена</h3><p><a href="' . e(url('index.php')) . '">Вернуться к новостям</a></p></div>';
    require_once dirname(__FILE__) . '/includes/footer.php';
    exit;
}

$pageTitle = $news['title'];
require_once dirname(__FILE__) . '/includes/header.php';

$cur = (isset($news['published_at']) && $news['published_at']) ? $news['published_at'] : $news['created_at'];
$prevStmt = $pdo->prepare("SELECT id, title FROM news WHERE is_published=1 AND (COALESCE(published_at,created_at) < :d1 OR (COALESCE(published_at,created_at)=:d2 AND id < :id)) ORDER BY COALESCE(published_at,created_at) DESC, id DESC LIMIT 1");
$prevStmt->execute(array(':d1' => $cur, ':d2' => $cur, ':id' => $id));
$prev = $prevStmt->fetch();
$nextStmt = $pdo->prepare("SELECT id, title FROM news WHERE is_published=1 AND (COALESCE(published_at,created_at) > :d1 OR (COALESCE(published_at,created_at)=:d2 AND id > :id)) ORDER BY COALESCE(published_at,created_at) ASC, id ASC LIMIT 1");
$nextStmt->execute(array(':d1' => $cur, ':d2' => $cur, ':id' => $id));
$next = $nextStmt->fetch();
?>

<article class="article">
    <div class="meta"><span class="pill pill-dark">Новость</span><?php echo e(format_date((isset($news['published_at']) && $news['published_at']) ? $news['published_at'] : $news['created_at'])); ?></div>
    <h1><?php echo e($news['title']); ?></h1>
    <?php if (!empty($news['image'])): ?>
        <div class="cover">
            <img src="<?php echo e(asset($news['image'])); ?>" alt="<?php echo e($news['title']); ?>">
        </div>
    <?php endif; ?>
    <div class="content">
        <?php
        $blocks = preg_split("/\n\s*\n/", (string) $news['content']);
        foreach ($blocks as $b) {
            $b = trim($b);
            if ($b === '') continue;
            echo '<p>' . nl2br(e($b), false) . '</p>';
        }
        ?>
    </div>

    <?php
    $gallery = get_news_gallery($news['id']);
    if (!empty($gallery)):
    ?>
        <section class="gallery">
            <h2 class="gallery-title">Фотогалерея</h2>
            <div class="gallery-grid">
                <?php foreach ($gallery as $g): ?>
                    <a class="gallery-item" href="<?php echo e(asset($g['image'])); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo e(asset($g['image'])); ?>" alt="Фото к новости" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>

<div style="max-width:860px;margin:18px auto 0;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <?php if ($prev): ?>
        <a class="btn btn-ghost" href="<?php echo e(url('news.php?id=' . (int)$prev['id'])); ?>">← <?php echo e(truncate($prev['title'], 40)); ?></a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
    <?php if ($next): ?>
        <a class="btn btn-ghost" href="<?php echo e(url('news.php?id=' . (int)$next['id'])); ?>"><?php echo e(truncate($next['title'], 40)); ?> →</a>
    <?php endif; ?>
</div>

<div style="max-width:860px;margin:18px auto 0;">
    <a class="btn btn-ghost" href="<?php echo e(url('index.php')); ?>">← Все новости</a>
</div>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>