# PRODUCT REQUIREMENTS DOCUMENT (PRD)
## Sistem E-Commerce Handphone & Gadget — Berkat Cell

---

## 1. Dokumen Kontrol & Meta Spesifikasi

| Atribut | Spesifikasi Teknis / Kebijakan |
| :--- | :--- |
| **Nama Proyek** | E-Commerce Platform Berkat Cell |
| **Paket** | Standar — maks. 30 produk, maks. 6 kategori |
| **Target Framework** | Laravel Framework 13.x |
| **Target UI Engine** | Livewire 4.x (Thin Controller Pattern, Single-File Components) |
| **Versi PHP** | PHP 8.3x (Strict Types Enabled) |
| **Mesin Database** | MySQL 8.x (InnoDB Engine, No SoftDeletes) |
| **Lokalisasi Bahasa** | Bahasa Indonesia Baku (100% Frontend & Notifikasi) |
| **Format Tanggal** | `j F Y H:i:s` (Contoh: **17 Juni 2026 09:39:12**) |
| **Format Mata Uang** | `Rp ` dengan pemisah ribuan titik (Contoh: **Rp 2.500.000**) |
| **Integrasi Pembayaran** | Midtrans Snap (Payment Gateway) |
| **Integrasi Logistik** | RajaOngkir (Komerce) — hingga tingkat Kecamatan/Kelurahan |
| **Integrasi Notifikasi**| Brevo API v3 (Transactional Mail via Queue System) |
| **Domain** | berkatcell.com |

---

## 2. Visi Bisnis & Profil Perusahaan

**Berkat Cell** adalah penyedia layanan jual beli handphone terpercaya yang menawarkan berbagai pilihan gadget berkualitas dengan harga terbaik. Toko mengutamakan pelayanan cepat, transaksi aman, serta kemudahan berbelanja untuk memenuhi kebutuhan pelanggan.

**Target Pembeli:** masyarakat umum, pembeli gadget online, serta pelanggan yang ingin mengecek ketersediaan warna atau stok HP secara online.

### Prinsip Operasional Utama:
1. **Pelayanan Cepat & Transaksi Aman:** memberikan pengalaman belanja yang mudah, cepat, dan terpercaya.
2. **Katalog Lengkap & Real-Time:** pembeli dapat melihat ketersediaan stok, harga, kapasitas RAM/penyimpanan, dan varian warna HP secara langsung di website.
3. **Harga Kompetitif:** menawarkan berbagai pilihan merek gadget dengan varian warna dan memori yang lengkap pada harga yang bersaing.

### Data Kontak & Operasional
| Atribut | Nilai |
| :--- | :--- |
| **Telepon / WhatsApp** | 0877-7600-6060 |
| **Email** | berkatcell111777@gmail.com |
| **Alamat** | Jl. Rw. Bebek II No.08 18, RT.4/RW.11, Penjaringan, Kec. Penjaringan, Jakarta Utara, DKI Jakarta |
| **Jam Operasional** | Setiap hari, 09.00 – 21.00 WIB |
| **Pesan WA Default** | "Halo BerkatCell saya mau order, mohon info lebih lanjut" |

---

## 3. Arsitektur Sistem & Aturan Teknis Mutlak

Dokumen ini disusun sebagai panduan utama bagi **Claude Code** atau AI Coding Agent lainnya. Aturan di bawah ini tidak boleh dilanggar dalam skenario implementasi kode apa pun.

### 3.1. Pemisahan Layer (Service Pattern)
* **Livewire sebagai Thin Controller:** Komponen Livewire 4.x hanya diperbolehkan mengelola status UI (*state binding*), menerima input pengguna, menjalankan validasi form dasar, dan melakukan *render* tampilan.
* **Service Layer sebagai Core Logic:** Seluruh logika bisnis inti seperti kalkulasi diskon, validasi stok, pemrosesan pembayaran, manipulasi database transaksional, dan interaksi API luar WAJIB diletakkan di dalam kelas Service terpisah di bawah namespace `App\Services`.
* *Dilarang keras melakukan kueri database yang kompleks atau kalkulasi finansial langsung di dalam komponen Livewire.*

