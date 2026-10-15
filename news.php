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
            <h2 class="gallery-title">Фотогалерея <span class="gallery-count"><?php echo count($gallery); ?> фото</span></h2>
            <div class="gallery-grid" id="gallery-grid">
                <?php foreach ($gallery as $gi => $g): ?>
                    <button type="button" class="gallery-item" data-index="<?php echo (int) $gi; ?>">
                        <img src="<?php echo e(asset($g['image'])); ?>" alt="Фото <?php echo (int) $gi + 1; ?>" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Лайтбокс-листалка -->
        <div class="lb" id="gallery-lb" style="display:none;" aria-modal="true" role="dialog">
            <button type="button" class="lb-close" id="lb-close" aria-label="Закрыть">×</button>
            <button type="button" class="lb-arrow lb-prev" id="lb-prev" aria-label="Предыдущее фото">‹</button>
            <div class="lb-stage">
                <img id="lb-img" src="" alt="">
                <div class="lb-counter" id="lb-counter"></div>
            </div>
            <button type="button" class="lb-arrow lb-next" id="lb-next" aria-label="Следующее фото">›</button>
            <div class="lb-thumbs" id="lb-thumbs"></div>
        </div>
        <script>
        (function () {
            var urls = [];
            <?php foreach ($gallery as $g): ?>
            urls.push('<?php echo e(addslashes(asset($g['image']))); ?>');
            <?php endforeach; ?>
            var grid = document.getElementById('gallery-grid');
            var lb = document.getElementById('gallery-lb');
            var img = document.getElementById('lb-img');
            var counter = document.getElementById('lb-counter');
            var thumbsEl = document.getElementById('lb-thumbs');
            var cur = 0;

            if (!grid || !lb) return;

            // Миниатюры в лайтбоксе
            for (var i = 0; i < urls.length; i++) {
                var t = document.createElement('button');
                t.type = 'button';
                t.className = 'lb-thumb';
                t.setAttribute('data-i', i);
                var ti = document.createElement('img');
                ti.src = urls[i];
                ti.alt = '';
                t.appendChild(ti);
                t.addEventListener('click', function () { show(parseInt(this.getAttribute('data-i'), 10)); });
                thumbsEl.appendChild(t);
            }

            function show(i) {
                if (i < 0) i = urls.length - 1;
                if (i >= urls.length) i = 0;
                cur = i;
                img.src = urls[cur];
                counter.textContent = (cur + 1) + ' / ' + urls.length;
                var th = thumbsEl.children;
                for (var k = 0; k < th.length; k++) {
                    th[k].className = 'lb-thumb' + (k === cur ? ' active' : '');
                }
                lb.style.display = 'flex';
            }

            var buttons = grid.querySelectorAll('.gallery-item');
            for (var b = 0; b < buttons.length; b++) {
                buttons[b].addEventListener('click', function () {
                    show(parseInt(this.getAttribute('data-index'), 10));
                });
            }

            function close() { lb.style.display = 'none'; }
            document.getElementById('lb-close').addEventListener('click', close);
            document.getElementById('lb-prev').addEventListener('click', function () { show(cur - 1); });
            document.getElementById('lb-next').addEventListener('click', function () { show(cur + 1); });
            lb.addEventListener('click', function (e) {
                if (e.target === lb) close();
            });
            document.addEventListener('keydown', function (e) {
                if (lb.style.display === 'none') return;
                if (e.key === 'Escape') close();
                if (e.key === 'ArrowLeft') show(cur - 1);
                if (e.key === 'ArrowRight') show(cur + 1);
            });
        })();
        </script>
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