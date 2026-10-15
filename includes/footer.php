</div>
</main>
<footer class="site-footer">
    <div class="container foot-inner">
        <div>
            © <?php echo date('Y'); ?> <?php echo e(APP_NAME); ?><br>
            <small>Копирование информации только при условии ссылки на сайт<?php echo defined('APP_BUILD') ? ' · сборка ' . e(APP_BUILD) : ' · сборка —'; ?></small>
        </div>
        <div>
            Свердловская обл., г. Нижний Тагил, ул. Металлургов, 1, корпус 7<br>
            (3435) 49-02-16, 49-72-42 · profkom.ntmk@evraz.com
        </div>
    </div>
</footer>
<script>
document.addEventListener('click', function(e){
    var nav = document.querySelector('.nav');
    if (nav && nav.classList.contains('open') && !e.target.closest('.nav') && !e.target.closest('.menu-toggle')) {
        nav.classList.remove('open');
    }
});
</script>
</body>
</html>