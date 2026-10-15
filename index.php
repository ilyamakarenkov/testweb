<?php

require_once dirname(__FILE__) . '/includes/db.php';
require_once dirname(__FILE__) . '/includes/functions.php';
require_once dirname(__FILE__) . '/includes/auth.php';

$pdo = get_db();

$page = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = NEWS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->query("SELECT COUNT(*) AS c FROM news WHERE is_published = 1");
$totalRow = $totalStmt->fetch();
$total = isset($totalRow['c']) ? (int) $totalRow['c'] : 0;

$stmt = $pdo->prepare("SELECT * FROM news WHERE is_published = 1 ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT :l OFFSET :o");
$stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':o', $offset, PDO::PARAM_INT);
$stmt->execute();
$news = $stmt->fetchAll();

/**
 * Разметка одной строки ленты:
 * заголовок → дата → картинка (фикс. размер, текст обтекает) → анонс → читать далее.
 */
function render_feed_item($n)
{
    $d = (isset($n['published_at']) && $n['published_at']) ? $n['published_at'] : $n['created_at'];
    $href = e(url('news.php?id=' . (int) $n['id']));
    $html = '<article class="feed-item">';
    $html .= '<h2 class="feed-item-title"><a href="' . $href . '">' . e($n['title']) . '</a></h2>';
    $html .= '<div class="meta">' . e(format_date($d)) . '</div>';
    if (!empty($n['image'])) {
        $html .= '<a class="feed-item-media" href="' . $href . '" tabindex="-1" aria-hidden="true">'
            . '<img src="' . e(asset($n['image'])) . '" alt="" loading="lazy"></a>';
    }
    $html .= '<p class="feed-item-excerpt">' . e(truncate(isset($n['excerpt']) && $n['excerpt'] ? $n['excerpt'] : $n['content'], 260)) . '</p>';
    $html .= '<div class="feed-item-foot"><a class="readmore" href="' . $href . '">Читать далее →</a></div>';
    $html .= '</article>';
    return $html;
}

// AJAX-подгрузка следующей страницы ленты
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    $html = '';
    foreach ($news as $n) {
        $html .= render_feed_item($n);
    }
    $hasMore = ($offset + count($news)) < $total;
    echo json_encode(array(
        'html' => $html,
        'has_more' => $hasMore,
        'page' => $page,
        'total' => $total,
    ));
    exit;
}

$pageTitle = 'Новости';
require_once dirname(__FILE__) . '/includes/header.php';
?>

<section class="masthead">
    <div class="container">
        <span class="kicker">ГМПР · ЕВРАЗ НТМК</span>
        <h1>Новости профсоюза</h1>
        <p>События, мероприятия и решения первичной профсоюзной организации ОАО «ЕВРАЗ НТМК» — в одном месте.</p>
    </div>
</section>
<figure class="photo-strip">
    <img src="<?php echo e(asset('images/news.jpg')); ?>" alt="Новости профсоюза">
</figure>

<div class="feed-list-wrap">
    <div class="feed-list" id="feed-list">
        <?php if (empty($news)): ?>
            <div class="empty">
                <h3>Новостей пока нет</h3>
                <p>Загляните позже — здесь появятся свежие материалы.</p>
                <?php if (is_logged_in()): ?>
                    <a class="btn btn-primary" href="<?php echo e(url('admin/create.php')); ?>">Добавить новость</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($news as $n): ?>
                <?php echo render_feed_item($n); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="feed-loading" id="feed-loading" style="display:none;">
        <span class="feed-spinner"></span> Загрузка…
    </div>

    <?php if (!empty($news)): ?>
        <div class="feed-more-wrap" id="feed-more-wrap">
            <button type="button" class="btn btn-ghost" id="feed-more">Показать ещё</button>
        </div>
        <div class="feed-end" id="feed-end" style="display:none;">Вы посмотрели все новости</div>
    <?php endif; ?>
</div>

<script>
(function () {
    var list = document.getElementById('feed-list');
    if (!list) return;
    var loadingEl = document.getElementById('feed-loading');
    var moreWrap = document.getElementById('feed-more-wrap');
    var moreBtn = document.getElementById('feed-more');
    var endEl = document.getElementById('feed-end');
    var page = <?php echo $page; ?>;
    var hasMore = <?php echo (!empty($news) && ($offset + count($news)) < $total) ? 'true' : 'false'; ?>;
    var loading = false;

    function loadNext() {
        if (loading || !hasMore) return;
        loading = true;
        if (loadingEl) loadingEl.style.display = 'block';
        if (moreWrap) moreWrap.style.display = 'none';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', '<?php echo e(url('index.php')); ?>?ajax=1&page=' + (page + 1), true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            loading = false;
            if (loadingEl) loadingEl.style.display = 'none';
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.html) {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = data.html;
                        while (tmp.firstChild) {
                            list.appendChild(tmp.firstChild);
                        }
                    }
                    page = data.page;
                    hasMore = !!data.has_more;
                } catch (e) {
                    hasMore = false;
                }
            }
            if (hasMore) {
                if (moreWrap) moreWrap.style.display = 'block';
            } else {
                if (moreWrap) moreWrap.style.display = 'none';
                if (endEl) endEl.style.display = 'block';
            }
        };
        xhr.send();
    }

    if (moreBtn) moreBtn.addEventListener('click', loadNext);

    // Автоподгрузка: когда конец ленты почти в зоне видимости
    window.addEventListener('scroll', function () {
        if (!hasMore || loading) return;
        var doc = document.documentElement;
        var scrollBottom = (window.pageYOffset || doc.scrollTop) + window.innerHeight;
        if (scrollBottom >= doc.offsetHeight - 300) {
            loadNext();
        }
    }, { passive: true });
})();
</script>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
