<?php

require __DIR__ . '/../lib/db.php';
require_admin();

function default_delivery_options(): array
{
    return [
        ['value' => 'pickup', 'label' => 'Ambil Sendiri', 'needs_address' => false, 'maps_url' => ''],
        ['value' => 'home_bake', 'label' => 'Home Bake', 'needs_address' => true, 'maps_url' => ''],
        ['value' => 'delivery', 'label' => 'Delivery', 'needs_address' => true, 'maps_url' => ''],
    ];
}

function normalize_delivery_options($value): array
{
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) {
        return [];
    }

    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $valueId = trim((string) ($row['value'] ?? ''));
        $label = trim((string) ($row['label'] ?? ''));
        if ($valueId === '' || $label === '') {
            continue;
        }

        $out[] = [
            'value' => $valueId,
            'label' => $label,
            'needs_address' => !empty($row['needs_address']),
            'maps_url' => trim((string) ($row['maps_url'] ?? '')),
        ];
    }

    return $out;
}

function day_rows(): array
{
    return [
        ['key' => 'mon', 'label' => 'Senin'],
        ['key' => 'tue', 'label' => 'Selasa'],
        ['key' => 'wed', 'label' => 'Rabu'],
        ['key' => 'thu', 'label' => 'Kamis'],
        ['key' => 'fri', 'label' => 'Jumat'],
        ['key' => 'sat', 'label' => 'Sabtu'],
        ['key' => 'sun', 'label' => 'Minggu'],
    ];
}

function default_hours(): array
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

function normalize_hours($value): array
{
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : default_hours();
}

function default_hero_promo(): array
{
    return [
        'title' => 'Promo Special',
        'subtitle' => 'Diskon 20% khusus croissant pagi.',
        'image' => 'assets/hero-promo.png',
    ];
}

function normalize_hero_promo($value): array
{
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) {
        return default_hero_promo();
    }

    return [
        'title' => trim((string) ($decoded['title'] ?? 'Promo Special')),
        'subtitle' => trim((string) ($decoded['subtitle'] ?? 'Diskon 20% khusus croissant pagi.')),
        'image' => trim((string) ($decoded['image'] ?? 'assets/hero-promo.png')),
    ];
}

function default_blog_section(): array
{
    return [
        'eyebrow' => 'Sejak 2018',
        'title' => 'Dedikasi Artisan',
        'body' => 'Setiap adonan kami uleni dengan tangan, lalu difermentasi perlahan agar rasa dan teksturnya tetap hidup sampai ke meja pelanggan.',
        'image' => 'assets/pairing.png',
        'button_label' => 'Lihat katalog',
    ];
}

function normalize_blog_section($value): array
{
    $decoded = json_decode((string) $value, true);
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
    ];
}

function homepage_section_enabled(string $key): bool
{
    return setting($key, '1') !== '0';
}

function hero_image_path(?string $fallback = null): ?string
{
    return uploaded_image_path('hero_image_file', 'hero', $fallback);
}

function blog_image_path(?string $fallback = null): ?string
{
    return uploaded_image_path('blog_image_file', 'blog', $fallback);
}

function uploaded_image_path(string $field, string $prefix, ?string $fallback = null): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $fallback;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        return $fallback;
    }

    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $name = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name);

    return 'uploads/' . $name;
}

