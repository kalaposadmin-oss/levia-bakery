<?php

require __DIR__ . '/../lib/db.php';
require_admin();

$stockStates = [
    'ready' => ['label' => 'Ready', 'limit' => 999999],
    'limited' => ['label' => 'Limited', 'limit' => 5],
    'sold_out' => ['label' => 'Sold Out', 'limit' => 0],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $productId = (int) ($_POST['product_id'] ?? 0);
    $type = (string) ($_POST['type'] ?? '');
    $qty = max(1, (int) ($_POST['qty'] ?? 1));
    $note = trim((string) ($_POST['note'] ?? ''));

    $stmt = db()->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['flash'] = 'Produk tidak ditemukan.';
        redirect('admin/stock.php');
    }

    if ($type === 'status') {
        $status = (string) ($_POST['stock_status'] ?? 'ready');
        $newStock = (int) ($_POST['new_stock'] ?? 0);
        if (!array_key_exists($status, $stockStates)) {
            $_SESSION['flash'] = 'Status stok tidak valid.';
            redirect('admin/stock.php');
        }

        $newStock = max(0, $newStock);
        if ($status === 'sold_out') {
            $newStock = 0;
        } elseif ($status === 'limited' && $newStock > 5) {
            $newStock = 5;
        }

        db()->prepare('UPDATE products SET stock = ?, stock_status = ? WHERE id = ?')->execute([$newStock, $status, $productId]);
        db()->prepare('INSERT INTO stock_movements (product_id, type, qty, note) VALUES (?, ?, ?, ?)')->execute([$productId, 'adjustment', $newStock, $note ?: 'Set status stok: ' . $status]);
        $_SESSION['flash'] = 'Status stok diperbarui.';
        redirect('admin/stock.php');
    }

    if (!in_array($type, ['in', 'out', 'adjustment'], true)) {
        $_SESSION['flash'] = 'Tipe stok tidak valid.';
        redirect('admin/stock.php');
    }

    $delta = $type === 'out' ? -$qty : $qty;
    db()->prepare('UPDATE products SET stock = GREATEST(stock + ?, 0), stock_status = CASE WHEN GREATEST(stock + ?, 0) <= 0 THEN "sold_out" WHEN GREATEST(stock + ?, 0) <= 5 THEN "limited" ELSE "ready" END WHERE id = ?')->execute([$delta, $delta, $delta, $productId]);
    db()->prepare('INSERT INTO stock_movements (product_id, type, qty, note) VALUES (?, ?, ?, ?)')->execute([$productId, $type, $qty, $note]);
    $_SESSION['flash'] = 'Stok berhasil disesuaikan.';
    redirect('admin/stock.php');
}

$products = db()->query('SELECT * FROM products ORDER BY name')->fetchAll();
$movements = db()->query('SELECT sm.*, p.name product_name FROM stock_movements sm JOIN products p ON p.id = sm.product_id ORDER BY sm.id DESC LIMIT 30')->fetchAll();
$title = 'Penyesuaian Stok';
ob_start();
?>
<div class="page-stack">
  <section class="page-hero" data-watermark="STOK HARI INI">
    <div class="page-hero-inner">
      <div>
        <h3>Penyesuaian Stok</h3>
        <p>Atur stok cepat untuk produksi harian, dan ubah status menu tanpa buka form produk satu per satu.</p>
      </div>
    </div>
  </section>

  <section class="card form-card">
    <div class="card-head"><h3>Kontrol Cepat</h3></div>
    <div class="card-content">
      <form method="post" class="form-grid">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <label>Produk
          <select name="product_id" required>
            <?php foreach ($products as $product): ?>
              <option value="<?= e((string) $product['id']) ?>"><?= e($product['name']) ?> - stok <?= e((string) $product['stock']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Status stok
          <select name="stock_status">
            <option value="ready">Ready</option>
            <option value="limited">Limited</option>
            <option value="sold_out">Sold Out</option>
          </select>
        </label>
        <label>Stok baru <input type="number" name="new_stock" min="0" value="0"></label>
        <label>Catatan <input name="note" placeholder="Produksi pagi / habis cepat"></label>
        <div class="form-actions" style="grid-column:1/-1;">
          <input type="hidden" name="type" value="status">
          <button class="primary-btn" type="submit">Simpan Status</button>
        </div>
      </form>
    </div>
  </section>

  <section class="card form-card">
    <div class="card-head"><h3>Pergerakan Stok</h3></div>
    <div class="card-content">
      <form method="post" class="form-grid">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <label>Produk
          <select name="product_id" required>
            <?php foreach ($products as $product): ?>
              <option value="<?= e((string) $product['id']) ?>"><?= e($product['name']) ?> - stok <?= e((string) $product['stock']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Tipe
          <select name="type">
            <option value="in">Stok Masuk</option>
            <option value="out">Stok Keluar</option>
            <option value="adjustment">Adjustment Tambah</option>
          </select>
        </label>
        <label>Qty <input type="number" name="qty" min="1" value="1" required></label>
        <label>Catatan <input name="note" placeholder="Produksi pagi / koreksi stok"></label>
        <div class="form-actions" style="grid-column:1/-1;">
          <button class="secondary-btn" type="submit">Simpan Pergerakan</button>
        </div>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h3>Riwayat Stok</h3></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Produk</th><th>Tipe</th><th>Qty</th><th>Catatan</th><th>Tanggal</th></tr></thead>
        <tbody>
          <?php foreach ($movements as $move): ?>
            <tr><td><?= e($move['product_name']) ?></td><td><?= e($move['type']) ?></td><td><?= e((string) $move['qty']) ?></td><td><?= e($move['note']) ?></td><td><?= e($move['created_at']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
