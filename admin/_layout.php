<?php

$currentAdmin = $currentAdmin ?? require_admin();
$title = $title ?? 'Backoffice';
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

$navigation = [
    ['file' => 'index.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
    ['file' => 'categories.php', 'label' => 'Kategori', 'icon' => 'category'],
    ['file' => 'products.php', 'label' => 'Katalog Produk', 'icon' => 'inventory_2'],
    ['file' => 'stock.php', 'label' => 'Ketersediaan Harian', 'icon' => 'event_available'],
    ['file' => 'promos.php', 'label' => 'Promo/Banner', 'icon' => 'campaign'],
    ['file' => 'blogs.php', 'label' => 'Blog', 'icon' => 'article'],
    ['file' => 'orders.php', 'label' => 'Request WA', 'icon' => 'forum'],
    ['file' => 'settings.php', 'label' => 'Setting Toko', 'icon' => 'settings'],
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> - Levia Daily Catalog</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="admin-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <h1>Levia DC</h1>
      <p>Daily Catalog</p>
    </div>

    <nav class="sidebar-nav">
      <?php foreach ($navigation as $item): ?>
        <a href="<?= e($item['file']) ?>" class="nav-link <?= $currentPage === $item['file'] ? 'is-active' : '' ?>">
          <span class="material-symbols-outlined"><?= e($item['icon']) ?></span>
          <span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <a href="../index.php" target="_blank" rel="noopener noreferrer" class="nav-link nav-link-secondary">
        <span class="material-symbols-outlined">storefront</span>
        <span>Lihat Catalog</span>
      </a>
      <a href="logout.php" class="nav-link nav-link-logout">
        <span class="material-symbols-outlined">logout</span>
        <span>Logout</span>
      </a>
    </div>
  </aside>

  <main class="admin-main">
    <header class="admin-top">
      <div class="admin-heading">
        <small>Backoffice</small>
        <div class="heading-line"></div>
        <h2><?= e($title) ?></h2>
      </div>
      <div class="admin-profile">
        <div class="admin-profile-copy">
          <strong><?= e($currentAdmin['name'] ?? 'Administrator') ?></strong>
          <span>Store Admin</span>
        </div>
        <div class="admin-avatar" aria-hidden="true">
          <?= e(strtoupper(substr((string) ($currentAdmin['name'] ?? 'A'), 0, 1))) ?>
        </div>
      </div>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert success"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <?= $content ?>
  </main>
</body>
</html>
