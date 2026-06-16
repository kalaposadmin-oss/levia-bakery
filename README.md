# Levia Bakery Daily Catalog

Aplikasi katalog harian Levia Bakery dengan PHP + MySQL. Web ini menjadi etalase menu dan pintu masuk obrolan WhatsApp, bukan pengganti sistem kasir utama.

## Fitur

- Storefront pelanggan seperti desain mobile Levia Bakery.
- Produk dibaca dari database.
- Keranjang request menu untuk diteruskan ke WhatsApp admin.
- Request WA tersimpan sebagai arsip ringan di database.
- Indikator ketersediaan harian tetap ada, tetapi stok final dikonfirmasi lewat WhatsApp.
- Backoffice untuk dashboard, katalog produk, ketersediaan harian, promo/banner, arsip request WA, dan setting toko.

## Cara install di hosting

1. Upload isi folder `v2` ke hosting.
2. Buat database MySQL di cPanel/hosting.
3. Copy `config.example.php` menjadi `config.php`, lalu edit:
   - `database`
   - `username`
   - `password`
   - `host`
   - `base_url` jika aplikasi berada di subfolder.
4. Buka `https://domain-anda.com/install.php`.
5. Isi akun admin awal dengan password minimal 8 karakter, lalu klik install.
6. Login backoffice di `https://domain-anda.com/admin/login.php`.
7. Setelah install di hosting produksi, hapus atau rename `install.php`.

Pastikan folder `uploads/` dan `storage/sessions/` writable oleh PHP hosting.

## Catatan GitHub

- Jangan commit `config.php`; gunakan `config.example.php` sebagai template.
- Jangan commit isi `storage/sessions/` karena berisi session runtime.
- Jangan commit isi `uploads/` kecuali memang ingin menjadikannya asset bawaan aplikasi.

## Struktur penting

- `index.php`: storefront pelanggan.
- `admin/`: backoffice.
- `api/order-create.php`: endpoint request WA.
- `database.sql`: struktur tabel.
- `config.php`: koneksi database.
- `uploads/`: file gambar baru dari backoffice.
