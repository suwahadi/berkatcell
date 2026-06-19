# berkatcell

Aplikasi e-commerce berbasis **Laravel** untuk berkatcell.

## Fitur

- Katalog produk, varian, kategori, dan slide/banner
- Keranjang, voucher, dan checkout
- Pembayaran via **Midtrans Snap**
- Ongkos kirim via **RajaOngkir (Komerce)**
- Notifikasi email via **Brevo**
- Wishlist, halaman dinamis (pages), dan panel admin
- Autentikasi (termasuk passkey)

## Persyaratan

- PHP 8.3+
- Composer 2
- Node.js 22+
- MySQL 8

## Instalasi

```bash
# 1. Install dependency
composer install
npm install

# 2. Siapkan environment
cp .env.example .env
php artisan key:generate

# 3. Isi kredensial di .env (DB, Midtrans, Brevo, RajaOngkir)

# 4. Migrasi & seed database
php artisan migrate --seed

# 5. Build asset & jalankan
npm run dev
php artisan serve
```

## Konfigurasi `.env`

Salin dari `.env.example` lalu isi nilai berikut dengan kredensial Anda sendiri:

- `DB_*` — koneksi database
- `MIDTRANS_*` — payment gateway
- `BREVO_API_KEY` — pengiriman email
- `RAJAONGKIR_API_KEY` — ongkos kirim

> ⚠️ **Jangan pernah** commit file `.env` ke repository. File ini sudah diabaikan oleh `.gitignore`.

## Testing & Code Style

```bash
./vendor/bin/phpunit   # menjalankan test
composer lint          # menjalankan Pint
```
