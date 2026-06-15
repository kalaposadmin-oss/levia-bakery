<?php

require __DIR__ . '/lib/db.php';

date_default_timezone_set('Asia/Jakarta');

try {
    $categories = db()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
    $products = db()->query('SELECT p.*, c.slug category_slug, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.is_popular DESC, p.name')->fetchAll();
    $promos = db()->query('SELECT * FROM promos WHERE is_active = 1 ORDER BY type = "hero" DESC, id DESC')->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database belum siap. Jalankan install.php setelah mengisi config.php.');
}

function default_hours_schedule(): array
{
    return [
        'mon' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
        'tue' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
        'wed' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
        'thu' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
        'fri' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
        'sat' => ['open' => '09:00', 'close' => '21:00', 'active' => 1],
        'sun' => ['open' => '00:00', 'close' => '00:00', 'active' => 0],
    ];
}

function normalize_hours_schedule(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? array_merge(default_hours_schedule(), $decoded) : default_hours_schedule();
}

function current_store_status(array $hours): array
{
    $dayMap = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $dayKey = $dayMap[(int) date('w')] ?? 'mon';
    $today = $hours[$dayKey] ?? ['open' => '08:00', 'close' => '20:00', 'active' => 1];

    $open = !empty($today['active']);
    $openTime = $today['open'] ?? '08:00';
    $closeTime = $today['close'] ?? '20:00';
    $now = date('H:i');

    if (!$open) {
        return ['label' => 'Hari ini libur', 'detail' => 'Buka lagi besok', 'is_open' => false, 'is_holiday' => true, 'open_time' => $openTime, 'close_time' => $closeTime];
    }

    $isOpenNow = $now >= $openTime && $now <= $closeTime;

    return $isOpenNow
        ? ['label' => 'Sedang buka', 'detail' => 'Buka jam ' . $openTime . ' sampai ' . $closeTime, 'is_open' => true, 'is_holiday' => false, 'open_time' => $openTime, 'close_time' => $closeTime]
        : ['label' => 'Sedang tutup', 'detail' => 'Buka jam ' . $openTime . ' sampai ' . $closeTime, 'is_open' => false, 'is_holiday' => false, 'open_time' => $openTime, 'close_time' => $closeTime];
}

$hero = json_decode((string) setting('hero_promo_json', ''), true);
if (!is_array($hero)) {
    $hero = $promos[0] ?? ['title' => 'Promo Special', 'subtitle' => 'Diskon 20% khusus croissant pagi.', 'image' => 'assets/hero-promo.png'];
}
$hero = [
    'title' => trim((string) ($hero['title'] ?? 'Promo Special')),
    'subtitle' => trim((string) ($hero['subtitle'] ?? 'Diskon 20% khusus croissant pagi.')),
    'image' => trim((string) ($hero['image'] ?? 'assets/hero-promo.png')),
];
$branch = setting('store_branch', 'Gading Serpong');
$storeName = setting('store_name', 'Levia Bakery');
$storeAddress = setting('store_address', '');
$googleMapsUrl = trim((string) setting('google_maps_url', ''));
$whatsApp = trim((string) setting('whatsapp', ''));
$storeHours = normalize_hours_schedule((string) setting('store_hours_json', ''));
$storeStatus = current_store_status($storeHours);
$storeCanCheckout = !empty($storeStatus['is_open']) && empty($storeStatus['is_holiday']);
$todayKey = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][(int) date('w')] ?? 'mon';
$todayHours = $storeHours[$todayKey] ?? ['open' => '08:00', 'close' => '20:00', 'active' => 1];
$todayHoursText = !empty($todayHours['active']) ? (($todayHours['open'] ?? '08:00') . ' - ' . ($todayHours['close'] ?? '20:00')) : 'Tutup';
$deliveryOptions = json_decode((string) setting('delivery_options_json', ''), true);
if (!is_array($deliveryOptions) || !$deliveryOptions) {
    $deliveryOptions = [
        ['value' => 'pickup', 'label' => 'Ambil Sendiri', 'needs_address' => false, 'maps_url' => ''],
        ['value' => 'home_bake', 'label' => 'Home Bake', 'needs_address' => true, 'maps_url' => ''],
        ['value' => 'gosend', 'label' => 'Gosend / Gojek', 'needs_address' => true, 'maps_url' => ''],
    ];
}

$categoryMap = [];
foreach ($categories as $category) {
    $categoryMap[$category['slug']] = $category;
}

