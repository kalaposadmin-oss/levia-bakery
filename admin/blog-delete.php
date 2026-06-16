<?php

require __DIR__ . '/../lib/db.php';
require_admin();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    $stmt = db()->prepare('DELETE FROM blogs WHERE id = ?');
    $stmt->execute([$id]);
    $_SESSION['flash'] = 'Blog berhasil dihapus.';
}

redirect('admin/blogs.php');
