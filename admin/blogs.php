<?php

require __DIR__ . '/../lib/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    $titleInput = trim((string) ($_POST['title'] ?? ''));
    if ($titleInput === '') {
        $_SESSION['flash'] = 'Judul blog wajib diisi.';
        redirect($id ? 'admin/blogs.php?edit=' . $id : 'admin/blogs.php');
    }

    $slugBase = slugify($titleInput);
    $slug = $slugBase;
    $suffix = 2;
    do {
        $stmt = db()->prepare('SELECT id FROM blogs WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $id]);
        $exists = $stmt->fetchColumn();
        if ($exists) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
        }
    } while ($exists);

    $image = upload_image('image', $_POST['current_image'] ?? null);
    $data = [
        $titleInput,
        $slug,
        trim((string) ($_POST['eyebrow'] ?? '')),
        trim((string) ($_POST['excerpt'] ?? '')),
        trim((string) ($_POST['content'] ?? '')),
        $image,
        isset($_POST['is_featured']) ? 1 : 0,
        isset($_POST['is_active']) ? 1 : 0,
    ];

    if (!empty($_POST['is_featured'])) {
        db()->exec('UPDATE blogs SET is_featured = 0');
    }

    if ($id) {
        $data[] = $id;
        db()->prepare('UPDATE blogs SET title=?, slug=?, eyebrow=?, excerpt=?, content=?, image=?, is_featured=?, is_active=? WHERE id=?')->execute($data);
    } else {
        db()->prepare('INSERT INTO blogs (title, slug, eyebrow, excerpt, content, image, is_featured, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute($data);
    }

    $_SESSION['flash'] = 'Blog berhasil disimpan.';
    redirect('admin/blogs.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM blogs WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$blogs = db()->query('SELECT * FROM blogs ORDER BY is_featured DESC, id DESC')->fetchAll();
$title = 'Blog';
ob_start();
?>
<section class="card form-card">
  <h3><?= $edit ? 'Edit Blog' : 'Tulis Blog' ?></h3>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e((string) ($edit['id'] ?? 0)) ?>">
    <input type="hidden" name="current_image" value="<?= e($edit['image'] ?? '') ?>">
    <div class="form-grid">
      <label>Judul <input name="title" value="<?= e($edit['title'] ?? '') ?>" required></label>
      <label>Label kecil <input name="eyebrow" value="<?= e($edit['eyebrow'] ?? 'Cerita Levia') ?>" placeholder="Cerita Levia"></label>
      <label>Ringkasan <input name="excerpt" value="<?= e($edit['excerpt'] ?? '') ?>" placeholder="Ringkasan singkat untuk homepage"></label>
      <label>Gambar <input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label>
    </div>
    <label>Isi blog<textarea name="content" rows="8" placeholder="Tulis cerita blog di sini..."><?= e($edit['content'] ?? '') ?></textarea></label>
    <div class="checks">
      <label><input type="checkbox" name="is_featured" value="1" <?= ($edit['is_featured'] ?? 0) ? 'checked' : '' ?>> Tampilkan di homepage</label>
      <label><input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Aktif</label>
    </div>
    <button class="primary-btn" type="submit">Simpan Blog</button>
    <?php if ($edit): ?><a class="secondary-btn" href="blogs.php">Batal</a><?php endif; ?>
  </form>
</section>

<section class="card">
  <div class="card-head"><h3>Daftar Blog</h3></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Judul</th><th>Label</th><th>Homepage</th><th>Aktif</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($blogs as $blog): ?>
          <tr>
            <td class="product-cell"><img src="../<?= e($blog['image'] ?: 'assets/pairing.png') ?>" alt=""><span><?= e($blog['title']) ?></span></td>
            <td><?= e($blog['eyebrow']) ?></td>
            <td><?= $blog['is_featured'] ? 'Ya' : 'Tidak' ?></td>
            <td><?= $blog['is_active'] ? 'Ya' : 'Tidak' ?></td>
            <td class="actions">
              <a href="blogs.php?edit=<?= e((string) $blog['id']) ?>">Edit</a>
              <form method="post" action="blog-delete.php" onsubmit="return confirm('Hapus blog ini?');">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $blog['id']) ?>">
                <button type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
