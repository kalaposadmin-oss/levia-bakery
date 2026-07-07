<?php

require __DIR__ . '/../lib/db.php';
require_admin();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$allowedStockStatuses = ['ready', 'limited', 'sold_out'];
$stockStatus = (string) ($_POST['stock_status'] ?? 'ready');

function normalize_ingredients($value): string
{
    $items = is_array($value) ? $value : preg_split('/\r?\n|\s*,\s*/', (string) $value);
    $items = array_values(array_filter(array_map(fn($item) => trim((string) $item), $items ?: []), fn($item) => $item !== ''));

    return implode("\n", $items);
}

if ($name === '') {
    $_SESSION['flash'] = 'Nama produk wajib diisi.';
    redirect($id ? 'admin/product-form.php?id=' . $id : 'admin/product-form.php');
}
if (!in_array($stockStatus, $allowedStockStatuses, true)) {
    $_SESSION['flash'] = 'Status stok tidak valid.';
    redirect($id ? 'admin/product-form.php?id=' . $id : 'admin/product-form.php');
}

$baseSlug = slugify($name);
$slug = $baseSlug;
$suffix = 2;
do {
    $stmt = db()->prepare('SELECT id FROM products WHERE slug = ? AND id <> ? LIMIT 1');
    $stmt->execute([$slug, $id]);
    $exists = $stmt->fetchColumn();
    if ($exists) {
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
} while ($exists);

$image = upload_image('image', $_POST['current_image'] ?? null, ['prefix' => 'product', 'max_width' => 700, 'max_height' => 700, 'quality' => 78, 'avif_quality' => 52, 'responsive_widths' => [320, 640]]);
$data = [
    $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null,
    $name,
    $slug,
    trim((string) ($_POST['description'] ?? '')),
    normalize_ingredients($_POST['ingredients'] ?? []),
    trim((string) ($_POST['package_info'] ?? '')),
    trim((string) ($_POST['shelf_life'] ?? '')),
    max(0, (float) ($_POST['price'] ?? 0)),
    max(0, (int) ($_POST['stock'] ?? 0)),
    $stockStatus,
    $image,
    isset($_POST['is_popular']) ? 1 : 0,
    isset($_POST['is_active']) ? 1 : 0,
];

if ($id) {
    $data[] = $id;
    db()->prepare('UPDATE products SET category_id=?, name=?, slug=?, description=?, ingredients=?, package_info=?, shelf_life=?, price=?, stock=?, stock_status=?, image=?, is_popular=?, is_active=? WHERE id=?')->execute($data);
} else {
    db()->prepare('INSERT INTO products (category_id, name, slug, description, ingredients, package_info, shelf_life, price, stock, stock_status, image, is_popular, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($data);
}

clear_storefront_cache();
$_SESSION['flash'] = 'Produk berhasil disimpan.';
redirect('admin/products.php');
