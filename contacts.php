<?php

$pageTitle = 'Контакты';
require_once dirname(__FILE__) . '/includes/header.php';
?>

<section class="masthead">
    <div class="container">
        <span class="kicker">Связаться с нами</span>
        <h1>Контакты</h1>
        <p>Приёмная, телефоны и адрес первичной профсоюзной организации ОАО «ЕВРАЗ НТМК»</p>
    </div>
</section>
<figure class="photo-strip">
    <img src="<?php echo e(asset('images/hero.jpg')); ?>" alt="Нижнетагильский металлургический комбинат">
</figure>

<div style="margin-top:24px;">
    <div class="contact-grid">
        <div class="contact-card">
            <h3>Адрес</h3>
            <p>622025, Свердловская обл., г. Нижний Тагил, ул. Металлургов, 1 (корпус 7а)</p>
        </div>
        <div class="contact-card">
            <h3>Телефоны</h3>
            <p>тел. (3435) 497-242</p>
            <p>тел./факс (3435) 490-216</p>
            <p>приёмная (3435) 49-72-42</p>
        </div>
        <div class="contact-card">
            <h3>E-mail и соцсети</h3>
            <p><a href="mailto:profkom.ntmk@evraz.com">profkom.ntmk@evraz.com</a></p>
            <p><a href="https://vk.com/club198963941" target="_blank" rel="noopener">Сообщество ВКонтакте</a></p>
        </div>
    </div>

    <div class="prose" style="margin-top:20px;">
        <h2>Руководство ППО</h2>
        <table class="admin-table">
            <thead><tr><th>Ф.И.О.</th><th>Должность</th><th>Телефон</th></tr></thead>
            <tbody>
                <tr><td>Радаев Владимир Григорьевич</td><td>Председатель ППО</td><td>49-72-42</td></tr>
                <tr><td>Шалин Евгений Николаевич</td><td>Заместитель председателя ППО</td><td>49-09-08</td></tr>
                <tr><td>Фисталь Владимир Владимирович</td><td>Заместитель председателя ППО</td><td>49-07-04</td></tr>
                <tr><td>Проскурнин Тимофей Александрович</td><td>Заместитель председателя ППО</td><td>49-10-30</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>