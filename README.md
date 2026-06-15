# Levia Bakery System

Aplikasi order Levia Bakery mandiri dengan PHP + MySQL. Folder ini terpisah dari Kalakasir utama.

## Fitur

- Storefront pelanggan seperti desain mobile Levia Bakery.
- Produk dibaca dari database.
- Keranjang dan checkout.
- Pesanan tersimpan ke database.
- Stok otomatis berkurang saat checkout.
- Backoffice untuk dashboard, produk, stok, promo/banner, pesanan, dan setting toko.

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
- `api/order-create.php`: endpoint checkout.
- `database.sql`: struktur tabel.
- `config.php`: koneksi database.
- `uploads/`: file gambar baru dari backoffice.
