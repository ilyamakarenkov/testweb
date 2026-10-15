<?php

/**
 * Импорт новостей со старого Joomla-сайта profkomntmk.ru.
 *
 * Логика:
 *  - первое нажатие (состояния ещё нет): удаляет демо-новости и забирает
 *    последние 20 статей со старого сайта;
 *  - каждое следующее нажатие: только те статьи, которых ещё не было
 *    в импорте (список источников хранится в pages.__import_state).
 *
 * Изображения копируются на сервере: сначала пробуем локальный файл
 * (старый сайт живёт на том же хосте), затем — загрузку по HTTP.
 *
 * Требуется PHP 5.3+.
 */

require_once dirname(__FILE__) . '/functions.php';

if (!defined('IMPORT_SOURCE_BASE')) {
    define('IMPORT_SOURCE_BASE', 'https://www.profkomntmk.ru');
}
if (!defined('IMPORT_FIRST_COUNT')) {
    define('IMPORT_FIRST_COUNT', 20);
}
if (!defined('IMPORT_PAGE_SIZE')) {
    define('IMPORT_PAGE_SIZE', 5);
}
if (!defined('IMPORT_MAX_PAGES')) {
    define('IMPORT_MAX_PAGES', 10);
}
if (!defined('IMPORT_MAX_IMAGE_BYTES')) {
    define('IMPORT_MAX_IMAGE_BYTES', 8 * 1024 * 1024);
}
if (!defined('IMPORT_MAX_GALLERY')) {
    define('IMPORT_MAX_GALLERY', 9);
}

/**
 * Заголовки демо-новостей (бывший сид из db.php) — при первом импорте
 * удаляются только строки с этими заголовками и без source_url,
 * чтобы не трогать вручную созданные новости.
 */
function import_demo_titles()
{
    $titles = array(
        'Профсоюзный вторник: охрана труда, подготовка к зиме и показатели по выполнению плана',
        'Здоровье и командный дух: на комбинате отметили лучших физоргов и победителей спартакиады',
        'Ветеран профсоюзного движения получила заслуженное признание',
        'Итоги осеннего тура спартакиады профкомов: имена победителей',
        'Правовая помощь: приём юриста в профкоме',
        'Профком передал школьникам наборы к новому учебному году',
        'Соглашение по охране труда на 2027 год: проект на обсуждении',
        'Молодёжный совет провёл встречу с ветеранами комбината',
        'Профсоюзный семинар: как оформить материальную помощь',
        'Итоги работы комиссии по социальным вопросам за квартал',
        'На комбинате прошёл день открытых дверей для детей работников',
        'Профсоюз и администрация подписали соглашение по питанию',
        'Тренинг по эргономике: как избежать травм на рабочем месте',
        'Профком подвёл итоги акции «Осенний урожай» в поддержку ветеранов',
        'Кружки и секции Дворца культуры: расписание на новый сезон',
        'Семинар по профессиональному развитию: программы обучения',
        'Благотворительный концерт собрал помощь для семей с детьми',
        'Обновлён пункт первой помощи на производстве',
        'Профсоюзная приёмка квартир: семьи получили новые ключи',
        'Волонтёрская десантная бригада профкома помогает ветеранам',
    );
    $out = array();
    foreach ($titles as $t) {
        $out[] = mb_strtolower($t, 'UTF-8');
    }
    return $out;
}

/**
 * HTTP-статус из $http_response_header.
 */
function import_http_status($headers)
{
    if (!is_array($headers)) {
        return 0;
    }
    $code = 0;
    foreach ($headers as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', (string) $line, $m)) {
            $code = (int) $m[1];
        }
    }
    return $code;
}

function import_stream_get($url, $timeout, $secure)
{
    $opts = array(
        'http' => array(
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: Mozilla/5.0 (compatible; ProfkomImport/1.0)\r\n"
                . "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
                . "Accept-Language: ru\r\n"
                . "Connection: close\r\n",
            'follow_location' => 1,
            'max_redirects' => 5,
            'ignore_errors' => true,
        ),
    );
    if (stripos($url, 'https://') === 0) {
        $opts['ssl'] = array(
            'verify_peer' => $secure,
            'verify_peer_name' => $secure,
        );
    }
    $ctx = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || $body === '') {
        return false;
    }
    $status = import_http_status(isset($http_response_header) ? $http_response_header : null);
    if ($status === 0 || ($status >= 200 && $status < 400)) {
        return $body;
    }
    return false;
}

