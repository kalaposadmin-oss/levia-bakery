<?php

require __DIR__ . '/../lib/db.php';

$currentAdmin = require_admin();

$products = db()->query('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC')->fetchAll();
$title = 'Katalog Produk';

function stock_badge_label(array $product): string
{
    if (($product['stock_status'] ?? '') === 'sold_out') {
        return 'Habis';
    }
    if (($product['stock_status'] ?? '') === 'limited') {
        return 'Terbatas';
    }
    return 'Ready';
}

function stock_badge_class(array $product): string
{
    if (($product['stock_status'] ?? '') === 'sold_out') {
        return 'badge sold-out';
    }
    if (($product['stock_status'] ?? '') === 'limited') {
        return 'badge limited';
    }
    return 'badge ready';
}

function request_limit_label(array $product): string
{
    if (($product['stock_status'] ?? '') === 'limited') {
        return 'Maks ' . (int) ($product['stock'] ?? 0) . ' pcs';
    }

    if (($product['stock_status'] ?? '') === 'sold_out') {
        return '-';
    }

    return 'Bebas';
}

ob_start();
?>
<section class="card">
  <div class="card-head"><h3>Daftar Menu Katalog</h3><a class="primary-btn" href="product-form.php">Tambah Produk</a></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Batas Request</th><th>Status Web</th><th>Aktif</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($products as $product): ?>
          <tr>
            <td class="product-cell"><img src="../<?= e($product['image'] ?: 'assets/almond-croissant.png') ?>" alt=""><span><?= e($product['name']) ?></span></td>
            <td><?= e($product['category_name']) ?></td>
            <td><?= money($product['price']) ?></td>
            <td><?= e(request_limit_label($product)) ?></td>
            <td><span class="<?= e(stock_badge_class($product)) ?>"><?= e(stock_badge_label($product)) ?></span></td>
            <td><?= $product['is_active'] ? 'Ya' : 'Tidak' ?></td>
            <td class="actions">
              <a href="product-form.php?id=<?= e((string) $product['id']) ?>">Edit</a>
              <form method="post" action="product-delete.php" onsubmit="return confirm('Hapus produk ini?')">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                <button type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