### 3.2. Penanganan Race Condition & Mutex Kontrol
Operasi kritis seperti pemotongan stok barang dan klaim kuota voucher rentan terhadap kondisi balapan jika diakses oleh banyak pengguna secara simultan.
* **Pessimistic Locking:** Setiap proses modifikasi stok produk wajib dibungkus dalam Database Transaction dengan klausa `lockForUpdate()`.
* **Alur Validasi Stok:** Sistem harus memverifikasi ketersediaan jumlah stok *setelah* kunci baris diperoleh, bukan sebelum transaksi dibuka.

### 3.3. Arsitektur Idempotency
Pencegahan replikasi transaksi akibat klik ganda (*double submit*) dari pengguna atau kegagalan *retry network*:
* **Idempotency Key:** Pembuatan pesanan (*checkout*) wajib menyertakan kunci idempotensi unik (UUID atau kombinasi hash keranjang + timestamp) yang dihasilkan sebelum aksi *checkout*.
* **Verifikasi Mutex:** Database atau layer *cache* harus memeriksa keberadaan kunci tersebut sebelum memproses *order* baru guna menghindari penciptaan record pesanan ganda dengan muatan data yang sama.

### 3.4. Implementasi PHP Native Enums
Seluruh status data yang bersifat tetap wajib diekspresikan menggunakan PHP backed enums ber-tipe string. Contoh implementasi wajib:
* `OrderStatus::PENDING = 'menunggu_pembayaran'`
* `OrderStatus::PAID = 'lunas'`
* `OrderStatus::SHIPPED = 'dikirim'`
* `OrderStatus::CANCELLED = 'dibatalkan'`
* `VoucherType::PERCENTAGE = 'persentase'`
* `VoucherType::FIXED = 'nominal_tetap'`

### 3.5. Global Settings Helper
Konfigurasi situs yang bersifat dinamis disimpan dalam tabel `settings`. Akses data dipermudah menggunakan fungsi helper global buatan sendiri dengan dukungan caching otomatis:
```php
if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed {
        return App\Services\SettingService::get($key, $default);
    }
}
// Penggunaan: setting('site_name') untuk data dinamis (mis. nama toko, kontak).
```

### 3.6. Aturan Penghapusan Data (No SoftDeletes)
Sistem tidak menggunakan trait `Illuminate\Database\Eloquent\SoftDeletes`.
* Operasi penghapusan data master (kategori → produk → varian/gambar) yang belum terikat transaksi menggunakan `ON DELETE CASCADE`.
* Data master yang sudah terikat dengan riwayat transaksi (tabel `orders`/`order_items`) dilarang keras dihapus (`ON DELETE RESTRICT`). Kontrol visibilitas wajib menggunakan flag `is_active = false`.

---

## 4. Lokalisasi, Format Antarmuka, & UX Tokens

Semua teks statis, label form, pesan eror, dan konfirmasi sistem pada sisi depan (frontend) wajib menggunakan Bahasa Indonesia.

### 4.1. Format Tanggal Sistem
Penerapan fungsi pembentuk waktu mengacu pada representasi lokal Indonesia:
* **Format Kode PHP:** `Carbon::parse($date)->locale('id')->isoFormat('D MMMM YYYY HH:mm:ss')`
* **Hasil Keluaran:** `17 Juni 2026 09:39:12`

### 4.2. Format Keuangan & Moneter
* **Mata Uang Resmi:** Rupiah (Rp)
* **Aturan Penulisan:** `Rp` diikuti spasi tunggal, lalu angka nominal dengan pemisah ribuan berupa titik tanpa desimal sen.
* **Hasil Keluaran:** `Rp 2.500.000`

---

## 5. Spesifikasi Skema Database

Desain tabel MySQL dioptimalkan untuk integritas referensial dan performa kueri tinggi tanpa mekanisme softdeletes.

