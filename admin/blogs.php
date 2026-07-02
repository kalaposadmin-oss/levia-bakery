<?php

require __DIR__ . '/../lib/db.php';
require_admin();
ensure_blog_schema();

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

    $image = upload_image('image', $_POST['current_image'] ?? null, ['prefix' => 'blog', 'max_width' => 1200, 'max_height' => 900, 'quality' => 82]);
    $data = [
        $titleInput,
        $slug,
        trim((string) ($_POST['eyebrow'] ?? '')),
        trim((string) ($_POST['excerpt'] ?? '')),
        sanitize_blog_html((string) ($_POST['content'] ?? '')),
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

    clear_storefront_cache();
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
$editorContent = $edit ? sanitize_blog_html((string) ($edit['content'] ?? '')) : '<p><br></p>';
$editorContent = str_replace('src="uploads/', 'src="../uploads/', $editorContent);
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
      <label>Gambar <input type="file" name="image" accept="image/png,image/jpeg,image/webp"><small>Otomatis di-resize dan dikompres ke WebP. Maksimal 12 MB.</small></label>
    </div>
    <div class="blog-editor-shell" data-blog-editor data-upload-endpoint="blog-image-upload.php" data-csrf="<?= e(csrf_token()) ?>">
      <div class="blog-editor-toolbar" role="toolbar" aria-label="Format artikel">
        <select data-block-format aria-label="Jenis paragraf"><option value="p">Paragraf</option><option value="h2">Judul Besar</option><option value="h3">Subjudul</option></select>
        <select data-font-family aria-label="Jenis font"><option value="Plus Jakarta Sans">Jakarta Sans</option><option value="Playfair Display">Playfair</option><option value="Georgia">Georgia</option><option value="Arial">Arial</option><option value="Courier New">Monospace</option></select>
        <span class="toolbar-divider"></span>
        <button type="button" data-command="bold" title="Tebal"><strong>B</strong></button>
        <button type="button" data-command="italic" title="Miring"><em>I</em></button>
        <button type="button" data-command="underline" title="Garis bawah"><u>U</u></button>
        <button type="button" data-command="insertUnorderedList" title="Daftar poin">• List</button>
        <button type="button" data-command="insertOrderedList" title="Daftar nomor">1. List</button>
        <button type="button" data-block="blockquote" title="Kutipan">❝</button>
        <span class="toolbar-divider"></span>
        <button type="button" data-command="justifyLeft" title="Rata kiri">≡</button>
        <button type="button" data-command="justifyCenter" title="Rata tengah">≡</button>
        <button type="button" data-command="justifyRight" title="Rata kanan">≡</button>
        <button type="button" data-link title="Tambahkan tautan">🔗</button>
        <button type="button" class="toolbar-media" data-image-trigger title="Sisipkan foto">＋ Foto</button>
        <input type="file" data-image-input accept="image/png,image/jpeg,image/webp" hidden>
      </div>
      <div class="blog-editor-media-options" data-image-options hidden>
        <div class="image-options-head"><strong>Atur Foto</strong><button type="button" data-image-options-close aria-label="Tutup">×</button></div>
        <label class="image-size-control">Ukuran <input type="range" min="20" max="100" step="5" value="70" data-image-size><output data-image-size-output>70%</output></label>
        <div class="image-position-buttons"><span>Posisi</span><button type="button" data-image-align="image-left">Kiri</button><button type="button" data-image-align="image-center">Tengah</button><button type="button" data-image-align="image-right">Kanan</button></div>
        <label>Teks alternatif <input type="text" data-image-alt placeholder="Jelaskan isi foto"></label>
        <label>Caption <input type="text" data-image-caption placeholder="Keterangan di bawah foto"></label>
        <button type="button" class="image-delete-button" data-image-delete>Hapus foto</button>
      </div>
      <div class="blog-editor-canvas" contenteditable="true" data-editor-canvas data-placeholder="Mulai tulis cerita Levia di sini..."><?= $editorContent ?></div>
      <textarea name="content" data-editor-output hidden></textarea>
      <div class="blog-editor-status"><span data-word-count>0 kata</span><span data-upload-status>Foto maksimal 12 MB, otomatis WebP</span></div>
    </div>
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
$pageScripts = ['../assets/admin-blog-editor.js?v=20260702d'];
require __DIR__ . '/_layout.php';
