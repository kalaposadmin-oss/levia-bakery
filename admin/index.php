<?php

require __DIR__ . '/../lib/db.php';

$currentAdmin = require_admin();

$stats = [
    'requests' => (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'products' => (int) db()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'limited' => (int) db()->query('SELECT COUNT(*) FROM products WHERE stock_status IN ("limited", "sold_out") OR stock <= 5')->fetchColumn(),
];
$requests = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$title = 'Dashboard';
ob_start();
?>
<div class="page-stack">
  <section class="page-hero" data-watermark="BO">
    <div class="page-hero-inner">
      <div>
        <div class="metric-label">Daily Catalog</div>
        <h3>Dashboard Katalog Harian</h3>
        <p>Pantau status toko, menu aktif, dan request WhatsApp tanpa menggantikan sistem kasir utama.</p>
      </div>
    </div>
  </section>

  <section class="stats-grid">
    <article class="stat-card">
      <div class="stat-card-head">
        <div class="metric-label">Request WA</div>
        <span class="material-symbols-outlined metric-icon">forum</span>
      </div>
      <div class="metric-value"><?= e((string) $stats['requests']) ?></div>
      <div class="metric-foot"><span class="material-symbols-outlined">chat</span> Arsip request sebelum konfirmasi WA</div>
    </article>
    <article class="stat-card">
      <div class="stat-card-head">
        <div class="metric-label">WhatsApp</div>
        <span class="material-symbols-outlined metric-icon">call</span>
      </div>
      <div class="metric-value" style="font-size:24px;"><?= e(setting('whatsapp', '-') ?: '-') ?></div>
      <div class="metric-foot"><span class="material-symbols-outlined">verified</span> Nomor tujuan konfirmasi pelanggan</div>
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
        <div class="metric-label">Perlu Konfirmasi</div>
        <span class="material-symbols-outlined metric-icon">warning</span>
      </div>
      <div class="metric-value is-danger"><?= e((string) $stats['limited']) ?></div>
      <div class="metric-foot"><span class="material-symbols-outlined">notifications_active</span> Item terbatas/habis di katalog</div>
    </article>
  </section>

  <section class="card">
    <div class="card-head">
      <h3>Request WA Terbaru</h3>
      <a class="ghost-btn" href="orders.php">Lihat arsip <span class="material-symbols-outlined">arrow_forward</span></a>
    </div>

    <?php if (!$requests): ?>
      <div class="empty-state">
        <div class="empty-state-illustration"><span class="material-symbols-outlined">inbox</span></div>
        <div>
          <h4>Belum Ada Request</h4>
          <p>Request baru akan tersimpan di sini setelah pelanggan lanjut ke WhatsApp dari katalog.</p>
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
              <th>Estimasi</th>
              <th>Tanggal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $order): ?>
              <tr>
                <td><strong><?= e($order['order_code']) ?></strong></td>
                <td><?= e($order['customer_name']) ?><br><span class="helper-text"><?= e($order['customer_phone']) ?></span></td>
                <td><span class="status-pill new">Request WA</span></td>
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
      <h4>Katalog siap dipakai hari ini?</h4>
      <p>Perbarui status produk, banner, jam toko, dan nomor WhatsApp agar pelanggan langsung masuk ke obrolan yang tepat.</p>
      <div class="action-group">
        <a class="secondary-btn" href="products.php">Kelola Katalog</a>
        <a class="ghost-btn" href="settings.php">Setting Toko</a>
      </div>
    </article>

    <aside class="status-card">
      <small>Sistem Status</small>
      <div class="status-row" style="margin-top: 12px;">
        <span class="dot-live"></span>
        <strong style="margin: 0; font-size: 18px;">Daily Catalog Aktif</strong>
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
