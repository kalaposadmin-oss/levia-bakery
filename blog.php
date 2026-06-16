<?php

require __DIR__ . '/lib/db.php';

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
$title = $blog ? (string) $blog['title'] : 'Blog tidak ditemukan';
$image = $blog && !empty($blog['image']) ? (string) $blog['image'] : 'assets/pairing.png';
$eyebrow = $blog ? (string) ($blog['eyebrow'] ?: 'Cerita Levia') : 'Blog';
$content = $blog ? trim((string) ($blog['content'] ?: $blog['excerpt'])) : 'Blog belum tersedia.';
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
  <link rel="stylesheet" href="assets/site.css?v=20260616g">
</head>
<body>
  <main class="storefront blog-detail-page" id="top">
    <nav class="blog-detail-nav"><a href="index.php#blogSection"><span class="ui-icon icon-back" aria-hidden="true"></span>Kembali</a></nav>
    <article class="blog-detail-card">
      <img class="blog-detail-image" src="<?= e($image) ?>" alt="<?= e($title) ?>">
      <div class="blog-detail-copy">
        <small><?= e($eyebrow) ?></small>
        <h1><?= e($title) ?></h1>
        <div class="blog-detail-content">
          <?php foreach (preg_split('/\R{2,}/', $content) ?: [] as $paragraph): ?>
            <?php if (trim($paragraph) !== ''): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </article>
  </main>

  <nav class="bottom-nav">
    <a href="index.php#top"><span class="ui-icon icon-home" aria-hidden="true"></span><span>Beranda</span></a>
    <a href="index.php#catalogSection"><span class="ui-icon icon-catalog" aria-hidden="true"></span><span>Katalog</span></a>
    <a class="is-active" href="index.php#blogSection"><span class="ui-icon icon-blog" aria-hidden="true"></span><span>Blog</span></a>
    <a href="#profile"><span class="ui-icon icon-profile" aria-hidden="true"></span><span>Profile</span></a>
  </nav>
</body>
</html>


