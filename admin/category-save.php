<?php

require __DIR__ . '/../lib/db.php';
require_admin();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$slug = trim((string) ($_POST['slug'] ?? ''));
$icon = trim((string) ($_POST['icon'] ?? 'bread'));
$sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($name === '') {
    $_SESSION['flash'] = 'Nama kategori wajib diisi.';
    redirect($id ? 'admin/categories.php?edit=' . $id : 'admin/categories.php');
}

$slug = $slug !== '' ? slugify($slug) : slugify($name);

$stmt = db()->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ? LIMIT 1');
$stmt->execute([$slug, $id]);
if ($stmt->fetchColumn()) {
    $_SESSION['flash'] = 'Slug kategori sudah dipakai.';
    redirect($id ? 'admin/categories.php?edit=' . $id : 'admin/categories.php');
}

$data = [$name, $slug, $icon !== '' ? $icon : 'bread', $sortOrder, $isActive];

if ($id) {
    $data[] = $id;
    db()->prepare('UPDATE categories SET name=?, slug=?, icon=?, sort_order=?, is_active=? WHERE id=?')->execute($data);
} else {
    db()->prepare('INSERT INTO categories (name, slug, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?)')->execute($data);
}

$_SESSION['flash'] = 'Kategori berhasil disimpan.';
redirect('admin/categories.php');
