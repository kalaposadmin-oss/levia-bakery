<?php

require __DIR__ . '/../lib/db.php';
require_admin();

$statusLabels = [
    'new' => 'request_wa',
    'confirmed' => 'direspons',
    'processing' => 'diskusi_wa',
    'completed' => 'selesai',
    'cancelled' => 'batal',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $allowedStatuses = ['new', 'confirmed', 'processing', 'completed', 'cancelled'];
    $status = (string) ($_POST['status'] ?? '');
    if (!in_array($status, $allowedStatuses, true)) {
        $_SESSION['flash'] = 'Status request tidak valid.';
        redirect('admin/orders.php');
    }

    db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, (int) ($_POST['id'] ?? 0)]);
    $_SESSION['flash'] = 'Status request diperbarui.';
    redirect('admin/orders.php');
}

$orders = db()->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();
$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$title = 'Request WA';
ob_start();
?>
<section class="card">
  <h3>Arsip Request WhatsApp</h3>
  <table>
    <thead><tr><th>Kode</th><th>Pelanggan</th><th>Request Menu</th><th>Estimasi</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
        <?php $itemsStmt->execute([$order['id']]); $items = $itemsStmt->fetchAll(); ?>
        <tr>
          <td><strong><?= e($order['order_code']) ?></strong><br><small><?= e($order['created_at']) ?></small></td>
          <td><?= e($order['customer_name']) ?><br><small><?= e($order['customer_phone']) ?></small><br><small><?= e($order['delivery_address']) ?></small></td>
          <td><?php foreach ($items as $item): ?><div><?= e((string) $item['qty']) ?>x <?= e($item['product_name']) ?></div><?php endforeach; ?></td>
          <td><?= money($order['total']) ?></td>
          <td><span class="pill"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span></td>
          <td>
            <form method="post" class="inline-form">
              <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= e((string) $order['id']) ?>">
              <select name="status">
                <?php foreach ($statusLabels as $status => $label): ?>
                  <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit">Update</button>
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
