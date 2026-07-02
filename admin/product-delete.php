<?php

require __DIR__ . '/../lib/db.php';
require_admin();
verify_csrf();

$stmt = db()->prepare('DELETE FROM products WHERE id = ?');
$stmt->execute([(int) ($_POST['id'] ?? 0)]);
clear_storefront_cache();
$_SESSION['flash'] = 'Produk berhasil dihapus.';
redirect('admin/products.php');
