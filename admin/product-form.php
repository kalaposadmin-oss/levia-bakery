<?php

require __DIR__ . '/../lib/db.php';

$currentAdmin = require_admin();

$id = (int) ($_GET['id'] ?? 0);
$product = [
    'id' => 0, 'category_id' => '', 'name' => '', 'slug' => '', 'description' => '',
    'ingredients' => '', 'package_info' => '', 'shelf_life' => '',
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

function ingredient_rows(?string $value): array
{
    $value = trim((string) $value);
    if ($value === '') {
        return [''];
    }

    $separator = str_contains($value, "\n") ? '/\r?\n/' : '/\s*,\s*/';
    $items = preg_split($separator, $value) ?: [];
    $items = array_values(array_filter(array_map('trim', $items), fn($item) => $item !== ''));

    return $items ?: [''];
}

$ingredientRows = ingredient_rows($product['ingredients'] ?? '');
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
      <label>Indikator jumlah <input type="number" name="stock" min="0" value="<?= e((string) $product['stock']) ?>" required></label>
      <label>Status katalog
        <select name="stock_status">
          <?php foreach (['ready' => 'Ready', 'limited' => 'Terbatas', 'sold_out' => 'Habis'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $product['stock_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Gambar <input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label>
      <label>Isi / berat <input name="package_info" value="<?= e($product['package_info'] ?? '') ?>" placeholder="Contoh: 6 pcs / 250 gram"></label>
      <label>Masa simpan <input name="shelf_life" value="<?= e($product['shelf_life'] ?? '') ?>" placeholder="Contoh: 2 hari suhu ruang"></label>
    </div>
    <label>Deskripsi <textarea name="description" rows="4"><?= e($product['description']) ?></textarea></label>
    <div class="ingredient-builder">
      <div class="ingredient-head">
        <span class="field-label">Ingredient</span>
        <button type="button" class="secondary-btn" id="addIngredient">Tambah Ingredient</button>
      </div>
      <div class="ingredient-list" id="ingredientList">
        <?php foreach ($ingredientRows as $ingredient): ?>
          <div class="ingredient-row">
            <input name="ingredients[]" value="<?= e($ingredient) ?>" placeholder="Contoh: Tepung terigu">
            <button type="button" class="ghost-btn remove-ingredient">Hapus</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="checks">
      <label><input type="checkbox" name="is_popular" value="1" <?= $product['is_popular'] ? 'checked' : '' ?>> Produk populer</label>
      <label><input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>> Aktif tampil di storefront</label>
    </div>
    <button class="primary-btn" type="submit">Simpan Produk</button>
    <a class="secondary-btn" href="products.php">Batal</a>
  </form>
</section>

<template id="ingredientTemplate">
  <div class="ingredient-row">
    <input name="ingredients[]" placeholder="Contoh: Butter">
    <button type="button" class="ghost-btn remove-ingredient">Hapus</button>
  </div>
</template>

<script>
  const ingredientList = document.getElementById('ingredientList');
  const addIngredient = document.getElementById('addIngredient');
  const ingredientTemplate = document.getElementById('ingredientTemplate');

  function bindIngredientRow(row) {
    const removeButton = row.querySelector('.remove-ingredient');
    if (!removeButton) return;
    removeButton.addEventListener('click', () => {
      if (ingredientList.querySelectorAll('.ingredient-row').length > 1) {
        row.remove();
        return;
      }

      const input = row.querySelector('input');
      if (input) input.value = '';
    });
  }

  ingredientList.querySelectorAll('.ingredient-row').forEach(bindIngredientRow);
  addIngredient.addEventListener('click', () => {
    const row = ingredientTemplate.content.firstElementChild.cloneNode(true);
    ingredientList.appendChild(row);
    bindIngredientRow(row);
    row.querySelector('input').focus();
  });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';



