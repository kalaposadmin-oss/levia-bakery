<?php

require __DIR__ . '/../lib/db.php';
require_admin();

$availabilityStates = [
    'ready' => ['label' => 'On bebas', 'hint' => 'Bisa request jumlah bebas'],
    'limited' => ['label' => 'On maksimal', 'hint' => 'Bisa request sampai batas maksimal'],
    'sold_out' => ['label' => 'Off / habis', 'hint' => 'Tampil di katalog, tidak bisa direquest'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $rows = (array) ($_POST['availability'] ?? []);
    $existing = db()->query('SELECT id, stock_status, stock FROM products')->fetchAll(PDO::FETCH_UNIQUE);
    $updated = 0;

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $updateStmt = $pdo->prepare('UPDATE products SET stock_status = ?, stock = ? WHERE id = ?');
        $movementStmt = $pdo->prepare('INSERT INTO stock_movements (product_id, type, qty, note) VALUES (?, ?, ?, ?)');

        foreach ($rows as $productId => $row) {
            $productId = (int) $productId;
            if (!isset($existing[$productId]) || !is_array($row)) {
                continue;
            }

            $status = (string) ($row['stock_status'] ?? 'ready');
            $requestLimit = max(0, (int) ($row['request_limit'] ?? 0));

            if (!array_key_exists($status, $availabilityStates)) {
                throw new RuntimeException('Status ketersediaan tidak valid.');
            }

            if ($status === 'ready' || $status === 'sold_out') {
                $requestLimit = 0;
            }

            if ($status === 'limited' && $requestLimit < 1) {
                throw new RuntimeException('Batas maksimal wajib diisi minimal 1 untuk status On maksimal.');
            }

            $currentStatus = (string) ($existing[$productId]['stock_status'] ?? 'ready');
            $currentLimit = (int) ($existing[$productId]['stock'] ?? 0);
            if ($currentStatus === $status && $currentLimit === $requestLimit) {
                continue;
            }

            $updateStmt->execute([$status, $requestLimit, $productId]);
            $movementStmt->execute([$productId, 'adjustment', $requestLimit, 'Set ketersediaan katalog: ' . $availabilityStates[$status]['label']]);
            $updated++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = $e->getMessage();
        redirect('admin/stock.php');
    }

    $_SESSION['flash'] = $updated > 0 ? $updated . ' perubahan ketersediaan disimpan.' : 'Tidak ada perubahan ketersediaan.';
    redirect('admin/stock.php');
}

$products = db()->query('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY c.sort_order, p.name')->fetchAll();
$title = 'Ketersediaan Harian';
ob_start();
?>
<div class="page-stack">
  <section class="page-hero" data-watermark="DAILY CATALOG">
    <div class="page-hero-inner">
      <div>
        <h3>Ketersediaan Harian</h3>
        <p>Atur produk yang bisa direquest hari ini. Angka maksimal hanya batas request web, bukan stok kasir asli.</p>
      </div>
    </div>
  </section>

  <section class="card">
    <form method="post" id="availabilityForm" data-availability-form>
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <div class="card-head">
        <h3>Aturan Request per Produk</h3>
        <button class="primary-btn" type="submit" data-save-availability disabled>Simpan Perubahan</button>
      </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produk</th>
            <th>Kategori</th>
            <th>Status Web</th>
            <th>Maksimal Request</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $product): ?>
            <?php $status = $product['stock_status'] ?: 'ready'; ?>
            <tr>
              <td class="product-cell">
                <img src="../<?= e($product['image'] ?: 'assets/almond-croissant.png') ?>" alt="">
                <span><?= e($product['name']) ?></span>
              </td>
              <td><?= e($product['category_name'] ?? '-') ?></td>
              <td>
                  <select name="availability[<?= e((string) $product['id']) ?>][stock_status]" data-availability-select>
                    <?php foreach ($availabilityStates as $value => $state): ?>
                      <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($state['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
              </td>
              <td>
                <input type="number" name="availability[<?= e((string) $product['id']) ?>][request_limit]" min="0" value="<?= e((string) (int) $product['stock']) ?>" data-request-limit <?= $status === 'limited' ? '' : 'disabled' ?>>
              </td>
              <td>
                <span class="helper-text" data-availability-hint><?= e($availabilityStates[$status]['hint'] ?? $availabilityStates['ready']['hint']) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </form>
  </section>
</div>

<script>
  const availabilityHints = {
    ready: 'Bisa request jumlah bebas',
    limited: 'Bisa request sampai batas maksimal',
    sold_out: 'Tampil di katalog, tidak bisa direquest'
  };

  const form = document.querySelector('[data-availability-form]');
  const saveButton = document.querySelector('[data-save-availability]');
  let isDirty = false;

  function setDirty(value) {
    isDirty = value;
    if (saveButton) saveButton.disabled = !value;
  }

  document.querySelectorAll('[data-availability-select]').forEach((select) => {
    const row = select.closest('tr');
    const limitInput = row?.querySelector('[data-request-limit]');
    const hint = row?.querySelector('[data-availability-hint]');

    function syncAvailabilityRow() {
      const isLimited = select.value === 'limited';
      if (limitInput) {
        limitInput.disabled = !isLimited;
        if (!isLimited) limitInput.value = '0';
        if (isLimited && Number(limitInput.value || 0) < 1) limitInput.value = '1';
      }
      if (hint) hint.textContent = availabilityHints[select.value] || availabilityHints.ready;
    }

    select.addEventListener('change', syncAvailabilityRow);
    syncAvailabilityRow();
  });

  if (form) {
    form.querySelectorAll('select, input').forEach((field) => {
      field.addEventListener('change', () => setDirty(true));
      field.addEventListener('input', () => setDirty(true));
    });

    form.addEventListener('submit', () => setDirty(false));
  }

  document.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', (event) => {
      if (!isDirty) return;
      const ok = window.confirm('Ada perubahan ketersediaan yang belum disimpan. Tinggalkan halaman tanpa menyimpan?');
      if (!ok) event.preventDefault();
    });
  });

  window.addEventListener('beforeunload', (event) => {
    if (!isDirty) return;
    event.preventDefault();
    event.returnValue = '';
  });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