/**
 * Загрузка URL: потоки (проверка сертификата), затем curl,
 * затем потоки без проверки сертификата (старые хостинги без CA-бандла).
 */
function import_fetch_url($url, $timeout = 20)
{
    $body = import_stream_get($url, $timeout, true);
    if ($body !== false) {
        return $body;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ProfkomImport/1.0)');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/html,application/xhtml+xml,*/*;q=0.8', 'Accept-Language: ru'));
        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($data !== false && $data !== '' && $code >= 200 && $code < 400) {
            return $data;
        }
    }

    return import_stream_get($url, $timeout, false);
}

/**
 * Приведение документа к UTF-8 (старый сайт отдаёт utf-8, но защищаемся).
 */
function import_ensure_utf8($html)
{
    $html = (string) $html;
    if (!function_exists('mb_check_encoding') || !function_exists('mb_convert_encoding')) {
        return $html;
    }
    if (mb_check_encoding($html, 'UTF-8')) {
        return $html;
    }
    $cs = '';
    if (preg_match('/charset\s*=\s*["\']?([a-zA-Z0-9._-]+)/i', substr($html, 0, 2000), $m)) {
        $cs = $m[1];
    }
    if ($cs !== '' && strcasecmp($cs, 'utf-8') !== 0 && strcasecmp($cs, 'utf8') !== 0) {
        $conv = @mb_convert_encoding($html, 'UTF-8', $cs);
        if (is_string($conv) && $conv !== '') {
            return $conv;
        }
    }
    $conv = @mb_convert_encoding($html, 'UTF-8', 'UTF-8');
    return (is_string($conv) && $conv !== '') ? $conv : $html;
}

/**
 * Страница-заглушка «Database Error» от старого Joomla.
 */
function import_looks_like_db_error($html)
{
    $h = substr((string) $html, 0, 4000);
    if ($h === '') {
        return true;
    }
    return (stripos($h, 'Could not connect to MySQL') !== false)
        || (stripos($h, 'Database Error') !== false)
        || (stripos($h, 'JDatabaseDriver::') !== false);
}

/**
 * Нормализация ссылки к декодированному пути: absolute/relative/query →
 * '/news/1540-2026-10-14-09-06-04.html'. Ключи состояния, RSS и колонка
 * source_url используют именно такой вид.
 */
function import_normalize_path($urlOrPath)
{
    $p = trim((string) $urlOrPath);
    if ($p === '') {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $p)) {
        $path = parse_url($p, PHP_URL_PATH);
        $p = ($path === null || $path === false) ? '' : (string) $path;
    }
    $q = strpos($p, '?');
    if ($q !== false) {
        $p = substr($p, 0, $q);
    }
    $h = strpos($p, '#');
    if ($h !== false) {
        $p = substr($p, 0, $h);
    }
    if ($p === '') {
        return '';
    }
    return rawurldecode($p);
}

/**
 * Полный URL старого сайта для (декодированного) пути.
 */
function import_build_url($path)
{
    $path = '/' . ltrim((string) $path, '/');
    $segs = explode('/', $path);
    foreach ($segs as $i => $s) {
        $segs[$i] = rawurlencode($s);
    }
    return IMPORT_SOURCE_BASE . implode('/', $segs);
}

/**
 * Разбор страницы-списка новостей: путь => элемент (id, title).
 * Сохраняется порядок страницы (свежие сверху), дубли по ссылке схлопываются.
 */
