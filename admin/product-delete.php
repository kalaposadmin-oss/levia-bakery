<?php

require __DIR__ . '/../lib/db.php';
require_admin();
verify_csrf();

$stmt = db()->prepare('DELETE FROM products WHERE id = ?');
$stmt->execute([(int) ($_POST['id'] ?? 0)]);
$_SESSION['flash'] = 'Produk berhasil dihapus.';
redirect('admin/products.php');
