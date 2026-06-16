<?php

require __DIR__ . '/../lib/db.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$_POST['email'] ?? '']);
    $admin = $stmt->fetch();
    if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        redirect('admin/index.php');
    }
    $error = 'Email atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Backoffice</title>
  <link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="install-page">
  <main class="login-card">
    <h1>Levia Daily Catalog</h1>
    <p>Kelola katalog harian, ketersediaan, promo, dan tujuan WhatsApp.</p>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <label>Email <input type="email" name="email" required></label>
      <label>Password <input type="password" name="password" placeholder="Masukkan password admin" required></label>
      <button class="primary-btn" type="submit">Login</button>
    </form>
  </main>
</body>
</html>

