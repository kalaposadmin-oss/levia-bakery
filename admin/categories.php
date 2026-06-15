<?php

require __DIR__ . '/../lib/db.php';
require_admin();

$edit = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$categories = db()->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
$title = 'Kategori Produk';
ob_start();
?>
<section class="card form-card">
  <h3><?= $edit ? 'Edit Kategori' : 'Tambah Kategori' ?></h3>
  <form method="post" action="category-save.php">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e((string) ($edit['id'] ?? 0)) ?>">
    <div class="form-grid">
      <label>Nama Kategori <input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
      <label>Slug <input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="auto jika kosong"></label>
      <label>Icon <input name="icon" value="<?= e($edit['icon'] ?? 'bread') ?>" placeholder="bread / croissant / tag"></label>
      <label>Urutan <input type="number" name="sort_order" min="0" value="<?= e((string) ($edit['sort_order'] ?? 0)) ?>"></label>
    </div>
    <div class="checks"><label><input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Aktif</label></div>
    <button class="primary-btn" type="submit">Simpan Kategori</button>
    <?php if ($edit): ?><a class="secondary-btn" href="categories.php">Batal</a><?php endif; ?>
  </form>
</section>
<section class="card">
  <h3>Daftar Kategori</h3>
  <table>
    <thead><tr><th>Nama</th><th>Slug</th><th>Icon</th><th>Urutan</th><th>Aktif</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($categories as $category): ?>
        <tr>
          <td><?= e($category['name']) ?></td>
          <td><?= e($category['slug']) ?></td>
          <td><?= e($category['icon']) ?></td>
          <td><?= e((string) $category['sort_order']) ?></td>
          <td><?= $category['is_active'] ? 'Ya' : 'Tidak' ?></td>
          <td class="actions">
            <a href="categories.php?edit=<?= e((string) $category['id']) ?>">Edit</a>
            <form method="post" action="category-delete.php" onsubmit="return confirm('Hapus kategori ini? Produk akan menjadi tanpa kategori.');">
              <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
              <button type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
