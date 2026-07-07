<?php

require __DIR__ . '/lib/db.php';

date_default_timezone_set('Asia/Jakarta');
header('Cache-Control: no-cache, must-revalidate');

try {
    [$categories, $products, $promos] = cache_remember('storefront-catalog', 300, function (): array {
        return [
            db()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll(),
            db()->query('SELECT p.*, c.slug category_slug, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY p.is_popular DESC, p.name')->fetchAll(),
            db()->query('SELECT * FROM promos WHERE is_active = 1 ORDER BY type = "hero" DESC, id DESC')->fetchAll(),
        ];
    });
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

function homepage_section_enabled(string $key): bool
{
    return setting($key, '1') !== '0';
}

function whatsapp_number(?string $value): string
{
    $number = preg_replace('/\D+/', '', (string) $value) ?: '';
    if ($number === '') {
        return '';
    }

    if (str_starts_with($number, '0')) {
        return '62' . substr($number, 1);
    }

    if (str_starts_with($number, '8')) {
        return '62' . $number;
    }

    return $number;
}

function default_blog_section(): array
{
    return [
        'eyebrow' => 'Sejak 2018',
        'title' => 'Dedikasi Artisan',
        'body' => 'Setiap adonan kami uleni dengan tangan, lalu difermentasi perlahan agar rasa dan teksturnya tetap hidup sampai ke meja pelanggan.',
        'image' => 'assets/pairing.png',
        'button_label' => 'Baca blog',
        'url' => '#blogSection',
    ];
}

function blog_summary(string $value, int $limit = 220): string
{
    $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length <= $limit) {
        return $text;
    }
    $slice = function_exists('mb_substr') ? mb_substr($text, 0, $limit - 1, 'UTF-8') : substr($text, 0, $limit - 1);
    return rtrim($slice) . '…';
}

function normalize_blog_section(string $json): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return default_blog_section();
    }

    $defaults = default_blog_section();
    return [
        'eyebrow' => trim((string) ($decoded['eyebrow'] ?? $defaults['eyebrow'])),
        'title' => trim((string) ($decoded['title'] ?? $defaults['title'])),
        'body' => trim((string) ($decoded['body'] ?? $defaults['body'])),
        'image' => trim((string) ($decoded['image'] ?? $defaults['image'])),
        'button_label' => trim((string) ($decoded['button_label'] ?? $defaults['button_label'])),
        'url' => trim((string) ($decoded['url'] ?? $defaults['url'])),
    ];
}

function homepage_blog_section(): array
{
    try {
        $blog = db()->query('SELECT * FROM blogs WHERE is_active = 1 ORDER BY is_featured DESC, id DESC LIMIT 1')->fetch();
        if ($blog) {
            return [
                'eyebrow' => trim((string) ($blog['eyebrow'] ?: 'Cerita Levia')),
                'title' => trim((string) $blog['title']),
                'body' => blog_summary((string) ($blog['excerpt'] ?: $blog['content'] ?: '')),
                'image' => trim((string) ($blog['image'] ?: 'assets/pairing.png')),
                'button_label' => 'Baca blog',
                'url' => 'blog.php?slug=' . rawurlencode((string) $blog['slug']),
            ];
        }
    } catch (Throwable $e) {
        return normalize_blog_section((string) setting('blog_section_json', ''));
    }

    return normalize_blog_section((string) setting('blog_section_json', ''));
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
$whatsAppNumber = whatsapp_number($whatsApp);
$whatsAppChatText = trim((string) setting('whatsapp_chat_text', 'Halo ' . $storeName . ', saya mau tanya menu hari ini.'));
$whatsAppChatUrl = $whatsAppNumber !== '' ? 'https://wa.me/' . $whatsAppNumber . ($whatsAppChatText !== '' ? '?text=' . rawurlencode($whatsAppChatText) : '') : '#';
$storeHours = normalize_hours_schedule((string) setting('store_hours_json', ''));
$showHeroPromo = homepage_section_enabled('show_hero_promo');
$showBestSellers = homepage_section_enabled('show_best_sellers');
$showPromos = homepage_section_enabled('show_promos');
$showTodayCatalog = homepage_section_enabled('show_today_catalog');
$showStoreCard = homepage_section_enabled('show_store_card');
$showBlogSection = homepage_section_enabled('show_blog_section');
$storeStatus = current_store_status($storeHours);
$storeCanCheckout = !empty($storeStatus['is_open']) && empty($storeStatus['is_holiday']);
$todayKey = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][(int) date('w')] ?? 'mon';
$todayHours = $storeHours[$todayKey] ?? ['open' => '08:00', 'close' => '20:00', 'active' => 1];
$todayHoursText = !empty($todayHours['active']) ? (($todayHours['open'] ?? '08:00') . ' - ' . ($todayHours['close'] ?? '20:00')) : 'Tutup';
$deliveryOptions = json_decode((string) setting('delivery_options_json', ''), true);
$blogSection = homepage_blog_section();
if (!is_array($deliveryOptions) || !$deliveryOptions) {
    $deliveryOptions = [
        ['value' => 'pickup', 'label' => 'Ambil Sendiri', 'needs_address' => false, 'maps_url' => ''],
        ['value' => 'home_bake', 'label' => 'Home Bake', 'needs_address' => true, 'maps_url' => ''],
        ['value' => 'gosend', 'label' => 'Gosend / Gojek', 'needs_address' => true, 'maps_url' => ''],
    ];
}

