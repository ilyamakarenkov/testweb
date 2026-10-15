<?php

$pageTitle = 'Новости';
require_once dirname(__FILE__) . '/includes/header.php';

$pdo = get_db();

$page = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = NEWS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->query("SELECT COUNT(*) AS c FROM news WHERE is_published = 1");
$totalRow = $totalStmt->fetch();
$total = isset($totalRow['c']) ? (int) $totalRow['c'] : 0;
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM news WHERE is_published = 1 ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT :l OFFSET :o");
$stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':o', $offset, PDO::PARAM_INT);
$stmt->execute();
$news = $stmt->fetchAll();
?>

<section class="hero">
    <div class="container">
        <h1>Новости профсоюза</h1>
        <p>Актуальные события, мероприятия и решения первичной профсоюзной организации ОАО «ЕВРАЗ НТМК».</p>
    </div>
</section>

<div style="margin-top:24px;">
    <?php if (empty($news)): ?>
        <div class="empty">
            <h3>Новостей пока нет</h3>
            <p>Загляните позже — здесь появятся свежие материалы.</p>
            <?php if (is_logged_in()): ?>
                <a class="btn btn-primary" href="<?php echo e(url('admin/create.php')); ?>">Добавить новость</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="news-grid">
            <?php foreach ($news as $n): ?>
                <article class="news-card">
                    <a class="thumb" href="<?php echo e(url('news.php?id=' . (int) $n['id'])); ?>">
                        <?php if (!empty($n['image'])): ?>
                            <img src="<?php echo e(asset($n['image'])); ?>" alt="<?php echo e($n['title']); ?>" loading="lazy">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#dbe3ec,#c3cdd9);color:#52606d;font-weight:700;font-size:1.2rem;">НТМК</div>
                        <?php endif; ?>
                    </a>
                    <div class="body">
                        <div class="meta"><?php echo e(format_date(isset($n['published_at']) && $n['published_at'] ? $n['published_at'] : $n['created_at'])); ?></div>
                        <h3><a href="<?php echo e(url('news.php?id=' . (int) $n['id'])); ?>"><?php echo e($n['title']); ?></a></h3>
                        <div class="excerpt"><?php echo e(truncate(isset($n['excerpt']) && $n['excerpt'] ? $n['excerpt'] : $n['content'], 160)); ?></div>
                        <a class="readmore" href="<?php echo e(url('news.php?id=' . (int) $n['id'])); ?>">Читать далее →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo e(url('index.php?page=' . ($page - 1))); ?>">← Предыдущая</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo e(url('index.php?page=' . $i)); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo e(url('index.php?page=' . ($page + 1))); ?>">Следующая →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>