function import_parse_list($html)
{
    $out = array();
    if (!preg_match_all('/<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $m)) {
        return $out;
    }
    $n = count($m[2]);
    for ($i = 0; $i < $n; $i++) {
        $path = import_normalize_path($m[2][$i]);
        if (!preg_match('#^/news/(\d+)(-|\.html)#', $path, $um)) {
            continue;
        }
        $id = (int) $um[1];
        $raw = html_entity_decode(strip_tags($m[3][$i]), ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/\s+/u', ' ', $raw);
        if ($t === null) {
            $t = preg_replace('/\s+/', ' ', (string) $raw);
        }
        $t = trim($t);
        if (mb_strlen($t, 'UTF-8') < 8) {
            continue; // обёртки картинок, «подробнее» и прочие служебные ссылки
        }
        if (isset($out[$path]) && strlen($t) <= strlen($out[$path]['title'])) {
            continue;
        }
        $out[$path] = array('path' => $path, 'id' => $id, 'title' => $t);
    }
    return $out;
}

/**
 * Дата из слага URL: /news/1540-2026-10-14-09-06-04.html → 2026-10-14 09:06:04.
 * В слагах без даты (битые ссылки) — null, тогда используется RSS или
 * ориентировочная дата относительно предыдущей статьи.
 */
function import_date_from_path($path)
{
    if (preg_match('#-(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})\.html$#', $path, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6];
    }
    return null;
}

/**
 * Даты публикации из RSS-ленты старого сайта: путь => 'Y-m-d H:i:s' (UTC).
 */
function import_parse_rss_dates($xml)
{
    $map = array();
    if (!preg_match_all('#<item\b[^>]*>(.*?)</item>#is', $xml, $m)) {
        return $map;
    }
    foreach ($m[1] as $one) {
        if (!preg_match('#<link\b[^>]*>\s*(?:<!\[CDATA\[)?\s*(.*?)\s*(?:\]\]>)?\s*</link>#is', $one, $lm)) {
            continue;
        }
        $path = import_normalize_path($lm[1]);
        if ($path === '') {
            continue;
        }
        if (!preg_match('#<pubDate\b[^>]*>\s*(?:<!\[CDATA\[)?\s*(.*?)\s*(?:\]\]>)?\s*</pubDate>#is', $one, $dm)) {
            continue;
        }
        $ts = strtotime(trim($dm[1]));
        if ($ts !== false) {
            // pubDate в формате RFC822 с зоной; на старом сайте время UTC
            $map[$path] = gmdate('Y-m-d H:i:s', $ts);
        }
    }
    return $map;
}

/**
 * Заголовок статьи: подсказка из списка → meta title → ссылка заголовка → <title>.
 */
function import_article_title($html, $hint)
{
    $hint = trim((string) $hint);
    if ($hint !== '') {
        return $hint;
    }
    if (preg_match('/<meta\s+name="title"\s+content="([^"]*)"/i', $html, $m)) {
        $t = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        if ($t !== '') {
            return $t;
        }
    }
    if (preg_match('/class="contentpagetitle_news"[^>]*>(.*?)<\/a>/is', $html, $m)) {
        $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')));
        if ($t !== null && $t !== '') {
            return $t;
        }
    }
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
        $t = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/\s+-\s+Первичная профсоюзная организация.*$/u', '', trim($t));
        if ($t !== null && trim($t) !== '') {
            return trim($t);
        }
    }
    return '';
}

/**
 * Выделение блока текста статьи: после таблицы заголовка (contentheading_news)
 * до таблицы pagenav (или подвала). Работает и для ?print=1, и для
 * ?tmpl=component, и для обычной страницы.
 */
function import_split_article($html)
{
    $end = strpos($html, 'class="pagenav"');
    if ($end === false) {
        $f = strpos($html, 'Свердловская обл., г. Нижний Тагил');
        if ($f !== false) {
            $end = $f;
        }
    }
    if ($end !== false) {
        // обрезаем по началу тега, чтобы не оставить хвост «<table align=...»
        $lt = strrpos(substr($html, 0, $end), '<');
        if ($lt !== false) {
            $end = $lt;
        }
    }

    $start = false;
    $h = strpos($html, 'contentheading_news');
    if ($h !== false) {
        $t = strpos($html, '</table>', $h);
        if ($t !== false) {
            $start = $t + strlen('</table>');
        }
    }
    if ($start === false && preg_match('/onclick="window\.print\(\)[^>]*>/', $html, $m, PREG_OFFSET_CAPTURE)) {
        $start = $m[0][1] + strlen($m[0][0]);
    }
    if ($start === false && preg_match('/class="contentpagetitle_news"[^>]*>.*?<\/a>/is', $html, $m, PREG_OFFSET_CAPTURE)) {
        $start = $m[0][1] + strlen($m[0][0]);
    }
    if ($start === false) {
        return null;
    }
    if ($end === false) {
        $end = strlen($html);
    }
    if ($end <= $start) {
        return null;
    }
    return substr($html, $start, $end - $start);
}

/**
 * Абсолютный URL картинки старого сайта (чужие хосты отбрасываются).
 */
function import_absolute_url($src)
{
    $src = trim((string) $src);
    if ($src === '') {
        return null;
    }
    if (strpos($src, '//') === 0) {
        $src = 'https:' . $src;
    }
    if (preg_match('#^https?://#i', $src)) {
        $host = (string) parse_url($src, PHP_URL_HOST);
        if ($host === '' || !preg_match('#(^|\.)profkomntmk\.ru$#i', $host)) {
            return null;
        }
        $path = parse_url($src, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            return null;
        }
        return IMPORT_SOURCE_BASE . (string) $path;
    }
    if (strpos($src, 'data:') === 0 || strpos($src, 'javascript:') === 0) {
        return null;
    }
    return IMPORT_SOURCE_BASE . '/' . ltrim($src, '/');
}

/**
 * Картинки блока статьи в порядке появления (первые из /images/).
 */
function import_extract_image_paths($contentHtml)
{
    $out = array();
    if (!preg_match_all('/<img\b[^>]*>/i', $contentHtml, $m)) {
        return $out;
    }
    foreach ($m[0] as $tag) {
        if (!preg_match('/\bsrc=(["\'])(.*?)\1/i', $tag, $sm)) {
            continue;
        }
        $abs = import_absolute_url($sm[2]);
        if ($abs === null) {
            continue;
        }
        $path = (string) parse_url($abs, PHP_URL_PATH);
        if (strpos($path, '/images/') !== 0) {
            continue; // счётчики, иконки шаблона и прочее не берём
        }
        if (in_array($abs, $out, true)) {
            continue;
        }
        $out[] = $abs;
    }
    return $out;
}

/**
 * HTML блока статьи → простой текст: абзацы через пустую строку,
 * без тегов и без картинок (как хранит news.php и как рендерит сайт).
 */
function import_html_to_text($html)
{
    $s = (string) $html;
    $s = preg_replace('/<img\b[^>]*>/i', '', $s);
    $s = preg_replace('/<br\s*\/?\s*>/i', "\n", $s);
    $s = preg_replace('/<hr\b[^>]*>/i', "\n\n", $s);
    $s = preg_replace('/<\/p\s*>/i', "\n\n", $s);
    $s = preg_replace('/<p\b[^>]*>/i', '', $s);
    $s = preg_replace('/<\/(h[1-6]|li|blockquote|div|tr|ul|ol)\s*>/i', "\n\n", $s);
    $s = preg_replace('/<[^>]+>/', '', $s);
    if ($s === null) {
        return '';
    }
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    $s = str_replace("\xc2\xa0", ' ', $s);
    $blocks = preg_split('/\n[ \t\r]*\n+/', $s);
    $out = array();
    foreach ($blocks as $b) {
        $t = preg_replace('/\s+/u', ' ', $b);
        if ($t === null) {
            $t = preg_replace('/\s+/', ' ', (string) $b);
        }
        $t = trim($t);
        if ($t !== '') {
            $out[] = $t;
        }
    }
    return implode("\n\n", $out);
}

/**
 * Краткое описание: первый абзац, не длиннее 220 символов.
 */
function import_make_excerpt($text, $len = 220)
{
    $flat = preg_replace('/\s+/u', ' ', (string) $text);
    if ($flat === null) {
        $flat = preg_replace('/\s+/', ' ', (string) $text);
    }
    return truncate(trim($flat), $len);
}

/**
 * Загрузка страницы статьи: ?print=1 (основной вариант), обычная, затем
 * ?tmpl=component. Битые ответы старого Joomla пропускаются.
 */
function import_fetch_article($path)
{
    $variants = array(
        import_build_url($path) . '?print=1',
        import_build_url($path),
        import_build_url($path) . '?tmpl=component',
    );
    $last = '';
    foreach ($variants as $u) {
        $html = import_fetch_url($u, 20);
        if ($html === false || $html === '') {
            $last = 'нет ответа';
            continue;
        }
        $html = import_ensure_utf8($html);
        if (import_looks_like_db_error($html)) {
            $last = 'ошибка БД старого сайта';
            continue;
        }
        if (strlen($html) < 400) {
            $last = 'пустой ответ';
            continue;
        }
        return $html;
    }
    throw new Exception('не удалось получить страницу (' . $last . ')');
}

/**
 * Байты картинки: локальный файл (тот же хост) → HTTP (путь percent-encoded).
 */
function import_fetch_image_bytes($absUrl)
{
    $urlPath = parse_url($absUrl, PHP_URL_PATH);
    if ($urlPath === null || $urlPath === false || $urlPath === '') {
        return false;
    }
    $decoded = rawurldecode((string) $urlPath);

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
    if ($docRoot !== '') {
        $local = rtrim($docRoot, '/\\') . $decoded;
        $real = @realpath($local);
        $root = @realpath($docRoot);
        if ($real !== false && $root !== false && is_file($real)) {
            $prefix = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
            if (strpos($real, $prefix) === 0) {
                $sz = @filesize($real);
                if ($sz > 0 && $sz <= IMPORT_MAX_IMAGE_BYTES) {
                    $b = @file_get_contents($real);
                    if ($b !== false) {
                        return $b;
                    }
                }
            }
        }
    }

    $segs = explode('/', $decoded);
    foreach ($segs as $i => $s) {
        $segs[$i] = rawurlencode($s);
    }
    return import_fetch_url(IMPORT_SOURCE_BASE . implode('/', $segs), 20);
}

/**
 * Сохранение байтов в storage/uploads с проверкой сигнатуры.
 * Возвращает путь вида 'storage/uploads/img_imp_….jpg' или null.
 */
function import_store_image_bytes($bytes)
{
    $len = strlen((string) $bytes);
    if ($len < 16 || $len > IMPORT_MAX_IMAGE_BYTES) {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'impimg');
    if ($tmp === false) {
        return null;
    }
    if (@file_put_contents($tmp, $bytes) === false) {
        @unlink($tmp);
        return null;
    }
    $mime = normalize_image_mime(detect_image_mime($tmp));
    $exts = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
    );
    if ($mime === '' || !in_array($mime, allowed_image_types()) || !isset($exts[$mime])) {
        @unlink($tmp);
        return null;
    }
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0775, true);
    }
    $name = uniqid('img_imp_', true) . '.' . $exts[$mime];
    $dest = UPLOAD_DIR . '/' . $name;
    if (!@rename($tmp, $dest)) {
        if (!@copy($tmp, $dest)) {
            @unlink($tmp);
            return null;
        }
        @unlink($tmp);
    }
    return UPLOAD_URL . '/' . $name;
}

