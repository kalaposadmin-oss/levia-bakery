<?php

require __DIR__ . '/lib/db.php';
ensure_blog_schema();

$slug = trim((string) ($_GET['slug'] ?? ''));
$blog = null;

try {
    if ($slug !== '') {
        $stmt = db()->prepare('SELECT * FROM blogs WHERE slug = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$slug]);
        $blog = $stmt->fetch();
    }

    if (!$blog) {
        $blog = db()->query('SELECT * FROM blogs WHERE is_active = 1 ORDER BY is_featured DESC, id DESC LIMIT 1')->fetch();
    }
} catch (Throwable $e) {
    $blog = null;
}

if (!$blog) {
    http_response_code(404);
}

$storeName = setting('store_name', 'Levia Bakery');
$storeBranch = setting('store_branch', 'Gading Serpong');
$storeAddress = setting('store_address', '');
$title = $blog ? (string) $blog['title'] : 'Blog tidak ditemukan';
$image = $blog && !empty($blog['image']) ? (string) $blog['image'] : 'assets/pairing.png';
$eyebrow = $blog ? (string) ($blog['eyebrow'] ?: 'Cerita Levia') : 'Blog';
$content = $blog ? sanitize_blog_html((string) ($blog['content'] ?: $blog['excerpt'])) : '<p>Blog belum tersedia.</p>';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> - <?= e($storeName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/site.css?v=20260702g">
</head>
<body>
  <main class="storefront blog-detail-page" id="top">
    <div class="top-info-bar">
      <div class="top-info-item"><span class="ui-icon icon-location" aria-hidden="true"></span><?= e($storeAddress !== '' ? $storeAddress : $storeBranch) ?></div>
      <div class="top-info-item"><span class="ui-icon icon-status" aria-hidden="true"></span>Cerita <?= e($storeName) ?></div>
    </div>
    <header class="topbar blog-topbar">
      <div class="address-row">
        <div class="address-copy"><small>Membaca dari:</small><a class="plain-button" href="index.php#top"><?= e($storeBranch) ?> <span class="ui-icon icon-chevron" aria-hidden="true"></span></a></div>
        <a class="bag-button" href="index.php#top" aria-label="Kembali ke beranda"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg></a>
      </div>
      <a class="search-field blog-catalog-link" href="index.php#catalogSection"><span class="ui-icon icon-search" aria-hidden="true"></span><span>Lihat dan cari katalog roti...</span></a>
    </header>
    <nav class="blog-detail-nav"><a href="index.php#blogSection"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg>Kembali</a></nav>
    <article class="blog-detail-card">
      <img class="blog-detail-image" src="<?= e($image) ?>" alt="<?= e($title) ?>">
      <div class="blog-detail-copy">
        <small><?= e($eyebrow) ?></small>
        <h1><?= e($title) ?></h1>
        <div class="blog-detail-content">
          <?= $content ?>
        </div>
      </div>
    </article>
  </main>

  <nav class="bottom-nav">
    <a href="index.php#top"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg><span>Beranda</span></a>
    <a href="index.php#catalogSection"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v16"/></svg><span>Katalog</span></a>
    <a class="is-active" href="index.php#blogSection"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A3.5 3.5 0 0 1 7.5 2H11a2 2 0 0 1 2 2v17a3 3 0 0 0-3-3H4Z"/><path d="M20 5.5A3.5 3.5 0 0 0 16.5 2H15a2 2 0 0 0-2 2v17a3 3 0 0 1 3-3h4Z"/></svg><span>Blog</span></a>
    <a href="#profile"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span>Profil</span></a>
  </nav>
</body>
</html>


