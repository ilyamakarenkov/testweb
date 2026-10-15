<?php

require_once dirname(__FILE__) . '/includes/db.php';
require_once dirname(__FILE__) . '/includes/functions.php';
require_once dirname(__FILE__) . '/includes/auth.php';

$page = get_page_content('about');
$pageTitle = $page ? $page['title'] : 'О профсоюзе';
require_once dirname(__FILE__) . '/includes/header.php';
?>

<section class="masthead">
    <div class="container">
        <span class="kicker"><?php echo $page && $page['kicker'] !== '' ? e($page['kicker']) : 'ГМПР · ЕВРАЗ НТМК'; ?></span>
        <h1><?php echo e($pageTitle); ?></h1>
        <p><?php echo $page && $page['subtitle'] !== '' ? e($page['subtitle']) : 'Первичная профсоюзная организация ОАО «ЕВРАЗ НТМК» ГМПР'; ?></p>
    </div>
</section>
<figure class="photo-strip">
    <img src="<?php echo e(asset('images/about.jpg')); ?>" alt="Заседание профсоюза">
</figure>

<div style="margin-top:24px;">
    <div class="prose">
        <?php if ($page && trim($page['content']) !== ''): ?>
            <?php echo $page['content']; // HTML санитизируется при сохранении в админке ?>
        <?php else: ?>
            <p>Раздел в подготовке.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/includes/footer.php'; ?>
