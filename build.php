<?php
/**
 * Диагностика деплоя: показывает, какая версия files реально на сервере.
 * После успешного обновления удалите этот файл.
 */
require_once dirname(__FILE__) . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$functionsPath = dirname(__FILE__) . '/includes/functions.php';
$configPath = dirname(__FILE__) . '/includes/config.php';

echo "=== Диагностика profkom ===\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Дата сборки (ожидается): 2026.10.16.3\n\n";

echo "includes/functions.php:\n";
echo "  mtime:  " . date('Y-m-d H:i:s', filemtime($functionsPath)) . "\n";
echo "  size:   " . filesize($functionsPath) . " bytes\n";
echo "  md5:    " . md5_file($functionsPath) . "\n";
echo "  APP_BUILD: " . (defined('APP_BUILD') ? APP_BUILD : 'НЕ ОПРЕДЕЛЕНА => версия файла УСТАРЕЛА') . "\n\n";

echo "includes/config.php:\n";
echo "  mtime:  " . date('Y-m-d H:i:s', filemtime($configPath)) . "\n";
echo "  md5:    " . md5_file($configPath) . "\n";
echo "  ALLOWED_IMAGE_TYPES: ";
if (defined('ALLOWED_IMAGE_TYPES') && is_array(ALLOWED_IMAGE_TYPES)) {
    echo implode(', ', ALLOWED_IMAGE_TYPES) . "\n";
} else {
    echo "НЕ МАССИВ => " . var_export(defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : null, true) . "\n";
}
echo "\nБазовый URL: '" . base_url() . "'\n";
echo "Загрузки доступны на запись: " . (is_writable(dirname(__FILE__) . '/storage/uploads') ? 'да' : 'НЕТ') . "\n";