```
+------------------+         +------------------+         +------------------+
|    categories    |         |     products     |         |     variants     |
+------------------+         +------------------+         +------------------+
| id (PK)          |         | id (PK)          |         | id (PK)          |
| name             |-------->| category_id (FK) |-------->| product_id (FK)  |
| slug (UQ)        |         | name             |         | name             |
| thumbnail        |         | slug (UQ)        |         | sku (UQ)         |
| is_active        |         | sku (UQ)         |         | price            |
+------------------+         | original_price   |         | promo_price      |
                             | promo_price      |         | stock            |
+------------------+         | description      |         | weight           |
|    settings      |         | weight (grams)   |         +------------------+
+------------------+         | stock            |
| id (PK)          |         | is_active        |         +------------------+
| key (UQ)         |         | badge            |         |     vouchers     |
| value            |         +------------------+         +------------------+
+------------------+                  |                   | id (PK)          |
                                      |                   | code (UQ)        |
+------------------+                  |                   | discount_type    |
|      pages       |                  v                   | discount_value   |
+------------------+         +------------------+         | min_purchase     |
| id (PK)          |         |   order_items    |         | max_usage        |
| title            |         +------------------+         | used_count       |
| slug (UQ)        |         | id (PK)          |         | valid_until      |
| content          |         | order_id (FK)    |         +------------------+
| is_active        |         | product_id (FK)  |
+------------------+         | variant_id (FK)  |         +------------------+
                             | price            |         |      orders      |
+------------------+         | quantity         |         +------------------+
|  product_images  |         | total            |         | id (PK)          |
+------------------+         +------------------+         | order_number(UQ) |
| id (PK)          |                                      | idempotency_key  |
| product_id (FK)  |                                      | customer_name    |
| url / path       |                                      | customer_email   |
| is_main          |                                      | customer_phone   |
| sort_order       |                                      | shipping_cost    |
+------------------+                                      | grand_total      |
                                                          | status (Enum)    |
                                                          +------------------+
```

> **Catatan harga produk:** `original_price` adalah harga coret (harga rilis baru), dan `promo_price` adalah harga jual yang dibayar pelanggan. Bila `promo_price` `NULL`, harga efektif = `original_price`. Tampilan "harga coret" hanya muncul saat `promo_price < original_price`.

> **Catatan varian (arsitektur hybrid):** Tabel `variants` mendukung produk dengan beberapa unit jual (mis. warna / kapasitas berbeda), masing-masing punya SKU, harga, stok, dan berat sendiri. Untuk katalog Berkat Cell saat ini (unit second spesifik), varian tidak digunakan — warna & RAM/ROM dibakukan pada nama dan deskripsi produk, stok berada di level produk.

### 5.1. Tabel: products (ringkas)
```sql
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    sku VARCHAR(100) NOT NULL UNIQUE,
    original_price INT UNSIGNED NOT NULL,        -- harga coret (harga rilis baru)
    promo_price INT UNSIGNED NULL,               -- harga jual; NULL = tanpa coret
    description TEXT NOT NULL,
    weight INT UNSIGNED NOT NULL,                -- gram, untuk akurasi RajaOngkir
    stock INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    badge VARCHAR(20) NULL,                      -- 'new' (Baru) | 'hot' (Terlaris) | NULL
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.2. Tabel: orders (ringkas)
```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(100) NOT NULL UNIQUE,
    idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    shipping_address TEXT NOT NULL,
    shipping_courier VARCHAR(100) NOT NULL,      -- mis. "jne", "jnt"
    shipping_cost INT UNSIGNED NOT NULL,
    voucher_id BIGINT UNSIGNED NULL,
    discount_amount INT UNSIGNED NOT NULL DEFAULT 0,
    subtotal INT UNSIGNED NOT NULL,
    grand_total INT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL,                 -- Enum OrderStatus
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Katalog Produk

### 6.1. Kategori (5 kategori aktif)
1. **iPhone**
2. **Samsung**
3. **Infinix**
4. **Oppo**
5. **Vivo**

### 6.2. Sumber Data Produk
* **Harga:** `docs/Harga_Produk.csv` (26 unit). Kolom **"Harga Second (Harga Diskon)"** = harga jual; kolom **"Harga Rilis Baru"** = harga coret bila tersedia & lebih tinggi.
* **Spesifikasi lengkap:** `docs/Spesifikasi_HP_Lengkap.md`.
* **Pemetaan harga (fitur Harga Coret):**
  * Bila Harga Rilis Baru tersedia **dan** lebih tinggi dari harga jual → `original_price` = Harga Rilis Baru, `promo_price` = harga jual (tampil dicoret).
  * Selain itu → `original_price` = harga jual, `promo_price` = `NULL` (tanpa coret).