$categoryChips = array_map(fn($category) => [
    'slug' => (string) $category['slug'],
    'label' => (string) $category['name'],
    'icon' => (string) ($category['icon'] ?: 'bread'),
], $categories);

if (!$showPromos) {
    $categoryChips = array_values(array_filter($categoryChips, fn($chip) => ($chip['slug'] ?? '') !== 'promo'));
}

array_unshift($categoryChips, ['slug' => 'popular', 'label' => 'Terlaris', 'icon' => 'popular']);
$categoryChips[] = ['slug' => 'all', 'label' => 'Semua', 'icon' => 'all'];
$heroImage = responsive_image_data($hero['image'], [640, 960, 1400], 80, 55);
$productsClient = array_map(function (array $product): array {
    $image = responsive_image_data($product['image'] ?: 'assets/almond-croissant.png', [320, 640], 78, 52);
    $product['image'] = $image['src'];
    $product['image_srcset_webp'] = $image['webp'];
    $product['image_srcset_avif'] = $image['avif'];
    $product['image_width'] = $image['width'];
    $product['image_height'] = $image['height'];
    return $product;
}, $products);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($storeName) ?> Daily Catalog</title>
  <?php if ($heroImage['avif'] !== ''): ?><link rel="preload" as="image" href="<?= e($heroImage['src']) ?>" imagesrcset="<?= e($heroImage['avif']) ?>" imagesizes="(max-width: 768px) 100vw, 980px" type="image/avif" fetchpriority="high"><?php elseif ($heroImage['webp'] !== ''): ?><link rel="preload" as="image" href="<?= e($heroImage['src']) ?>" imagesrcset="<?= e($heroImage['webp']) ?>" imagesizes="(max-width: 768px) 100vw, 980px" type="image/webp" fetchpriority="high"><?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap">
  <link rel="stylesheet" href="assets/site.css?v=20260707a">
</head>
<body>
  <div class="app-shell">
    <main class="storefront" id="top">
      <div class="top-info-bar">
        <div class="top-info-item"><span class="ui-icon icon-location" aria-hidden="true"></span><?= e($storeAddress !== '' ? $storeAddress : $branch) ?></div>
        <div class="top-info-item"><span class="ui-icon icon-status" aria-hidden="true"></span><?= e($storeStatus['label']) ?> &bull; <?= e($storeStatus['detail']) ?></div>
      </div>

      <header class="topbar">
        <div class="address-row">
          <div class="address-copy">
            <small>Dikirim ke:</small>
          <button class="plain-button" type="button"><?= e($branch) ?> <span class="ui-icon icon-chevron" aria-hidden="true"></span></button>
          </div>
          <button class="bag-button" type="button" data-open-cart aria-label="Buka keranjang"><span class="ui-icon icon-cart" aria-hidden="true"></span><strong data-cart-count>0</strong></button>
        </div>
        <label class="search-field"><span class="ui-icon icon-search" aria-hidden="true"></span><input id="searchInput" type="search" placeholder="Cari kelezatan hari ini..."></label>
      </header>

      <section class="hero-showcase">
        <?= responsive_image_html($hero['image'], $hero['title'], ['widths' => [640, 960, 1400], 'sizes' => '(max-width: 768px) 100vw, 980px', 'loading' => 'eager', 'fetchpriority' => 'high']) ?>
      </section>

