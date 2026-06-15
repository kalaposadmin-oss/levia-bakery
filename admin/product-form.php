<?php

require __DIR__ . '/../lib/db.php';

$currentAdmin = require_admin();

$id = (int) ($_GET['id'] ?? 0);
$product = [
    'id' => 0, 'category_id' => '', 'name' => '', 'slug' => '', 'description' => '',
    'price' => 0, 'stock' => 0, 'stock_status' => 'ready', 'image' => '',
    'is_popular' => 0, 'is_active' => 1,
];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch() ?: $product;
}
$categories = db()->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
$title = $id ? 'Edit Produk' : 'Tambah Produk';
ob_start();
?>
<section class="card form-card">
  <form method="post" action="product-save.php" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
    <input type="hidden" name="current_image" value="<?= e($product['image']) ?>">
    <div class="form-grid">
      <label>Nama Produk <input name="name" value="<?= e($product['name']) ?>" required></label>
      <label>Kategori
        <select name="category_id">
          <option value="">Tanpa kategori</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e((string) $category['id']) ?>" <?= (int) $product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Harga <input type="number" name="price" min="0" value="<?= e((string) (int) $product['price']) ?>" required></label>
      <label>Stok <input type="number" name="stock" min="0" value="<?= e((string) $product['stock']) ?>" required></label>
      <label>Status Stok
        <select name="stock_status">
          <?php foreach (['ready' => 'Ready', 'limited' => 'Limited', 'sold_out' => 'Habis'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $product['stock_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Gambar <input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label>
    </div>
    <label>Deskripsi <textarea name="description" rows="4"><?= e($product['description']) ?></textarea></label>
    <div class="checks">
      <label><input type="checkbox" name="is_popular" value="1" <?= $product['is_popular'] ? 'checked' : '' ?>> Produk populer</label>
      <label><input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>> Aktif tampil di storefront</label>
    </div>
    <button class="primary-btn" type="submit">Simpan Produk</button>
    <a class="secondary-btn" href="products.php">Batal</a>
  </form>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';