* **Badge kuratif** (kolom `badge`, dari "Label Status" CSV): `Terbaru → new`; `Terlaris / Terpopuler / Best Seller → hot`; `Termurah → tanpa badge`.

### 6.3. Varian & Stok
* Varian = Warna + Kapasitas RAM/ROM. Karena unit bersifat second (spesifik per unit), kombinasi warna & RAM/ROM dibakukan ke nama + deskripsi produk; stok dikelola di level produk.
* Sisa stok ditampilkan ke pembeli. Produk dengan stok 0 tetap tampil dengan label **"Habis / Sold Out"**.

---

## 7. Spesifikasi Fitur & Alur Kerja Komponen

### 7.1. Keranjang Belanja & Form Checkout (Livewire Component)
Komponen Frontend mengikat data pengiriman secara reaktif.
* **Alur Penurunan Wilayah (Cascade):** pengguna mengetik/memilih tujuan → `ShippingService` mencari destinasi (hingga kelurahan) via RajaOngkir (Komerce).
* **Kalkulasi Ongkir:** setelah tujuan & kurir dipilih, komponen memanggil `ShippingService` untuk menghitung ongkos kirim berdasarkan total berat barang di keranjang.
* **Proses Checkout:** saat tombol "Buat Pesanan" ditekan, komponen mengumpulkan payload dan meneruskannya ke `OrderService` (dengan idempotency key).

### 7.2. Manajemen Voucher & Pemotongan Diskon
* Input kode voucher tersedia di komponen checkout.
* Validasi dikerjakan penuh oleh `VoucherService::validate($code, $subtotal)`.
* **Kriteria Validitas:** tanggal saat ini < `valid_until`, `used_count` < `max_usage` (0 = tak terbatas), dan `subtotal` >= `min_purchase`.
* Nilai diskon dipotong secara reaktif sebelum penulisan transaksi.

### 7.3. Pembayaran (Midtrans Snap)
* Setelah order dibuat, sistem membuat transaksi Snap dan menampilkan popup pembayaran (transfer bank, e-wallet, kartu kredit).
* Status pembayaran diverifikasi server-to-server melalui webhook notifikasi Midtrans (publik, tanpa auth/CSRF), lalu order dipindahkan ke status `PAID`.
* Order yang tidak dibayar melewati `expiry_order` menit otomatis dibatalkan (sinkron dengan `expiry` Snap).

---

## 8. Protokol Integrasi API Pihak Ketiga

### 8.1. Midtrans Snap (Pembayaran)
* Lihat detail di `docs/Midtrans_Snap.md` & `docs/implementation-midtrans-snap.md`.
* Kredensial via `.env`: `MIDTRANS_*` (Server Key, Client Key, is_production).
* Endpoint notifikasi server-to-server diproses oleh controller pembayaran khusus.

### 8.2. RajaOngkir (Komerce) — Logistik
* Header autentikasi `key: RAJAONGKIR_API_KEY`.
* Pencarian destinasi (hingga kelurahan) & kalkulasi ongkir berdasarkan `origin_district_id` (setting), tujuan, berat, dan kurir.
* Kurir aktif: **JNE**, **J&T**, serta kurir terkemuka lain.

### 8.3. Brevo (Notifikasi Transaksional)
Pengiriman email diproses di latar belakang menggunakan Laravel Queue.
* **Endpoint:** `POST https://api.brevo.com/v3/smtp/email`
* **Header Wajib:** `api-key: BREVO_API_KEY`, `Content-Type: application/json`

Event notifikasi otomatis (pembeli & admin sesuai konteks):
1. **Pesanan Baru Dibuat** (`new_order`) — saat order menunggu pembayaran.
2. **Pembayaran Lunas** (`payment_paid`) — saat pembayaran terverifikasi.
3. **Pesanan Dikirim** (`order_shipped`) — saat barang dikirim (menyertakan nomor resi).

