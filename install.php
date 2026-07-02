<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $adminPassword = (string) ($_POST['admin_password'] ?? '');
        $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email admin wajib valid.');
        }

        if (strlen($adminPassword) < 8) {
            throw new RuntimeException('Password admin minimal 8 karakter.');
        }

        $db = $config['db'];
        $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $db['host'], $db['port'], $db['charset']);
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['database']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . str_replace('`', '', $db['database']) . '`');
        $pdo->exec(file_get_contents(__DIR__ . '/database.sql'));
        ensurePerformanceIndexes($pdo, (string) $db['database']);

        $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash)');
        $stmt->execute([$_POST['admin_name'] ?: 'Administrator', $adminEmail, $hash]);

        seedData($pdo);
        $message = 'Install berhasil. Silakan login ke backoffice.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

function ensurePerformanceIndexes(PDO $pdo, string $database): void
{
    $indexes = [
        'categories' => ['idx_categories_active_sort' => '(is_active, sort_order)'],
        'products' => ['idx_products_active_popular' => '(is_active, is_popular)', 'idx_products_category' => '(category_id)'],
        'promos' => ['idx_promos_active_type' => '(is_active, type)'],
        'blogs' => ['idx_blogs_active_featured' => '(is_active, is_featured)'],
        'orders' => ['idx_orders_status_created' => '(status, created_at)'],
        'order_items' => ['idx_order_items_order' => '(order_id)'],
        'stock_movements' => ['idx_stock_movements_product_created' => '(product_id, created_at)'],
    ];
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?');
    foreach ($indexes as $table => $tableIndexes) {
        foreach ($tableIndexes as $name => $columns) {
            $check->execute([$database, $table, $name]);
            if (!(int) $check->fetchColumn()) {
                $pdo->exec("ALTER TABLE `$table` ADD INDEX `$name` $columns");
            }
        }
    }
}

