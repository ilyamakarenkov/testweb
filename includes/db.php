<?php

require_once dirname(__FILE__) . '/config.php';
if (!function_exists('slugify')) {
    require_once dirname(__FILE__) . '/functions.php';
}

function get_db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'mysql') {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ));
        } else {
            $dir = dirname(DB_SQLITE_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    } catch (PDOException $e) {
        header('HTTP/1.0 500 Internal Server Error');
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Ошибка БД</title></head><body style="font-family:sans-serif;padding:24px;">';
        echo '<h1>Не удалось подключиться к базе данных</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p>Проверьте, что в PHP включено расширение <code>pdo_sqlite</code> (или <code>pdo_mysql</code> при использовании MySQL) и что каталог <code>storage/</code> доступен для записи.</p>';
        echo '</body></html>';
        exit;
    }

    init_schema($pdo);
    return $pdo;
}

function init_schema($pdo)
{
    $isMysql = (DB_DRIVER === 'mysql');

    if ($isMysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS news (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            excerpt TEXT,
            content TEXT NOT NULL,
            image VARCHAR(255) DEFAULT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            published_at DATETIME DEFAULT NULL,
            source_url VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS news_gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            news_id INT NOT NULL,
            image VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            KEY idx_news_gallery_news (news_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_key VARCHAR(50) NOT NULL UNIQUE,
            title VARCHAR(255) DEFAULT NULL,
            subtitle VARCHAR(500) DEFAULT NULL,
            kicker VARCHAR(255) DEFAULT NULL,
            content TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS news (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT,
            excerpt TEXT,
            content TEXT NOT NULL,
            image TEXT,
            is_published INTEGER NOT NULL DEFAULT 1,
            published_at DATETIME,
            source_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS news_gallery (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            news_id INTEGER NOT NULL,
            image TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_key TEXT NOT NULL UNIQUE,
            title TEXT,
            subtitle TEXT,
            kicker TEXT,
            content TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Админ по умолчанию
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM users');
    $row = $stmt->fetch();
    $count = isset($row['c']) ? (int) $row['c'] : 0;
    if ($count === 0) {
        $hash = make_password_hash(ADMIN_DEFAULT_PASS);
        $ins = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:u, :h)');
        $ins->execute(array(':u' => ADMIN_DEFAULT_USER, ':h' => $hash));
    }

    seed_pages($pdo);

    // Миграция: колонка source_url (импорт новостей со старого сайта).
    // Для уже существующих баз колонки нет — добавляем; для новых она уже
    // создана вместе с таблицей (проверка через пробный SELECT).
    $hasSource = false;
    try {
        $pdo->query('SELECT source_url FROM news LIMIT 1');
        $hasSource = true;
    } catch (PDOException $e) {
        $hasSource = false;
    }
    if (!$hasSource) {
        $pdo->exec('ALTER TABLE news ADD COLUMN source_url VARCHAR(255) DEFAULT NULL');
    }
}

/**
 * Контент статических страниц (редактируется в админке).
 */
function seed_pages($pdo)
{
    $aboutContent = <<<'EOT'
<h2>Славная история, богатый опыт</h2>
<p>В 2022 году профсоюзной организации «ЕВРАЗ Нижнетагильский металлургический комбинат» исполнилось 85 лет. К этой дате металлурги подошли, имея в своём активе славную историю, богатый опыт социального партнёрства, твёрдые принципы в защите Человека труда и веру в рабочую солидарность.</p>
<p>Своим днём рождения первичная профсоюзная организация ЕВРАЗ НТМК по праву считает 23 октября 1937 года — в этот день строители сдали в эксплуатацию бандажный стан, первый из цехов основного металлургического производства строящегося Ново-Тагильского металлургического завода.</p>
<p>Сегодня профсоюз на предприятии — действенная сила, которая объединяет людей, защищает их права, заботится о благополучии родного комбината и каждого из его работников.</p>
<h2>Сегодня</h2>
<p>Первичная профсоюзная организация НТМК насчитывает в своих рядах около 20 тысяч членов и является самой многочисленной в Свердловской области. Успешно работают 58 цеховых профорганизаций и 584 профсоюзные группы.</p>
<p>С 1998 года организацию возглавляет Владимир Григорьевич Радаев. Предметом заботы всех звеньев профсоюзной организации остаются состояние здоровья металлургов, уровень заработной платы, организация питания, трудовое соперничество, оказание материальной поддержки, контроль за соблюдением техники безопасности, работа с молодёжью и помощь пенсионерам, благотворительность, правовая защита работников, улучшение жилищных условий.</p>
<p>Профсоюзный комитет руководствуется в своей работе главным правовым документом предприятия — коллективным договором, являясь инициатором его заключения и главным разработчиком.</p>
<h2>Руководство</h2>
<ul>
<li><strong>Радаев Владимир Григорьевич</strong> — Председатель ППО</li>
<li><strong>Фисталь Владимир Владимирович</strong> — Заместитель председателя ППО</li>
<li><strong>Проскурнин Тимофей Александрович</strong> — Заместитель председателя ППО</li>
<li><strong>Шалин Евгений Николаевич</strong> — Заместитель председателя ППО</li>
</ul>
<p>Первичная профорганизация ОАО «ЕВРАЗ НТМК» ГМПР входит в Горно-металлургический профсоюз России — один из самых мощных и влиятельных в Федерации независимых профсоюзов страны.</p>
EOT;

    $contactsContent = <<<'EOT'
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
<h2>Руководство ППО</h2>
<div class="table-scroll" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
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
EOT;

    $pages = array(
        array(
            'key' => 'about',
            'title' => 'О профсоюзе',
            'subtitle' => 'Первичная профсоюзная организация ОАО «ЕВРАЗ НТМК» ГМПР',
            'kicker' => 'ГМПР · ЕВРАЗ НТМК',
            'content' => $aboutContent,
        ),
        array(
            'key' => 'contacts',
            'title' => 'Контакты',
            'subtitle' => 'Приёмная, телефоны и адрес первичной профсоюзной организации ОАО «ЕВРАЗ НТМК»',
            'kicker' => 'Связаться с нами',
            'content' => $contactsContent,
        ),
    );

    $exists = $pdo->prepare('SELECT id FROM pages WHERE page_key = :k');
    $ins = $pdo->prepare('INSERT INTO pages (page_key, title, subtitle, kicker, content) VALUES (:k, :t, :s, :g, :c)');
    foreach ($pages as $p) {
        $exists->execute(array(':k' => $p['key']));
        if (!$exists->fetch()) {
            $ins->execute(array(
                ':k' => $p['key'],
                ':t' => $p['title'],
                ':s' => $p['subtitle'],
                ':g' => $p['kicker'],
                ':c' => $p['content'],
            ));
        }
    }
}