<?php if (!$storeCanCheckout): ?>
      <section class="notice-banner">
        <strong><?= e($storeStatus['label']) ?></strong>
        <span><?= e($storeStatus['detail']) ?></span>
      </section>
      <?php endif; ?>

      <section class="quick-actions">
        <a class="quick-action-card" href="<?= e($googleMapsUrl !== '' ? $googleMapsUrl : '#') ?>" <?= $googleMapsUrl !== '' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
          <span class="quick-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.1 7-12A7 7 0 1 0 5 9c0 5.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg></span><span>Cek Lokasi</span>
        </a>
        <a class="quick-action-card quick-action-chat" href="<?= e($whatsAppChatUrl) ?>" <?= $whatsAppNumber !== '' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
          <span class="quick-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.6a8 8 0 0 1-11.8 7L4 20l1.4-4A8 8 0 1 1 20 11.6Z"/><path d="M9 8.5c.5 2.5 2 4 4.5 5"/></svg></span><span>Chat WhatsApp</span>
        </a>
      </section>

      <?php if ($showHeroPromo): ?>
      <section class="promo-hero-card">
        <?= responsive_image_html($hero['image'], $hero['title'], ['widths' => [640, 960, 1400], 'sizes' => '(max-width: 768px) 100vw, 980px', 'loading' => 'lazy']) ?>
        <div class="promo-hero-copy">
          <h1><?= e($hero['title']) ?></h1>
          <p><?= e($hero['subtitle']) ?></p>
          <div class="pager"><span></span><i></i><i></i></div>
        </div>
      </section>
      <?php endif; ?>

      <section class="category-grid">
        <?php foreach ($categoryChips as $chip): ?>
          <button class="category <?= $chip['slug'] === 'promo' ? 'pink' : '' ?> <?= $chip['slug'] === 'all' ? 'is-active' : '' ?>" type="button" data-category="<?= e($chip['slug']) ?>" data-category-label="<?= e($chip['label']) ?>">
            <span><?php if (str_starts_with($chip['icon'], 'uploads/')): ?><img src="<?= e($chip['icon']) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><?= e(['bread'=>'🥖','croissant'=>'🥐','snack'=>'🍪','coffee'=>'☕','tag'=>'%','popular'=>'★','all'=>'▦'][$chip['icon']] ?? '🍞') ?><?php endif; ?></span>
            <?= e($chip['label']) ?>
          </button>
        <?php endforeach; ?>
      </section>

      <?php if ($showBestSellers): ?>
      <section class="section-block">
        <div class="section-title"><h2>Paling Laris</h2><button type="button" data-category-jump="popular">Lihat Semua</button></div>
        <div class="horizontal-list" id="bestSellerList">
          <?php $bestSellers = array_slice(array_filter($products, fn($p) => !empty($p['is_popular'])), 0, 5); if (!$bestSellers) { $bestSellers = array_slice($products, 0, 5); } ?>
          <?php foreach ($bestSellers as $product): ?>
            <article class="product-card" data-product-id="<?= e((string) $product['id']) ?>">
              <div class="product-image">
                <?= responsive_image_html($product['image'] ?: 'assets/almond-croissant.png', $product['name'], ['widths' => [320, 640], 'sizes' => '(max-width: 520px) 44vw, 210px']) ?>
                <span class="badge <?= (($product['stock_status'] ?? '') === 'limited') ? 'limited' : ((($product['stock_status'] ?? '') === 'sold_out') ? 'sold-out' : 'ready') ?>">
                  <?= (($product['stock_status'] ?? '') === 'sold_out') ? 'Habis' : (($product['stock_status'] ?? '') === 'limited' ? 'Terbatas' : 'Ready') ?>
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
      <?php endif; ?>

      <?php if ($showPromos): ?>
      <section class="section-block">
        <div class="section-title"><h2>Promo Spesial</h2><button type="button" data-category-jump="promo">Lihat Semua</button></div>
        <div class="promo-strip">
          <?php foreach (array_slice($promos, 1, 4) as $promo): ?>
            <button class="promo-banner" type="button" data-promo="<?= e($promo['title']) ?>">
              <?= responsive_image_html($promo['image'], $promo['title'], ['widths' => [480, 960], 'sizes' => '(max-width: 768px) 82vw, 460px']) ?>
              <b><?= e($promo['title']) ?></b>
              <small><?= e($promo['subtitle']) ?></small>
            </button>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($showTodayCatalog): ?>
      <section class="section-block catalog-section" id="catalogSection">
        <div class="section-title"><h2 id="catalogTitle">Katalog Hari Ini</h2><button type="button" data-category-jump="all">Semua</button></div>
        <p class="section-note">Ketersediaan final dikonfirmasi lewat WhatsApp</p>
        <div class="product-grid" id="stockList">
          <?php foreach (array_slice($products, 0, 12) as $product): ?>
            <article class="product-card" data-product-id="<?= e((string) $product['id']) ?>">
              <div class="product-image">
                <?= responsive_image_html($product['image'] ?: 'assets/almond-croissant.png', $product['name'], ['widths' => [320, 640], 'sizes' => '(max-width: 520px) 44vw, 210px']) ?>
                <span class="badge <?= (($product['stock_status'] ?? '') === 'limited') ? 'limited' : ((($product['stock_status'] ?? '') === 'sold_out') ? 'sold-out' : 'ready') ?>">
                  <?= (($product['stock_status'] ?? '') === 'sold_out') ? 'Habis' : (($product['stock_status'] ?? '') === 'limited' ? 'Terbatas' : 'Ready') ?>
                </span>
              </div>
              <div class="product-body">
                <h3><?= e($product['name']) ?></h3>
                <p><?= money($product['price']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <button class="catalog-load-more" id="catalogLoadMore" type="button" <?= count($products) <= 12 ? 'hidden' : '' ?>>Muat produk lainnya</button>
      </section>
      <?php endif; ?>

      <?php if ($showStoreCard): ?>
      <section class="store-card">
        <div>
          <h3><?= e($branch ?: $storeName) ?></h3>
          <p><?= e($storeStatus['detail']) ?> • Jam hari ini <?= e($todayHoursText) ?></p>
        </div>
        <button type="button" id="storeDetailBtn">Detail</button>
      </section>
      <?php endif; ?>

      <?php if ($showBlogSection): ?>
      <section class="story-section blog-section" id="blogSection">
        <article class="blog-feature">
          <?= responsive_image_html($blogSection['image'], $blogSection['title'], ['widths' => [240, 480], 'sizes' => '120px']) ?>
          <div class="blog-copy">
            <small><?= e($blogSection['eyebrow']) ?></small>
            <h2><?= e($blogSection['title']) ?></h2>
            <p><?= e($blogSection['body']) ?></p>
            <a href="<?= e($blogSection['url'] ?? '#blogSection') ?>"><?= e($blogSection['button_label']) ?> <span>&rarr;</span></a>
          </div>
        </article>
      </section>
      <?php endif; ?>

    </main>
  </div>

  <nav class="bottom-nav">
    <a class="is-active" href="index.php#top"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg><span>Beranda</span></a>
    <a href="index.php#catalogSection"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v16"/></svg><span>Katalog</span></a>
    <a href="index.php#blogSection"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A3.5 3.5 0 0 1 7.5 2H11a2 2 0 0 1 2 2v17a3 3 0 0 0-3-3H4Z"/><path d="M20 5.5A3.5 3.5 0 0 0 16.5 2H15a2 2 0 0 0-2 2v17a3 3 0 0 1 3-3h4Z"/></svg><span>Blog</span></a>
    <a href="#profile"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span>Profil</span></a>
  </nav>

  <div class="cart-drawer" id="cartDrawer" aria-hidden="true">
    <button class="drawer-backdrop" type="button" data-close-cart></button>
    <section class="drawer-panel">
      <div class="drawer-head"><div><small>Request menu</small><h2>WhatsApp</h2></div><button type="button" data-close-cart>x</button></div>
      <div id="cartItems" class="cart-items"></div>
      <form id="checkoutForm" class="checkout-form">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <label>Nama pelanggan <input name="customer_name" required placeholder="Nama lengkap"></label>
        <label>No. WhatsApp <input name="customer_phone" placeholder="62812xxxxxxx"></label>
        <label>Metode kirim
          <select name="delivery_method" id="deliveryMethod">
            <option value="pickup">Ambil Sendiri</option>
          </select>
        </label>
        <a id="deliveryMapsLink" class="maps-link" href="#" target="_blank" rel="noopener noreferrer" hidden></a>
        <label>Alamat / titik antar <textarea name="delivery_address" rows="2" id="deliveryAddress" placeholder="Alamat atau titik antar"></textarea></label>
        <label>Catatan request <textarea name="note" rows="2" placeholder="Contoh: kalau stok kurang, boleh diganti"></textarea></label>
        <input type="hidden" name="cart" id="cartPayload">
      </form>
      <?php if (!$storeCanCheckout): ?>
        <div class="notice-banner" style="margin: 0 0 12px;">
          <strong><?= e($storeStatus['label']) ?></strong>
          <span><?= e($storeStatus['detail']) ?></span>
        </div>
      <?php endif; ?>
      <div class="checkout-summary"><span>Total</span><strong id="cartTotal">Rp 0</strong></div>
      <button class="checkout-button" type="button" id="checkoutBtn" data-store-closed="<?= $storeCanCheckout ? '0' : '1' ?>" aria-disabled="<?= $storeCanCheckout ? 'false' : 'true' ?>" <?= $storeCanCheckout ? '' : 'disabled' ?>><?= $storeCanCheckout ? 'Lanjut ke WhatsApp' : 'Toko Libur' ?></button>
    </section>
  </div>

  <div class="product-modal" id="productModal" aria-hidden="true">
    <button class="product-modal-backdrop" type="button" data-close-product-detail aria-label="Tutup detail produk"></button>
    <section class="product-detail-panel" role="dialog" aria-modal="true" aria-labelledby="productDetailName">
      <button class="product-detail-close" type="button" data-close-product-detail aria-label="Tutup detail produk">x</button>
      <img class="product-detail-image" id="productDetailImage" src="" alt="">
      <div class="product-detail-body">
        <div class="product-detail-title-row">
          <div>
            <small id="productDetailCategory"></small>
            <h2 id="productDetailName"></h2>
          </div>
          <span class="badge ready" id="productDetailBadge"></span>
        </div>
        <strong class="product-detail-price" id="productDetailPrice"></strong>
        <p class="product-detail-description" id="productDetailDescription"></p>
        <dl class="product-detail-meta">
          <div><dt>Isi / berat</dt><dd id="productDetailPackage"></dd></div>
          <div><dt>Stok hari ini</dt><dd id="productDetailStock"></dd></div>
          <div><dt>Masa simpan</dt><dd id="productDetailShelfLife"></dd></div>
        </dl>
        <div class="product-detail-ingredients">
          <h3>Ingredient</h3>
          <ul id="productDetailIngredients"></ul>
        </div>
        <button class="checkout-button" type="button" id="productDetailAddBtn">Tambahkan ke Keranjang</button>
      </div>
    </section>
  </div>

  <div class="toast" id="toast"></div>
  <script>
    window.LEVIA_PRODUCTS = <?= json_encode($productsClient, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.LEVIA_ORDER_ENDPOINT = "api/order-create.php";
    window.LEVIA_DELIVERY_OPTIONS = <?= json_encode($deliveryOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.LEVIA_ADMIN_WHATSAPP = <?= json_encode($whatsAppNumber, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.LEVIA_STORE_NAME = <?= json_encode($storeName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="assets/app.js?v=20260707a" defer></script>
</body>
</html>