function seedData(PDO $pdo): void
{
    $categories = [
        ['Roti Manis', 'roti-manis', 'croissant', 1],
        ['Roti Tawar', 'roti-tawar', 'bread', 2],
        ['Croissant', 'croissant', 'croissant', 3],
        ['Camilan', 'camilan', 'snack', 4],
        ['Promo', 'promo', 'tag', 5],
        ['Kopi', 'kopi', 'coffee', 6],
    ];
    foreach ($categories as $category) {
        $stmt = $pdo->prepare('INSERT INTO categories (name, slug, icon, sort_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
        $stmt->execute($category);
    }

    $categoryIds = [];
    foreach ($pdo->query('SELECT id, slug FROM categories') as $row) {
        $categoryIds[$row['slug']] = $row['id'];
    }

    $products = [
        ['Almond Croissant', 'almond-croissant', 'Pastry lapis dengan almond cream.', "Tepung terigu\nbutter\nalmond\ntelur\ngula", '1 pcs / 85 gram', '1 hari suhu ruang', 28000, 24, 'ready', 'assets/almond-croissant.png', 1, $categoryIds['croissant']],
        ['Cinnamon Roll', 'cinnamon-roll', 'Roti gulung kayu manis dan glaze lembut.', "Tepung terigu\nkayu manis\nbutter\ngula\nsusu", '1 pcs / 95 gram', '2 hari suhu ruang', 22000, 18, 'ready', 'assets/cinnamon-roll.png', 1, $categoryIds['roti-manis']],
        ['Seeded Bread', 'seeded-bread', 'Roti biji-bijian untuk sarapan.', "Tepung gandum\nbiji bunga matahari\nwijen\nragi", '1 loaf / 450 gram', '3 hari suhu ruang', 15000, 12, 'ready', 'assets/seeded-bread.png', 1, $categoryIds['roti-tawar']],
        ['Classic Sourdough', 'classic-sourdough', 'Sourdough fermentasi lambat.', "Tepung gandum\nragi alami\nair\ngaram", '1 loaf / 500 gram', '3 hari suhu ruang', 45000, 6, 'limited', 'assets/sourdough.png', 1, $categoryIds['roti-tawar']],
        ['Kopi Latte', 'kopi-latte', 'Espresso dan susu creamy.', "Espresso\nsusu\ngula cair opsional", '1 cup / 250 ml', 'Minum segera', 24000, 30, 'ready', 'assets/pairing.png', 0, $categoryIds['kopi']],
        ['Bundle Sarapan', 'bundle-sarapan', 'Paket kopi dan roti pagi.', "Roti pilihan\nkopi\nsusu\nbutter", '1 paket', 'Minum segera, roti 1 hari', 35000, 10, 'ready', 'assets/bundle-promo.png', 0, $categoryIds['promo']],
    ];

    foreach ($products as $product) {
        $stmt = $pdo->prepare('INSERT INTO products (name, slug, description, ingredients, package_info, shelf_life, price, stock, stock_status, image, is_popular, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock), image = VALUES(image), ingredients = VALUES(ingredients), package_info = VALUES(package_info), shelf_life = VALUES(shelf_life)');
        $stmt->execute($product);
    }

    $promos = [
        ['Promo Special', 'Diskon 20% khusus croissant pagi.', 'assets/hero-promo.png', 'hero', 20],
        ['Bundle Sarapan', 'Kopi + roti hanya Rp 35rb', 'assets/bundle-promo.png', 'small', 0],
        ['Flash Sale', 'Diskon 30%', 'assets/flash-sale.png', 'small', 30],
    ];
    foreach ($promos as $promo) {
        $stmt = $pdo->prepare('SELECT id FROM promos WHERE title = ? AND type = ? LIMIT 1');
        $stmt->execute([$promo[0], $promo[3]]);
        $promoId = $stmt->fetchColumn();

        if ($promoId) {
            $stmt = $pdo->prepare('UPDATE promos SET subtitle = ?, image = ?, discount_percent = ?, is_active = 1 WHERE id = ?');
            $stmt->execute([$promo[1], $promo[2], $promo[4], $promoId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO promos (title, subtitle, image, type, discount_percent) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute($promo);
        }
    }

    $stmt = $pdo->prepare('INSERT INTO blogs (title, slug, eyebrow, excerpt, content, image, is_featured, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), eyebrow = VALUES(eyebrow), excerpt = VALUES(excerpt), content = VALUES(content), image = VALUES(image), is_featured = VALUES(is_featured), is_active = VALUES(is_active)');
    $stmt->execute([
        'Dedikasi Artisan',
        'dedikasi-artisan',
        'Sejak 2018',
        'Setiap adonan kami uleni dengan tangan, lalu difermentasi perlahan agar rasa dan teksturnya tetap hidup sampai ke meja pelanggan.',
        'Setiap adonan kami uleni dengan tangan, lalu difermentasi perlahan agar rasa dan teksturnya tetap hidup sampai ke meja pelanggan.',
        'assets/pairing.png',
        1,
        1,
    ]);

    $settings = [
        'store_name' => 'Levia Bakery',
        'store_branch' => 'Gading Serpong',
        'store_address' => 'Gading Serpong, Tangerang',
        'store_hours_json' => json_encode([
            'mon' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
            'tue' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
            'wed' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
            'thu' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
            'fri' => ['open' => '08:00', 'close' => '20:00', 'active' => 1],
            'sat' => ['open' => '09:00', 'close' => '21:00', 'active' => 1],
            'sun' => ['open' => '00:00', 'close' => '00:00', 'active' => 0],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'whatsapp' => '6281234567890',
        'whatsapp_chat_text' => 'Halo Levia Bakery, saya mau tanya menu hari ini.',
        'delivery_options_json' => json_encode([
            ['value' => 'pickup', 'label' => 'Ambil Sendiri', 'needs_address' => false, 'maps_url' => ''],
            ['value' => 'home_bake', 'label' => 'Home Bake', 'needs_address' => true, 'maps_url' => ''],
            ['value' => 'gosend', 'label' => 'Gosend / Gojek', 'needs_address' => true, 'maps_url' => ''],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'google_maps_url' => '',
    ];
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([$key, $value]);
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install Levia Bakery</title>
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="install-page">
  <main class="login-card">
    <h1>Install Levia Bakery</h1>
    <p>Pastikan `config.php` sudah berisi kredensial MySQL hosting.</p>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?> <a href="admin/login.php">Login admin</a></div><?php endif; ?>
    <?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <label>Nama Admin <input name="admin_name" value="Administrator"></label>
      <label>Email Admin <input type="email" name="admin_email" required placeholder="admin@domain-anda.com"></label>
      <label>Password Admin <input type="password" name="admin_password" minlength="8" required placeholder="Minimal 8 karakter"></label>
      <button class="primary-btn" type="submit">Buat Database & Seed Data</button>
    </form>
  </main>
</body>
</html>





