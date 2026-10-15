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
        http_response_code(500);
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
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

    // Демонстрационные новости при первом запуске
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM news');
    $row = $stmt->fetch();
    $count = isset($row['c']) ? (int) $row['c'] : 0;
    if ($count === 0) {
        $samples = array(
            array(
                'title' => 'Профсоюзный вторник: охрана труда, подготовка к зиме и показатели по выполнению плана',
                'excerpt' => 'На еженедельном семинаре-совещании с председателями профкомов структурных подразделений комбината рассмотрели статистику по охране труда, выполнение мероприятий по работе в осенне-зимний период и данные об исполнении плана в сентябре.',
                'content' => "На еженедельном семинаре-совещании с председателями профкомов структурных подразделений комбината рассмотрели статистику по охране труда, выполнение мероприятий по работе в осенне-зимний период и данные об исполнении плана в сентябре.\n\nЗа прошедшую неделю травм на комбинате не допущено. С начала года зарегистрированы 2 тяжелых и 24 легких несчастных случая, а также 126 микротравм.\n\nОтдельно рассмотрели ход подготовки к осенне-зимнему периоду. В план включено 234 мероприятия, из них выполнено 111.\n\nРабота над Соглашением по охране труда на 2027 год завершена.",
                'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ),
            array(
                'title' => 'Здоровье и командный дух: на комбинате отметили лучших физоргов и победителей спартакиады',
                'excerpt' => 'Во Дворце культуры металлургов состоялось торжественное подведение итогов 67-й Спартакиады ЕВРАЗа. Событие объединило порядка 200 человек.',
                'content' => "Во Дворце культуры металлургов состоялось торжественное подведение итогов 67-й Спартакиады ЕВРАЗа. Событие объединило порядка 200 человек — руководителей, профсоюзных активистов, физоргов и спортивный актив предприятия.\n\nУчастники получили награды и дипломы. Спартакиада стала ярким завершением спортивного сезона.",
                'published_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            ),
            array(
                'title' => 'Ветеран профсоюзного движения получила заслуженное признание',
                'excerpt' => 'В конвертерном цехе НТМК состоялось награждение Нуризы Шакировой медалью Свердловского обкома ГМПР «Почетный ветеран».',
                'content' => "В конвертерном цехе Нижнетагильского металлургического комбината состоялось награждение Нуризы Шакировой медалью Свердловского обкома Горно-металлургического профсоюза России «Почетный ветеран Свердловской областной организации ГМПР».\n\nВысокую оценку ее труда и общественной работы дали за многолетнюю преданность делу, весомый вклад в развитие профсоюзного движения и последовательную защиту интересов работников.",
                'published_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            ),
        );
        $ins = $pdo->prepare("INSERT INTO news (title, slug, excerpt, content, image, is_published, published_at)
            VALUES (:title, :slug, :excerpt, :content, NULL, 1, :date)");
        foreach ($samples as $s) {
            $ins->execute(array(
                ':title' => $s['title'],
                ':slug' => slugify($s['title']),
                ':excerpt' => $s['excerpt'],
                ':content' => $s['content'],
                ':date' => $s['published_at'],
            ));
        }
    }
}