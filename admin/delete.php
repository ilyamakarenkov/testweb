<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
require_login();

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    flash('error', 'Неверный запрос.');
    redirect(url('admin/index.php'));
}

$id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
if ($id <= 0) {
    flash('error', 'Неверный ID.');
    redirect(url('admin/index.php'));
}

$pdo = get_db();
$stmt = $pdo->prepare("SELECT image FROM news WHERE id = :id");
$stmt->execute(array(':id' => $id));
$row = $stmt->fetch();
if ($row) {
    delete_uploaded_image(isset($row['image']) ? $row['image'] : null);
    delete_news_gallery($id);
    $del = $pdo->prepare("DELETE FROM news WHERE id = :id");
    $del->execute(array(':id' => $id));
    flash('success', 'Новость удалена.');
} else {
    flash('error', 'Новость не найдена.');
}
redirect(url('admin/index.php'));