<?php

require __DIR__ . '/../lib/db.php';
require_admin();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
if (!$stmt->fetchColumn()) {
    $_SESSION['flash'] = 'Kategori tidak ditemukan.';
    redirect('admin/categories.php');
}

$productsStmt = db()->prepare('UPDATE products SET category_id = NULL WHERE category_id = ?');
$productsStmt->execute([$id]);

db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
clear_storefront_cache();
$_SESSION['flash'] = 'Kategori berhasil dihapus.';
redirect('admin/categories.php');