Contoh payload `new_order`:
```json
{
  "sender": {"name": "Berkat Cell", "email": "berkatcell111777@gmail.com"},
  "to": [{"email": "pembeli@email.com", "name": "Budi Santoso"}],
  "subject": "Pesanan Baru Anda Telah Diterima - [ORDER_NUMBER]",
  "htmlContent": "<p>Halo Budi, pesanan Anda dengan nomor [ORDER_NUMBER] sebesar Rp 2.500.000 menunggu pembayaran...</p>"
}
```

---

## 9. Panduan Desain Frontend & Antarmuka UI

Desain visual Berkat Cell mengusung karakteristik **Modern, Kekinian, Bersih, dan Profesional** — mencerminkan toko gadget tepercaya. Referensi gaya: e-commerce HP standar (mis. alfacase.id).

### 9.1. Palet Warna (Color Palette Tokens)
Gunakan utilitas kelas warna Tailwind CSS berikut:
* **Warna Utama (Primary):** Biru — Blue 600 (#2563eb) & Blue 700 (#1d4ed8) untuk aksi utama, tautan, dan aksen brand.
* **Warna Dasar / Netral:** Putih (#ffffff) sebagai latar blok komponen; Slate 50 (#f8fafc) untuk latar halaman; Slate 900 (#0f172a) untuk teks utama.
* **Warna Pendukung:** Slate 500 (#64748b) untuk teks sekunder; Emerald 600 untuk indikator sukses/stok tersedia; Red 600 untuk peringatan/"Habis".
* **Tipografi:** Font sans-serif modern (mis. Inter) untuk keterbacaan; tonjolkan angka harga dengan bobot tebal.

### 9.2. Struktur Visual Komponen
* **Gaya Komponen:** modern dengan sudut membulat secukupnya (`rounded-lg` / `rounded-xl`), bayangan halus, dan ruang putih lega.
* **Visualisasi Harga Promo:** harga asli ditampilkan dengan coret tengah (`line-through`) berwarna abu redup (`text-slate-400 text-sm`), bersanding harga jual yang dicetak tebal dan lebih besar (`text-blue-600 text-xl font-bold`).
* **Badge Produk:** `Baru` (new) dan `Terlaris` (hot) sebagai label kuratif; badge `Diskon` diturunkan otomatis dari adanya harga promo.
* **Indikator Stok:** tampilkan sisa stok; bila habis tampilkan label tegas **"Habis / Sold Out"**.

---

## 10. Protokol Debugging via Claude Code & Chrome DevTools MCP

Ketika melakukan debugging, verifikasi fungsionalitas, atau otomatisasi perbaikan menggunakan Claude Code bersama Chrome DevTools MCP, ikuti instruksi taktis berikut.

### 10.1. Inspeksi Reaktivitas Livewire 4.x
* **State Komponen:** gunakan panel Console / tab Elements untuk memantau atribut Livewire pada DOM node (`wire:id`, `wire:model`).
* **Network Payload:** filter tab Network ke Fetch/XHR; amati request ke endpoint reaktif Livewire 4. Pastikan tidak ada state ganda yang memperlambat server.

### 10.2. Pelacakan Payload Integrasi Pihak Ketiga
* **RajaOngkir:** bila dropdown/pencarian wilayah gagal, periksa respons Service Layer; pastikan parameter tujuan tidak `null`/`undefined`.
* **Midtrans:** verifikasi pembuatan token Snap dan penerimaan webhook notifikasi; periksa status order setelah callback.
* **Brevo Queue:** pastikan kegagalan email berasal dari kode atau koneksi luar; periksa log server / queue.

### 10.3. Pengujian Race Condition & Simulasi Beban Mutex
* **Request Simultan:** gunakan `fetch()` dalam loop cepat pada Console untuk menembak endpoint checkout secara bersamaan dengan payload voucher/produk yang sama.
* **Verifikasi Kunci DB:** pastikan request kedua dst. menghasilkan penolakan tegas (mis. "Stok barang tidak mencukupi" atau "Voucher telah melampaui batas kuota penggunaan"), membuktikan `lockForUpdate()` bekerja tanpa kebocoran data.