/**
 * Скачать и сохранить одну картинку со старого сайта.
 */
function import_save_image($absUrl)
{
    $bytes = import_fetch_image_bytes($absUrl);
    if ($bytes === false || $bytes === '') {
        return null;
    }
    return import_store_image_bytes($bytes);
}

/**
 * Состояние импорта (страница-маркер __import_state, скрыта в админке).
 */
function import_get_state($pdo)
{
    try {
        $stmt = $pdo->query("SELECT content FROM pages WHERE page_key = '__import_state'");
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
    if (!$row || !isset($row['content']) || $row['content'] === null || $row['content'] === '') {
        return null;
    }
    $state = json_decode($row['content'], true);
    if (!is_array($state)) {
        return null;
    }
    if (!isset($state['sources']) || !is_array($state['sources'])) {
        $state['sources'] = array();
    }
    if (!isset($state['imported_total'])) {
        $state['imported_total'] = 0;
    }
    if (!isset($state['runs'])) {
        $state['runs'] = 0;
    }
    if (!isset($state['last_import'])) {
        $state['last_import'] = null;
    }
    return $state;
}

function import_put_state($pdo, $state)
{
    $state['runs'] = isset($state['runs']) ? ((int) $state['runs'] + 1) : 1;
    $state['last_import'] = date('Y-m-d H:i:s');
    $json = json_encode($state);
    if ($json === false) {
        throw new Exception('Не удалось сохранить состояние импорта (JSON).');
    }
    $upd = $pdo->prepare("UPDATE pages SET content = :c WHERE page_key = '__import_state'");
    $upd->execute(array(':c' => $json));
    if ($upd->rowCount() === 0) {
        $ins = $pdo->prepare("INSERT INTO pages (page_key, title, subtitle, kicker, content)
            VALUES ('__import_state', 'Импорт новостей', '', '', :c)");
        $ins->execute(array(':c' => $json));
    }
    return $state;
}

/**
 * Восстановление состояния из колонки news.source_url
 * (если страница-состояние потерялась, импортированные не задублируются).
 */
function import_recover_state($pdo)
{
    $state = array(
        'sources' => array(),
        'runs' => 0,
        'imported_total' => 0,
        'last_import' => null,
    );
    $stmt = $pdo->query('SELECT source_url FROM news WHERE source_url IS NOT NULL');
    foreach ($stmt->fetchAll() as $r) {
        $u = (string) $r['source_url'];
        if ($u !== '') {
            $state['sources'][$u] = 1;
        }
    }
    $state['imported_total'] = count($state['sources']);
    return $state;
}

function import_count_imported($pdo)
{
    $row = $pdo->query('SELECT COUNT(*) AS c FROM news WHERE source_url IS NOT NULL')->fetch();
    return isset($row['c']) ? (int) $row['c'] : 0;
}

/**
 * «Пол окна импорта» — минимальный id среди уже импортированных источников.
 * Старые статьи с меньшим id не считаются новыми: первое нажатие берёт
 * последние 20, далее — только появившиеся после этого.
 */
function import_floor_from_sources($state)
{
    $min = null;
    if (empty($state['sources']) || !is_array($state['sources'])) {
        return null;
    }
    foreach (array_keys($state['sources']) as $path) {
        if (preg_match('#^/news/(\d+)#', $path, $m)) {
            $id = (int) $m[1];
            if ($min === null || $id < $min) {
                $min = $id;
            }
        }
    }
    return $min;
}

/**
 * Удаление демо-новостей (только по списку заголовок + без source_url).
 */
function import_clear_demo_news($pdo)
{
    $titles = import_demo_titles();
    $n = 0;
    $stmt = $pdo->query('SELECT id, title FROM news WHERE source_url IS NULL');
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $low = mb_strtolower((string) $row['title'], 'UTF-8');
        if (!in_array($low, $titles, true)) {
            continue;
        }
        delete_news_gallery((int) $row['id']);
        $del = $pdo->prepare('DELETE FROM news WHERE id = :id');
        $del->execute(array(':id' => (int) $row['id']));
        $n++;
    }
    return $n;
}

/**
 * Главный прогон импорта. Возвращает отчёт:
 * array(mode, cleared, imported, skipped, errors, lines[], finished).
 * Бросает Exception при фатальной ошибке (старый сайт недоступен и т.п.).
 */
function run_news_import()
{
    @set_time_limit(0);
    @ini_set('max_execution_time', '300');

    $report = array(
        'mode' => '',
        'cleared' => 0,
        'imported' => 0,
        'skipped' => 0,
        'outside' => 0,
        'errors' => 0,
        'lines' => array(),
        'finished' => date('Y-m-d H:i:s'),
    );

    $pdo = get_db();
    $state = import_get_state($pdo);

    if ($state === null) {
        if (import_count_imported($pdo) > 0) {
            $state = import_recover_state($pdo);
            $report['mode'] = 'recovered';
            $report['lines'][] = 'Состояние импорта восстановлено по базе: источников '
                . count($state['sources']) . '.';
        } else {
            $report['mode'] = 'first';
            $report['cleared'] = import_clear_demo_news($pdo);
            if ($report['cleared'] > 0) {
                $report['lines'][] = 'Первый импорт: удалено демо-новостей — ' . $report['cleared'] . '.';
            } else {
                $report['lines'][] = 'Первый импорт: демо-новостей не найдено.';
            }
            $state = array(
                'sources' => array(),
                'runs' => 0,
                'imported_total' => 0,
                'last_import' => null,
            );
        }
    } else {
        $report['mode'] = 'incremental';
    }

    // Даты из RSS — только как запасной вариант для слагов без даты
    $rssDates = array();
    $rss = import_fetch_url(IMPORT_SOURCE_BASE . '/index.php?format=feed&type=rss', 15);
    if ($rss !== false) {
        $rssDates = import_parse_rss_dates(import_ensure_utf8($rss));
    }

    $firstRun = ($report['mode'] === 'first');
    $maxPages = $firstRun ? 6 : IMPORT_MAX_PAGES;

    // Окно импорта: floor_id — минимальный id среди импортированного.
    // Пока пол не задан (первый запуск / пустое состояние) — не выходим
    // за первые IMPORT_FIRST_COUNT статей списка.
    $floor = null;
    if (isset($state['floor_id'])) {
        $floor = (int) $state['floor_id'];
    } else {
        $floor = import_floor_from_sources($state);
    }
    $limit = ($floor === null) ? IMPORT_FIRST_COUNT : 0; // 0 — без лимита
    $furthest = isset($state['furthest']) ? (int) $state['furthest'] : 0;

    // Сбор списка ещё не импортированных статей
    $collected = array();
    $seen = 0;
    $outside = 0;
    $idx = 0;
    $natural = false;
    $maxNewIdx = 0;
    while ($idx < $maxPages) {
        $url = ($idx === 0)
            ? IMPORT_SOURCE_BASE . '/'
            : IMPORT_SOURCE_BASE . '/index.php?start=' . ($idx * IMPORT_PAGE_SIZE);
        $html = import_fetch_url($url, 20);
        if ($html === false || import_looks_like_db_error($html)) {
            if ($idx === 0) {
                throw new Exception('Старый сайт недоступен (страница списка новостей). Попробуйте позже.');
            }
            break; // сеть дрогнула — остатки догоним следующим запуском
        }
        $items = import_parse_list(import_ensure_utf8($html));
        if (empty($items)) {
            $natural = true;
            break;
        }
        $newOnPage = 0;
        foreach ($items as $item) {
            if (isset($state['sources'][$item['path']])) {
                $seen++;
                continue;
            }
            if ($floor !== null && $item['id'] < $floor) {
                $outside++;
                continue;
            }
            if (isset($collected[$item['path']])) {
                continue;
            }
            if ($limit > 0 && count($collected) >= $limit) {
                break;
            }
            $collected[$item['path']] = $item;
            $newOnPage++;
        }
        if ($newOnPage > 0) {
            $maxNewIdx = $idx;
        }
        $idx++;
        if ($limit > 0 && count($collected) >= $limit) {
            $natural = true;
            break;
        }
        if ($limit === 0 && $newOnPage === 0 && ($idx - 1) > $furthest) {
            $natural = true;
            break;
        }
    }
    $report['skipped'] = $seen;
    $report['outside'] = $outside;

    // Глубина сканирования: при штатной остановке достаточно пройденной
    // глубины, при обрыве по лимиту страниц сохраняем прежнюю — статей ниже
    // уже импортированного блока (остатки старого лимита) достанем позже.
    if ($firstRun || $limit > 0) {
        $state['furthest'] = 0;
    } elseif ($natural) {
        $state['furthest'] = $maxNewIdx;
    } else {
        $state['furthest'] = max($maxNewIdx, $furthest);
    }

    // Первый запуск фиксирует окно: дальше «новыми» считаются статьи
    // с id не меньше минимального из импортированных.
    if ($firstRun && !empty($collected)) {
        $minId = null;
        foreach ($collected as $item) {
            if ($minId === null || $item['id'] < $minId) {
                $minId = $item['id'];
            }
        }
        if ($minId !== null) {
            $state['floor_id'] = $minId;
        }
    }

    if (empty($collected)) {
        $report['lines'][] = 'Новых статей для импорта не найдено.';
    }
    if ($outside > 0) {
        $report['lines'][] = 'Старее окна импорта пропущено: ' . $outside . '.';
    }

    // Обработка: свежие статьи идут первыми (порядок списка),
    // $cursor хранит дату предыдущего элемента — для статей без даты.
    $cursor = null;
    foreach ($collected as $item) {
        $date = null;
        try {
            // Дедуп и по состоянию, и по базе
            $dup = $pdo->prepare('SELECT id FROM news WHERE source_url = :u');
            $dup->execute(array(':u' => $item['path']));
            if ($dup->fetch()) {
                $state['sources'][$item['path']] = 1;
                $report['skipped']++;
                continue;
            }

            $date = import_date_from_path($item['path']);
            if ($date === null && isset($rssDates[$item['path']])) {
                $date = $rssDates[$item['path']];
            }
            if ($date === null && $cursor !== null) {
                $date = date('Y-m-d H:i:s', strtotime($cursor) - 60);
                $report['lines'][] = '• ' . $item['id'] . ': дата в источнике не найдена — '
                    . 'назначена ориентировочно ' . $date . '.';
            }
            if ($date !== null) {
                $cursor = $date;
            }

            $artHtml = import_fetch_article($item['path']);
            $region = import_split_article($artHtml);
            if ($region === null || trim($region) === '') {
                throw new Exception('не удалось выделить текст статьи');
            }
            $text = import_html_to_text($region);
            if ($text === '') {
                throw new Exception('текст статьи пуст');
            }

            // Картинки: первая — обложка, остальные — в галерею; из текста уходят
            $image = null;
            $gallery = array();
            $imgFail = 0;
            foreach (import_extract_image_paths($region) as $src) {
                $saved = import_save_image($src);
                if ($saved === null) {
                    $imgFail++;
                    continue;
                }
                if ($image === null) {
                    $image = $saved;
                } elseif (count($gallery) < IMPORT_MAX_GALLERY) {
                    $gallery[] = $saved;
                }
            }

            $title = import_article_title($artHtml, $item['title']);
            if ($title === '') {
                $title = 'Новость ' . $item['id'];
            }

            $ins = $pdo->prepare("INSERT INTO news (title, slug, excerpt, content, image, is_published, published_at, source_url)
                VALUES (:title, :slug, :excerpt, :content, :image, 1, :date, :src)");
            $ins->execute(array(
                ':title' => $title,
                ':slug' => slugify($title),
                ':excerpt' => import_make_excerpt($text),
                ':content' => $text,
                ':image' => $image,
                ':date' => $date,
                ':src' => $item['path'],
            ));
            $newId = (int) $pdo->lastInsertId();
            if (!empty($gallery)) {
                $g = $pdo->prepare('INSERT INTO news_gallery (news_id, image, sort_order) VALUES (:n, :i, :s)');
                foreach ($gallery as $gi => $gp) {
                    $g->execute(array(':n' => $newId, ':i' => $gp, ':s' => $gi));
                }
            }

            $state['sources'][$item['path']] = 1;
            $state['imported_total'] = isset($state['imported_total'])
                ? ((int) $state['imported_total'] + 1) : 1;
            $report['imported']++;

            $line = '✓ ' . $item['id'] . ' — ' . truncate($title, 60)
                . ' (фото: ' . ($image !== null ? '1' : '0');
            if (!empty($gallery)) {
                $line .= ', галерея: ' . count($gallery);
            }
            $line .= ')';
            if ($imgFail > 0) {
                $line .= ' [не скачалось картинок: ' . $imgFail . ']';
            }
            $report['lines'][] = $line;
        } catch (Exception $ex) {
            $report['errors']++;
            $report['lines'][] = '✗ ' . $item['id'] . ' — ' . $ex->getMessage();
            if ($date !== null) {
                $cursor = $date;
            }
        }
    }

    import_put_state($pdo, $state);
    $report['lines'][] = 'Итог: добавлено ' . $report['imported']
        . ', пропущено (уже были) ' . $report['skipped']
        . ', ошибок ' . $report['errors'] . '.';

    return $report;
}
