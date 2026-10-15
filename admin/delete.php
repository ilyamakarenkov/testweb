<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    flash('error', 'Неверный запрос.');
    redirect(url('admin/index.php'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Неверный ID.');
    redirect(url('admin/index.php'));
}

$pdo = get_db();
$stmt = $pdo->prepare("SELECT image FROM news WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if ($row) {
    delete_uploaded_image($row['image'] ?? null);
    $del = $pdo->prepare("DELETE FROM news WHERE id = :id");
    $del->execute([':id' => $id]);
    flash('success', 'Новость удалена.');
} else {
    flash('error', 'Новость не найдена.');
}
redirect(url('admin/index.php'));