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

<section class="masthead">
    <div class="container">
        <span class="kicker">ГМПР · ЕВРАЗ НТМК</span>
        <h1>Новости профсоюза</h1>
        <p>События, мероприятия и решения первичной профсоюзной организации ОАО «ЕВРАЗ НТМК» — в одном месте.</p>
    </div>
</section>

<div style="margin-top:30px;">
    <?php if (empty($news)): ?>
        <div class="empty">
            <h3>Новостей пока нет</h3>
            <p>Загляните позже — здесь появятся свежие материалы.</p>
            <?php if (is_logged_in()): ?>
                <a class="btn btn-primary" href="<?php echo e(url('admin/create.php')); ?>">Добавить новость</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php
        $featured = $news[0];
        $rest = array_slice($news, 1);
        $featuredDate = (isset($featured['published_at']) && $featured['published_at']) ? $featured['published_at'] : $featured['created_at'];
        ?>
        <div class="feed-grid">
            <article class="featured">
                <a class="featured-media" href="<?php echo e(url('news.php?id=' . (int) $featured['id'])); ?>">
                    <?php if (!empty($featured['image'])): ?>
                        <img src="<?php echo e(asset($featured['image'])); ?>" alt="<?php echo e($featured['title']); ?>">
                    <?php else: ?>
                        <div class="thumb-ph">НТМК</div>
                    <?php endif; ?>
                </a>
                <div class="featured-body">
                    <div class="meta"><span class="pill">Главное</span><?php echo e(format_date($featuredDate)); ?></div>
                    <h2><a href="<?php echo e(url('news.php?id=' . (int) $featured['id'])); ?>"><?php echo e($featured['title']); ?></a></h2>
                    <div class="excerpt"><?php echo e(truncate(isset($featured['excerpt']) && $featured['excerpt'] ? $featured['excerpt'] : $featured['content'], 240)); ?></div>
                    <a class="readmore" href="<?php echo e(url('news.php?id=' . (int) $featured['id'])); ?>">Читать далее →</a>
                </div>
            </article>

            <aside class="feed-side">
                <div class="side-title">Также читают</div>
                <?php if (empty($rest)): ?>
                    <p style="color:var(--muted);font-size:.92rem;">Это пока единственная публикация.</p>
                <?php else: ?>
                    <?php foreach ($rest as $n): ?>
                        <?php $d = (isset($n['published_at']) && $n['published_at']) ? $n['published_at'] : $n['created_at']; ?>
                        <a class="story" href="<?php echo e(url('news.php?id=' . (int) $n['id'])); ?>">
                            <div class="story-thumb">
                                <?php if (!empty($n['image'])): ?>
                                    <img src="<?php echo e(asset($n['image'])); ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="thumb-ph">НТМК</div>
                                <?php endif; ?>
                            </div>
                            <div class="story-info">
                                <span class="story-title"><?php echo e($n['title']); ?></span>
                                <span class="meta" style="display:block;margin-top:5px;"><?php echo e(format_date($d)); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo e(url('index.php?page=' . ($page - 1))); ?>">← Назад</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo e(url('index.php?page=' . $i)); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo e(url('index.php?page=' . ($page + 1))); ?>">Вперёд →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<section class="about-teaser">
    <div class="about-teaser-img">
        <img src="<?php echo e(asset('images/about.jpg')); ?>" alt="Заседание профсоюза" loading="lazy">
    </div>
    <div class="about-teaser-text">
        <h2>О профсоюзе</h2>
        <p>Первичная профсоюзная организация ОАО «ЕВРАЗ НТМК» — одна из крупнейших в Горно-металлургическом профсоюзе России. Мы защищаем трудовые права работников, помогаем в решении бытовых и социальных вопросов, проводим спартакиады, оздоровительные программы и работу профсоюзных комиссий.</p>
        <div class="about-teaser-actions">
            <a class="btn btn-primary" href="<?php echo e(url('about.php')); ?>">Подробнее о профсоюзе</a>
            <a class="btn btn-ghost" href="<?php echo e(url('contacts.php')); ?>">Контакты и приёмная</a>
        </div>
    </div>
</section>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>