$categoryChips = [
    ['slug' => 'roti-manis', 'label' => 'Roti Manis', 'icon' => 'R'],
    ['slug' => 'roti-tawar', 'label' => 'Roti Tawar', 'icon' => 'T'],
    ['slug' => 'croissant', 'label' => 'Croissant', 'icon' => 'C'],
    ['slug' => 'camilan', 'label' => 'Camilan', 'icon' => 'M'],
    ['slug' => 'promo', 'label' => 'Promo', 'icon' => 'P'],
    ['slug' => 'popular', 'label' => 'Terlaris', 'icon' => '*'],
    ['slug' => 'kopi', 'label' => 'Kopi', 'icon' => 'K'],
    ['slug' => 'all', 'label' => 'Lainnya', 'icon' => '#'],
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($storeName) ?> Order Web</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/site.css">
</head>
<body>
  <div class="app-shell">
    <main class="storefront">
      <div class="top-info-bar">
        <div class="top-info-item">📍 <?= e($storeAddress !== '' ? $storeAddress : $branch) ?></div>
        <div class="top-info-item">● <?= e($storeStatus['label']) ?> • <?= e($storeStatus['detail']) ?></div>
      </div>

      <header class="topbar">
        <div class="address-row">
          <div class="address-copy">
            <small>Dikirim ke:</small>
          <button class="plain-button" type="button"><?= e($branch) ?> <span>⌄</span></button>
          </div>
          <button class="bag-button" type="button" data-open-cart aria-label="Buka keranjang">🛍<strong data-cart-count>0</strong></button>
        </div>
        <label class="search-field"><span>⌕</span><input id="searchInput" type="search" placeholder="Cari kelezatan hari ini..."></label>
      </header>

      <section class="hero-showcase">
        <img src="<?= e($hero['image']) ?>" alt="<?= e($hero['title']) ?>">
      </section>

<?php if (!$storeCanCheckout): ?>
      <section class="notice-banner">
        <strong><?= e($storeStatus['label']) ?></strong>
        <span><?= e($storeStatus['detail']) ?></span>
      </section>
      <?php endif; ?>

      <section class="quick-actions">
        <a class="quick-action-card" href="<?= e($googleMapsUrl !== '' ? $googleMapsUrl : '#') ?>" <?= $googleMapsUrl !== '' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
          <span class="quick-action-icon">⌖</span>
          <span>Cek Lokasi</span>
        </a>
        <a class="quick-action-card quick-action-chat" href="<?= e($whatsApp !== '' ? 'https://wa.me/' . preg_replace('/\D+/', '', $whatsApp) : '#') ?>" <?= $whatsApp !== '' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
          <span class="quick-action-icon">◫</span>
          <span>Chat WhatsApp</span>
        </a>
      </section>      <section class="promo-hero-card">
        <img src="<?= e($hero['image']) ?>" alt="<?= e($hero['title']) ?>">
        <div class="promo-hero-copy">
          <h1><?= e($hero['title']) ?></h1>
          <p><?= e($hero['subtitle']) ?></p>
          <div class="pager"><span></span><i></i><i></i></div>
        </div>
      </section>

      <section class="category-grid">
        <?php foreach ($categoryChips as $chip): ?>
          <?php if (!isset($categoryMap[$chip['slug']]) && !in_array($chip['slug'], ['popular', 'all'], true)) { continue; } ?>
          <button class="category <?= $chip['slug'] === 'promo' ? 'pink' : '' ?> <?= $chip['slug'] === 'all' ? 'is-active' : '' ?>" type="button" data-category="<?= e($chip['slug']) ?>">
            <span><?= e($chip['icon']) ?></span>
            <?= e($chip['label']) ?>
          </button>
        <?php endforeach; ?>
      </section>

      <section class="section-block">
        <div class="section-title"><h2>Paling Laris</h2><button type="button" data-category-jump="popular">Lihat Semua</button></div>
        <div class="horizontal-list" id="bestSellerList">
          <?php $bestSellers = array_slice(array_filter($products, fn($p) => !empty($p['is_popular'])), 0, 5); if (!$bestSellers) { $bestSellers = array_slice($products, 0, 5); } ?>
          <?php foreach ($bestSellers as $product): ?>
            <article class="product-card" data-product-id="<?= e((string) $product['id']) ?>">
              <div class="product-image">
                <img src="<?= e($product['image'] ?: 'assets/almond-croissant.png') ?>" alt="<?= e($product['name']) ?>">
                <span class="badge <?= (($product['stock_status'] ?? '') === 'limited') ? 'limited' : (((int) $product['stock'] <= 0 || ($product['stock_status'] ?? '') === 'sold_out') ? 'sold-out' : 'ready') ?>">
                  <?= ((int) $product['stock'] <= 0 || ($product['stock_status'] ?? '') === 'sold_out') ? 'Sold Out' : (($product['stock_status'] ?? '') === 'limited' ? 'Limited' : 'Ready Stock') ?>
                </span>
              </div>
              <div class="product-body">
                <h3><?= e($product['name']) ?></h3>
                <p><?= money($product['price']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="section-block">
        <div class="section-title"><h2>Promo Spesial</h2><button type="button" data-category-jump="promo">Lihat Semua</button></div>
        <div class="promo-strip">
          <?php foreach (array_slice($promos, 1, 4) as $promo): ?>
            <button class="promo-banner" type="button" data-promo="<?= e($promo['title']) ?>">
              <img src="<?= e($promo['image']) ?>" alt="<?= e($promo['title']) ?>">
              <b><?= e($promo['title']) ?></b>
              <small><?= e($promo['subtitle']) ?></small>
            </button>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="section-block">
        <div class="section-title"><h2>Stok Hari Ini</h2><button type="button" data-category-jump="all">Semua</button></div>
        <p class="section-note">Baru saja keluar dari oven kami</p>
        <div class="product-grid" id="stockList">
          <?php foreach ($products as $product): ?>
            <article class="product-card" data-product-id="<?= e((string) $product['id']) ?>">
              <div class="product-image">
                <img src="<?= e($product['image'] ?: 'assets/almond-croissant.png') ?>" alt="<?= e($product['name']) ?>">
                <span class="badge <?= (($product['stock_status'] ?? '') === 'limited') ? 'limited' : (((int) $product['stock'] <= 0 || ($product['stock_status'] ?? '') === 'sold_out') ? 'sold-out' : 'ready') ?>">
                  <?= ((int) $product['stock'] <= 0 || ($product['stock_status'] ?? '') === 'sold_out') ? 'Sold Out' : (($product['stock_status'] ?? '') === 'limited' ? 'Limited' : 'Ready Stock') ?>
                </span>
              </div>
              <div class="product-body">
                <h3><?= e($product['name']) ?></h3>
                <p><?= money($product['price']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="store-card">
        <div>
          <h3><?= e($storeName) ?> - <?= e($branch) ?></h3>
          <p><?= e($storeStatus['detail']) ?> • Jam hari ini <?= e($todayHoursText) ?></p>
        </div>
        <button type="button" id="storeDetailBtn">Detail</button>
      </section>

      <section class="story-section blog-section">
        <article class="blog-feature">
          <img src="assets/pairing.png" alt="Proses artisan bakery">
          <div class="blog-copy">
            <small>Sejak 2018</small>
            <h2>Dedikasi Artisan</h2>
            <p>Setiap adonan kami uleni dengan tangan, lalu difermentasi perlahan agar rasa dan teksturnya tetap hidup sampai ke meja pelanggan.</p>
            <a href="#stockList">Lihat katalog <span>?</span></a>
          </div>
        </article>
      </section>

    </main>
  </div>

  <nav class="bottom-nav">
    <button class="is-active" type="button">⌂<span>Boutique</span></button>
    <button type="button" data-open-cart>▤<span>Orders</span></button>
    <button type="button">♡<span>Favorites</span></button>
    <button type="button">♙<span>Profile</span></button>
  </nav>

  <div class="cart-drawer" id="cartDrawer" aria-hidden="true">
    <button class="drawer-backdrop" type="button" data-close-cart></button>
    <section class="drawer-panel">
      <div class="drawer-head"><div><small>Pesanan kamu</small><h2>Keranjang</h2></div><button type="button" data-close-cart>×</button></div>
      <div id="cartItems" class="cart-items"></div>
      <form id="checkoutForm" class="checkout-form">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <label>Nama pelanggan <input name="customer_name" required placeholder="Nama lengkap"></label>
        <label>No. WhatsApp <input name="customer_phone" value="<?= e($whatsApp) ?>"></label>
        <label>Metode kirim
          <select name="delivery_method" id="deliveryMethod">
            <option value="pickup">Ambil Sendiri</option>
          </select>
        </label>
        <a id="deliveryMapsLink" class="maps-link" href="#" target="_blank" rel="noopener noreferrer" hidden></a>
        <label>Alamat / titik antar <textarea name="delivery_address" rows="2" id="deliveryAddress" placeholder="Alamat atau titik antar"></textarea></label>
        <label>Catatan pesanan <textarea name="note" rows="2" placeholder="Contoh: croissant dipanaskan"></textarea></label>
        <input type="hidden" name="cart" id="cartPayload">
      </form>
      <?php if (!$storeCanCheckout): ?>
        <div class="notice-banner" style="margin: 0 0 12px;">
          <strong><?= e($storeStatus['label']) ?></strong>
          <span><?= e($storeStatus['detail']) ?></span>
        </div>
      <?php endif; ?>
      <div class="checkout-summary"><span>Total</span><strong id="cartTotal">Rp 0</strong></div>
      <button class="checkout-button" type="button" id="checkoutBtn" data-store-closed="<?= $storeCanCheckout ? '0' : '1' ?>" aria-disabled="<?= $storeCanCheckout ? 'false' : 'true' ?>" <?= $storeCanCheckout ? '' : 'disabled' ?>><?= $storeCanCheckout ? 'Buat Pesanan' : 'Toko Libur' ?></button>
    </section>
  </div>

  <div class="toast" id="toast"></div>
  <script>
    window.LEVIA_PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.LEVIA_ORDER_ENDPOINT = "api/order-create.php";
    window.LEVIA_DELIVERY_OPTIONS = <?= json_encode($deliveryOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="assets/app.js"></script>
</body>
</html>

