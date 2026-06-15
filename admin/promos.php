<?php

require __DIR__ . '/../lib/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $titleInput = trim((string) ($_POST['title'] ?? ''));
    $type = (string) ($_POST['type'] ?? 'small');
    if ($titleInput === '') {
        $_SESSION['flash'] = 'Judul promo wajib diisi.';
        redirect('admin/promos.php');
    }
    if (!in_array($type, ['hero', 'small'], true)) {
        $_SESSION['flash'] = 'Tipe promo tidak valid.';
        redirect('admin/promos.php');
    }

    $image = upload_image('image', $_POST['current_image'] ?? null);
    $data = [
        $titleInput,
        trim((string) ($_POST['subtitle'] ?? '')),
        $image,
        $type,
        max(0, min(100, (int) ($_POST['discount_percent'] ?? 0))),
        isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($id) {
        $data[] = $id;
        db()->prepare('UPDATE promos SET title=?, subtitle=?, image=?, type=?, discount_percent=?, is_active=? WHERE id=?')->execute($data);
    } else {
        db()->prepare('INSERT INTO promos (title, subtitle, image, type, discount_percent, is_active) VALUES (?, ?, ?, ?, ?, ?)')->execute($data);
    }
    $_SESSION['flash'] = 'Promo berhasil disimpan.';
    redirect('admin/promos.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM promos WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}
$promos = db()->query('SELECT * FROM promos ORDER BY id DESC')->fetchAll();
$title = 'Promo & Banner';
ob_start();
?>
<section class="card form-card">
  <h3><?= $edit ? 'Edit Promo' : 'Tambah Promo' ?></h3>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e((string) ($edit['id'] ?? 0)) ?>">
    <input type="hidden" name="current_image" value="<?= e($edit['image'] ?? '') ?>">
    <div class="form-grid">
      <label>Judul <input name="title" value="<?= e($edit['title'] ?? '') ?>" required></label>
      <label>Subtitle <input name="subtitle" value="<?= e($edit['subtitle'] ?? '') ?>"></label>
      <label>Tipe <select name="type"><option value="hero" <?= ($edit['type'] ?? '') === 'hero' ? 'selected' : '' ?>>Hero</option><option value="small" <?= ($edit['type'] ?? 'small') === 'small' ? 'selected' : '' ?>>Small</option></select></label>
      <label>Diskon % <input type="number" name="discount_percent" min="0" max="100" value="<?= e((string) ($edit['discount_percent'] ?? 0)) ?>"></label>
      <label>Gambar <input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label>
    </div>
    <div class="checks"><label><input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Aktif</label></div>
    <button class="primary-btn" type="submit">Simpan Promo</button>
  </form>
</section>
<section class="card">
  <h3>Daftar Promo</h3>
  <table><thead><tr><th>Judul</th><th>Tipe</th><th>Diskon</th><th>Aktif</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($promos as $promo): ?><tr><td><?= e($promo['title']) ?></td><td><?= e($promo['type']) ?></td><td><?= e((string) $promo['discount_percent']) ?>%</td><td><?= $promo['is_active'] ? 'Ya' : 'Tidak' ?></td><td><a href="promos.php?edit=<?= e((string) $promo['id']) ?>">Edit</a></td></tr><?php endforeach; ?>
  </tbody></table>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
