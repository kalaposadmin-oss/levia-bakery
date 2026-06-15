<?php

require __DIR__ . '/../lib/db.php';

$currentAdmin = require_admin();

$stats = [
    'orders' => (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'revenue' => (float) db()->query('SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != "cancelled"')->fetchColumn(),
    'products' => (int) db()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'low_stock' => (int) db()->query('SELECT COUNT(*) FROM products WHERE stock <= 5')->fetchColumn(),
];
$orders = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$title = 'Dashboard';
ob_start();
?>
<div class="page-stack">
  <section class="page-hero" data-watermark="BO">
    <div class="page-hero-inner">
      <div>
        <div class="metric-label">Ringkasan Harian</div>
        <h3>Dashboard Ringkasan</h3>
        <p>Pantau performa toko, pesanan terbaru, dan status operasional dari satu tempat yang rapi.</p>
      </div>
    </div>
  </section>

  <section class="stats-grid">
    <article class="stat-card">
      <div class="stat-card-head">
        <div class="metric-label">Pesanan</div>
        <span class="material-symbols-outlined metric-icon">shopping_bag</span>
      </div>
      <div class="metric-value"><?= e((string) $stats['orders']) ?></div>
      <div class="metric-foot"><span class="material-symbols-outlined">trending_flat</span> Status pesanan masuk hari ini</div>
    </article>
    <article class="stat-card">
      <div class="stat-card-head">
        <div class="metric-label">Omzet</div>
        <span class="material-symbols-outlined metric-icon">payments</span>
      </div>
      <div class="metric-value"><?= money($stats['revenue']) ?></div>
      <div class="metric-foot"><span class="material-symbols-outlined">analytics</span> Total pendapatan tervalidasi</div>
    </article>
    <article class="stat-card">
      <div class="stat-card-head">
        <div class="metric-label">Produk</div>
        <span class="material-symbols-outlined metric-icon">inventory</span>
      </div>
      <div class="metric-value"><?= e((string) $stats['products']) ?></div>
      <div class="metric-foot"><span class="material-symbols-outlined">check_circle</span> Katalog aktif di storefront</div>
    </article>
    <article class="stat-card">
      <div class="stat-card-head">
        <div class="metric-label">Stok Rendah</div>
        <span class="material-symbols-outlined metric-icon">warning</span>
      </div>
      <div class="metric-value is-danger"><?= e((string) $stats['low_stock']) ?></div>
      <div class="metric-foot"><span class="material-symbols-outlined">notifications_active</span> Segera perbarui persediaan</div>
    </article>
  </section>

  <section class="card">
    <div class="card-head">
      <h3>Pesanan Terbaru</h3>
      <a class="ghost-btn" href="orders.php">Lihat semua <span class="material-symbols-outlined">arrow_forward</span></a>
    </div>

    <?php if (!$orders): ?>
      <div class="empty-state">
        <div class="empty-state-illustration"><span class="material-symbols-outlined">inbox</span></div>
        <div>
          <h4>Belum Ada Pesanan</h4>
          <p>Pesanan baru akan muncul secara otomatis di sini saat pelanggan checkout dari storefront.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Kode</th>
              <th>Pelanggan</th>
              <th>Status</th>
              <th>Total</th>
              <th>Tanggal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <tr>
                <td><strong><?= e($order['order_code']) ?></strong></td>
                <td><?= e($order['customer_name']) ?><br><span class="helper-text"><?= e($order['customer_phone']) ?></span></td>
                <td><span class="status-pill <?= e($order['status']) ?>"><?= e($order['status']) ?></span></td>
                <td><?= money($order['total']) ?></td>
                <td><?= e($order['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="overview-grid">
    <article class="cta-card">
      <h4>Siap untuk memproses pesanan?</h4>
      <p>Pantau dashboard ini setiap hari untuk melihat performa toko, menjaga ketersediaan stok, dan merespons order lebih cepat.</p>
      <div class="action-group">
        <a class="secondary-btn" href="products.php">Kelola Produk</a>
        <a class="ghost-btn" href="settings.php">Setting Toko</a>
      </div>
    </article>

    <aside class="status-card">
      <small>Sistem Status</small>
      <div class="status-row" style="margin-top: 12px;">
        <span class="dot-live"></span>
        <strong style="margin: 0; font-size: 18px;">Toko Online Aktif</strong>
      </div>
      <p class="muted-copy">Terakhir diperbarui otomatis dari sistem backoffice aktif.</p>
      <div class="kv-list" style="margin-top: 18px;">
        <div class="kv-item"><span>Jam toko</span><strong><?php $hours = json_decode((string) setting('store_hours_json', ''), true); echo e(is_array($hours) ? 'Diatur per hari' : 'Belum diatur'); ?></strong></div>
        <div class="kv-item"><span>WhatsApp</span><strong><?= e(setting('whatsapp', '-')) ?></strong></div>
      </div>
    </aside>
  </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';