$settingKeys = ['store_name', 'store_branch', 'store_address', 'whatsapp', 'google_maps_url'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($settingKeys as $key) {
        set_setting($key, trim((string) ($_POST[$key] ?? '')));
    }

    foreach (['show_hero_promo', 'show_best_sellers', 'show_promos', 'show_today_catalog', 'show_store_card', 'show_blog_section'] as $key) {
        set_setting($key, isset($_POST[$key]) ? '1' : '0');
    }

    $hours = [];
    foreach (day_rows() as $day) {
        $dayKey = $day['key'];
        $hours[$dayKey] = [
            'open' => trim((string) ($_POST["hours_open_$dayKey"] ?? '08:00')),
            'close' => trim((string) ($_POST["hours_close_$dayKey"] ?? '20:00')),
            'active' => isset($_POST["hours_active_$dayKey"]) ? 1 : 0,
        ];
    }

    set_setting('store_hours_json', json_encode($hours, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $values = (array) ($_POST['delivery_option_value'] ?? []);
    $labels = (array) ($_POST['delivery_option_label'] ?? []);
    $needsAddresses = (array) ($_POST['delivery_option_needs_address'] ?? []);
    $mapsUrls = (array) ($_POST['delivery_option_maps_url'] ?? []);
    $options = [];

    foreach ($values as $index => $value) {
        $value = trim((string) $value);
        $label = trim((string) ($labels[$index] ?? ''));
        if ($value === '' || $label === '') {
            continue;
        }

        $options[] = [
            'value' => $value,
            'label' => $label,
            'needs_address' => (($needsAddresses[$index] ?? '0') === '1'),
            'maps_url' => trim((string) ($mapsUrls[$index] ?? '')),
        ];
    }

    if (!$options) {
        $options = default_delivery_options();
    }

    set_setting('delivery_options_json', json_encode(array_values($options), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $existingHero = normalize_hero_promo(setting('hero_promo_json', ''));
    $heroImage = trim((string) ($_POST['hero_image'] ?? ($existingHero['image'] ?? 'assets/hero-promo.png')));
    $uploadedHeroImage = hero_image_path($heroImage);
    $heroPromo = [
        'title' => trim((string) ($_POST['hero_title'] ?? 'Promo Special')),
        'subtitle' => trim((string) ($_POST['hero_subtitle'] ?? 'Diskon 20% khusus croissant pagi.')),
        'image' => $uploadedHeroImage ?: $heroImage,
    ];
    set_setting('hero_promo_json', json_encode($heroPromo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $existingBlog = normalize_blog_section(setting('blog_section_json', ''));
    $blogImage = trim((string) ($_POST['blog_image'] ?? ($existingBlog['image'] ?? 'assets/pairing.png')));
    $uploadedBlogImage = blog_image_path($blogImage);
    $blogSection = [
        'eyebrow' => trim((string) ($_POST['blog_eyebrow'] ?? 'Sejak 2018')),
        'title' => trim((string) ($_POST['blog_title'] ?? 'Dedikasi Artisan')),
        'body' => trim((string) ($_POST['blog_body'] ?? '')),
        'image' => $uploadedBlogImage ?: $blogImage,
        'button_label' => trim((string) ($_POST['blog_button_label'] ?? 'Lihat katalog')),
    ];
    set_setting('blog_section_json', json_encode($blogSection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $_SESSION['flash'] = 'Setting toko berhasil disimpan.';
    redirect('admin/settings.php');
}

$deliveryOptions = normalize_delivery_options(setting('delivery_options_json', '')) ?: default_delivery_options();
$hours = normalize_hours(setting('store_hours_json', ''));
if (!$hours) { $hours = default_hours(); }
$hours = array_merge(default_hours(), $hours);
$heroPromo = normalize_hero_promo(setting('hero_promo_json', ''));
$blogSection = normalize_blog_section(setting('blog_section_json', ''));

$title = 'Setting Toko';
ob_start();
?>
<?php $debugHours = $hours; ?>
<div class="page-stack">
  <section class="page-hero" data-watermark="SETTING TOKO">
    <div class="page-hero-inner">
      <div>
        <h3>Pengaturan Toko</h3>
        <p>Kelola alamat, jam buka, dan metode pengiriman yang dipakai storefront.</p>
      </div>
      <button form="settingsForm" class="primary-btn" type="submit">
        <span class="material-symbols-outlined">save</span>
        Simpan Semua
      </button>
    </div>
  </section>

  <form method="post" id="settingsForm" class="page-grid-uneven" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <section class="stack">
      <section class="card form-card">
        <div class="card-head"><h3>Banner Utama</h3></div>
        <div class="card-content">
          <div class="form-grid">
            <label>Judul hero <input name="hero_title" value="<?= e($heroPromo['title']) ?>"></label>
            <label>Subtitle hero <input name="hero_subtitle" value="<?= e($heroPromo['subtitle']) ?>"></label>
            <label>Gambar hero <input name="hero_image" value="<?= e($heroPromo['image']) ?>" placeholder="assets/hero-promo.png"></label>
            <label>Upload gambar <input type="file" name="hero_image_file" accept="image/png,image/jpeg,image/webp" id="heroImageFile"></label>
          </div>
          <div class="hero-preview" style="margin-top:18px;">
            <img id="heroPreview" src="<?= e($heroPromo['image']) ?>" alt="Preview hero" style="width:100%; aspect-ratio:1.72; object-fit:cover; border-radius:16px; border:1px solid rgba(75,53,33,.12);">
          </div>
          </div>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head"><h3>Informasi Toko</h3></div>
        <div class="card-content">
          <div class="form-grid">
            <label>Nama toko <input name="store_name" value="<?= e(setting('store_name', 'Levia Bakery')) ?>"></label>
            <label>Cabang <input name="store_branch" value="<?= e(setting('store_branch', 'Gading Serpong')) ?>"></label>
            <label>WhatsApp <input name="whatsapp" value="<?= e(setting('whatsapp', '')) ?>" placeholder="0812xxxxxxx atau 62812xxxxxxx"></label>
            <label>Link Google Maps <input name="google_maps_url" value="<?= e(setting('google_maps_url', '')) ?>" placeholder="https://maps.google.com/..."></label>
          </div>
          <label style="margin-top:18px;">Alamat utama toko<textarea name="store_address" rows="4"><?= e(setting('store_address', '')) ?></textarea></label>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head"><h3>Tampilan Homepage</h3></div>
        <div class="card-content schedule-list">
          <?php $homepageSections = [
            ['key' => 'show_hero_promo', 'label' => 'Banner Promo Utama'],
            ['key' => 'show_best_sellers', 'label' => 'Paling Laris'],
            ['key' => 'show_promos', 'label' => 'Promo Spesial'],
            ['key' => 'show_today_catalog', 'label' => 'Katalog Hari Ini'],
            ['key' => 'show_store_card', 'label' => 'Kartu Info Toko'],
            ['key' => 'show_blog_section', 'label' => 'Blog / Cerita'],
          ]; ?>
          <?php foreach ($homepageSections as $section): $isEnabled = homepage_section_enabled($section['key']); ?>
            <div class="schedule-row">
              <div class="schedule-day">
                <strong><?= e($section['label']) ?></strong>
              </div>
              <label class="switch" title="<?= $isEnabled ? 'Tampil' : 'Disembunyikan' ?>">
                <input class="toggle-input" type="checkbox" name="<?= e($section['key']) ?>" <?= $isEnabled ? 'checked' : '' ?>>
                <span class="switch-track"></span>
                <span class="switch-label <?= $isEnabled ? 'is-on' : 'is-off' ?>"><?= $isEnabled ? 'ON' : 'OFF' ?></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head"><h3>Jam Operasional</h3></div>
        <div class="card-content schedule-list">
          <?php foreach (day_rows() as $day): $row = $hours[$day['key']] ?? ['open' => '08:00', 'close' => '20:00', 'active' => 1]; ?>
            <div class="schedule-row">
              <div class="schedule-day">
                <strong><?= e($day['label']) ?></strong>
              </div>
              <div class="schedule-time">
                <input type="time" name="hours_open_<?= e($day['key']) ?>" value="<?= e($row['open'] ?? '08:00') ?>">
                <span>-</span>
                <input type="time" name="hours_close_<?= e($day['key']) ?>" value="<?= e($row['close'] ?? '20:00') ?>">
              </div>
              <label class="switch" title="<?= !empty($row['active']) ? 'Aktif' : 'Tutup' ?>">
                <input class="toggle-input" type="checkbox" name="hours_active_<?= e($day['key']) ?>" <?= !empty($row['active']) ? 'checked' : '' ?>>
                <span class="switch-track"></span>
                <span class="switch-label <?= !empty($row['active']) ? 'is-on' : 'is-off' ?>"><?= !empty($row['active']) ? 'AKTIF' : 'TUTUP' ?></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head"><h3>Blog / Cerita Homepage</h3></div>
        <div class="card-content">
          <div class="form-grid">
            <label>Label kecil <input name="blog_eyebrow" value="<?= e($blogSection['eyebrow']) ?>" placeholder="Sejak 2018"></label>
            <label>Judul <input name="blog_title" value="<?= e($blogSection['title']) ?>" placeholder="Dedikasi Artisan"></label>
            <label>Gambar <input name="blog_image" value="<?= e($blogSection['image']) ?>" placeholder="assets/pairing.png"></label>
            <label>Upload gambar <input type="file" name="blog_image_file" accept="image/png,image/jpeg,image/webp" id="blogImageFile"></label>
            <label>Teks tombol <input name="blog_button_label" value="<?= e($blogSection['button_label']) ?>" placeholder="Lihat katalog"></label>
          </div>
          <label style="margin-top:18px;">Isi cerita<textarea name="blog_body" rows="5"><?= e($blogSection['body']) ?></textarea></label>
          <div class="hero-preview" style="margin-top:18px;">
            <img id="blogPreview" src="<?= e($blogSection['image']) ?>" alt="Preview blog" style="width:100%; aspect-ratio:1.72; object-fit:cover; border-radius:16px; border:1px solid rgba(75,53,33,.12);">
          </div>
        </div>
      </section>
    </section>

    <section class="stack">
      <section class="card form-card">
        <div class="card-head">
          <h3>Opsi Pengiriman</h3>
          <button type="button" class="secondary-btn" id="addDeliveryOption">Tambah Opsi</button>
        </div>
        <div class="card-content option-list" id="deliveryOptionsList">
          <?php foreach ($deliveryOptions as $option): ?>
            <div class="option-card option-row">
              <div class="option-head">
                <div class="option-meta">
                  <div class="option-icon"><span class="material-symbols-outlined">local_shipping</span></div>
                  <div class="option-title">
                    <strong><?= e($option['label']) ?></strong>
                    <span><?= e($option['value']) ?></span>
                  </div>
                </div>
                <button type="button" class="ghost-btn remove-option">Hapus</button>
              </div>
              <div class="option-grid">
                <label>Key opsi<input name="delivery_option_value[]" value="<?= e($option['value']) ?>"></label>
                <label>Label<input name="delivery_option_label[]" value="<?= e($option['label']) ?>"></label>
                <label class="inline-check full"><input type="hidden" name="delivery_option_needs_address[]" value="<?= !empty($option['needs_address']) ? '1' : '0' ?>"><input type="checkbox" data-needs-address value="1" <?= !empty($option['needs_address']) ? 'checked' : '' ?>>Butuh alamat</label>
                <label class="full">Link Maps<input name="delivery_option_maps_url[]" value="<?= e($option['maps_url']) ?>" placeholder="https://maps.app.goo.gl/..."></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head"><h3>Ringkasan</h3></div>
        <div class="card-content kv-list">
          <div class="kv-item"><span>Senin</span><strong><?= e(($debugHours['mon']['active'] ?? 0) ? (($debugHours['mon']['open'] ?? '08:00') . ' - ' . ($debugHours['mon']['close'] ?? '20:00')) : 'Tutup') ?></strong></div>
          <div class="kv-item"><span>Setting utama</span><strong>Disimpan ke database</strong></div>
          <div class="kv-item"><span>Jam buka</span><strong>Per hari</strong></div>
          <div class="kv-item"><span>Delivery</span><strong>Dinamis</strong></div>
        </div>
      </section>
    </section>
  </form>
</div>

<template id="deliveryOptionTemplate">
  <div class="option-card option-row">
    <div class="option-head">
      <div class="option-meta">
        <div class="option-icon"><span class="material-symbols-outlined">local_shipping</span></div>
        <div class="option-title"><strong>Opsi baru</strong><span>custom</span></div>
      </div>
      <button type="button" class="ghost-btn remove-option">Hapus</button>
    </div>
    <div class="option-grid">
      <label>Key opsi<input name="delivery_option_value[]" placeholder="pickup"></label>
      <label>Label<input name="delivery_option_label[]" placeholder="Ambil Sendiri"></label>
      <label class="inline-check full"><input type="hidden" name="delivery_option_needs_address[]" value="0"><input type="checkbox" data-needs-address value="1">Butuh alamat</label>
      <label class="full">Link Maps<input name="delivery_option_maps_url[]" placeholder="https://maps.app.goo.gl/..."></label>
    </div>
  </div>
</template>

<script>
  const heroImageFile = document.getElementById('heroImageFile');
  const heroPreview = document.getElementById('heroPreview');
  if (heroImageFile && heroPreview) {
    heroImageFile.addEventListener('change', () => {
      const file = heroImageFile.files && heroImageFile.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => { heroPreview.src = String(reader.result || ''); };
      reader.readAsDataURL(file);
    });
  }

  const blogImageFile = document.getElementById('blogImageFile');
  const blogPreview = document.getElementById('blogPreview');
  if (blogImageFile && blogPreview) {
    blogImageFile.addEventListener('change', () => {
      const file = blogImageFile.files && blogImageFile.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => { blogPreview.src = String(reader.result || ''); };
      reader.readAsDataURL(file);
    });
  }

  const list = document.getElementById('deliveryOptionsList');
  const addBtn = document.getElementById('addDeliveryOption');
  const template = document.getElementById('deliveryOptionTemplate');

  function syncNeedsAddress(row) {
    const hidden = row.querySelector('input[type="hidden"][name="delivery_option_needs_address[]"]');
    const checkbox = row.querySelector('input[data-needs-address]');
    if (hidden && checkbox) hidden.value = checkbox.checked ? '1' : '0';
  }

  function bindRow(row) {
    const removeButton = row.querySelector('.remove-option');
    const checkbox = row.querySelector('input[data-needs-address]');
    if (checkbox) checkbox.addEventListener('change', () => syncNeedsAddress(row));
    if (removeButton) removeButton.addEventListener('click', () => { if (list.querySelectorAll('.option-row').length > 1) row.remove(); });
    syncNeedsAddress(row);
  }

  list.querySelectorAll('.option-row').forEach(bindRow);
  addBtn.addEventListener('click', () => { const row = template.content.firstElementChild.cloneNode(true); list.appendChild(row); bindRow(row); });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
