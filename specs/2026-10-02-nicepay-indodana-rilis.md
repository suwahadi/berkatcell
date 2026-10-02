# Panduan Rilis: Indodana PayLater lewat Nicepay

Panduan ini untuk orang yang menjalankan rilis di server produksi. Spec ada di `specs/2026-10-02-nicepay-indodana-design.md`.

Fitur ini mati secara default. Selama `NICEPAY_ENABLED=false`, pelanggan tidak melihat Indodana dan pembayaran Midtrans berjalan seperti biasa. Kalau ada masalah di tahap mana pun, matikan flag itu (lihat bagian 6).

## 1. Yang harus siap sebelum mulai

- `iMid` dan `merchantKey` produksi dari Nicepay.
- Konfirmasi dari Nicepay bahwa Paylater dengan mitra Indodana sudah aktif di akun itu. Tanpa ini Registration ditolak dengan `resultCd 9108`.
- Kota, provinsi, dan kode pos toko. Dipakai sebagai data penjual dan sebagai alamat untuk pesanan ambil di toko.
- Cadangan database terbaru.
- Akun admin toko, untuk transaksi uji.

## 2. Urutan rilis

Jalankan dari folder aplikasi di server, berurutan.

1. Cadangkan database.
2. Ambil kode terbaru (unggah berkas, atau lewat git).

Riwayat `main` di GitHub ditulis ulang pada 2 Oktober 2026 untuk merapikan pesan commit; isi kodenya tidak berubah. Clone yang dibuat sebelum itu tidak bisa `git pull` biasa. Pastikan dulu tidak ada perubahan lokal yang belum disimpan, lalu samakan dengan GitHub:

```bash
git status
git fetch origin
git reset --hard origin/main
```

`git reset --hard` membuang perubahan pada file yang ter-track. File yang tidak ter-track seperti `.env` dan isi `storage/` tidak tersentuh.
3. Pasang dependensi:

```bash
composer install --no-dev --optimize-autoloader
```

4. Jalankan migrasi. Langkah ini harus selesai sebelum kode baru melayani pembayaran: kode baru menulis kolom `provider`, jadi tanpa migrasi pembayaran Midtrans ikut gagal.

```bash
php artisan migrate --force
```

5. Bangun aset. Folder `public/build` tidak ikut di git, dan halaman redirect memakai kelas CSS yang belum ada di build lama.

```bash
npm ci
npm run build
```

Kalau server tidak punya Node, jalankan `npm run build` di komputer lain lalu unggah folder `public/build`.

6. Tambahkan ke `.env`:

```dotenv
NICEPAY_ENABLED=true
NICEPAY_ADMIN_ONLY=true
NICEPAY_IS_PRODUCTION=true
NICEPAY_IMID=<iMid produksi>
NICEPAY_MERCHANT_KEY=<merchantKey produksi>
NICEPAY_EXPIRY_MINUTES=1440
NICEPAY_STORE_CITY="<kota toko>"
NICEPAY_STORE_STATE="<provinsi toko>"
NICEPAY_STORE_POSTCODE=<kode pos toko>
```

`NICEPAY_ADMIN_ONLY=true` membuat Indodana hanya terlihat oleh akun admin. Biarkan begitu sampai bagian 4 selesai.

7. Muat ulang konfigurasi dan pekerja antrean:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Lewati perintah `*:cache` yang biasanya tidak Anda pakai di server ini.

8. Periksa cron. Rekonsiliasi berganti nama dari `payments:midtrans:reconcile` menjadi `payments:reconcile`. Cron yang menjalankan `schedule:run` tidak perlu diubah. Cron yang memanggil nama lama secara langsung harus diganti.

## 3. Periksa sebelum transaksi uji

| Yang diperiksa | Cara | Hasil yang benar |
|---|---|---|
| Rute Nicepay terdaftar | `php artisan route:list --path=payments/nicepay` | Tiga rute: `notification`, `pay/{order}`, `callback` |
| Jadwal rekonsiliasi | `php artisan schedule:list` | `payments:reconcile` tiap 15 menit |
| Midtrans tidak terganggu | Buat pesanan kecil, pilih salah satu Virtual Account | Popup Snap muncul dan menampilkan nomor VA |
| Indodana tersembunyi dari pelanggan | Buka halaman pesanan tanpa login | Tidak ada kartu Indodana |
| Indodana terlihat oleh admin | Buka halaman pesanan yang sama setelah login sebagai admin | Kartu Indodana ada di daftar metode |

Kartu Indodana hanya muncul untuk pesanan dengan total Rp10.000 sampai Rp50.000.000 dan email pelanggan paling panjang 40 karakter.

## 4. Transaksi uji oleh admin

Ini transaksi sungguhan dengan uang sungguhan. Sampai langkah ini, langkah Payment, Status Inquiry, Cancel, dan notifikasi belum pernah dijalankan terhadap Nicepay; semuanya baru teruji dengan respons tiruan.

1. Login sebagai admin, buat satu pesanan kecil (minimal Rp10.000) dengan pengiriman kurir supaya ada ongkir.
2. Di halaman pesanan pilih Indodana, tekan Bayar.
3. Selesaikan pembayaran di halaman Indodana.
4. Kembali ke halaman pesanan dan catat hasil tabel di bawah ke spec bagian 13.

| Pertanyaan | Tempat melihat | Kalau asumsinya salah |
|---|---|---|
| Apakah tenor yang kita kirim mengikat? | Halaman Indodana: apakah ada pilihan tenor selain yang dikirim (1 bulan untuk total sampai Rp2 juta, selain itu 3 bulan) | Perlu pemilih tenor di halaman pesanan. Ini pekerjaan baru. |
| Apakah baris ongkir dan diskon diterima? | Tombol Bayar tidak memunculkan "Gagal memulai pembayaran"; rincian di halaman Indodana | Ubah `NicepayRegistrationPayload::cart()` agar selalu mengirim satu baris senilai total |
| Apakah form POST membuka halaman Indodana? | Setelah halaman "Lanjut ke Indodana", browser sampai ke Indodana | Ganti form jadi redirect GET di `NicepayPaymentController::redirect()` |
| Apakah notifikasi sampai? | Tabel `payment_webhook_events`, baris dengan `provider = nicepay` | Periksa apakah firewall memblokir IP Nicepay (bagian 5) |
| Apakah Nicepay mengirim ulang notifikasi yang sama? | Baris `payment_webhook_events` yang identik muncul berkali-kali | Sesuaikan isi balasan di `NicepayNotificationController` |
| Apakah zona waktu `timeStamp` benar? | Tidak ada penolakan soal waktu di `storage/logs/laravel.log` | Ganti zona di `NicepayClient::timestamp()` |
| Apakah cancel diterima untuk tagihan yang belum dibayar? | Buat pesanan kedua, pilih Indodana, lalu ganti ke VA. Cari "Nicepay cancel tidak berhasil" di log | Bila hanya ditolak, tidak perlu perubahan. Bila kodenya salah, ganti `cancelType` di `NicepayClient::cancel()` |

Yang juga harus benar setelah pembayaran:

- Pesanan berstatus Lunas dalam beberapa detik sampai paling lama 15 menit (rekonsiliasi).
- Aktivitas pesanan mencatat "Pembayaran diterima" dengan pelaku `Indodana via Nicepay (otomatis)`.
- Email "pembayaran diterima" terkirim seperti pada pembayaran Midtrans.

Setelah selesai, void transaksi uji lewat back office Nicepay. Void itu akan muncul di aktivitas pesanan sebagai "Pembayaran dibatalkan di penyedia"; status pesanan tidak berubah otomatis.

## 5. Kalau ada yang tidak berjalan

Semua kegagalan Nicepay dicatat di `storage/logs/laravel.log`. `merchantKey` tidak pernah ditulis ke log.

| Gejala | Yang dicari di log | Arti |
|---|---|---|
| "Gagal memulai pembayaran" setelah menekan Bayar | `Nicepay registration gagal` beserta `resultCd` dan `resultMsg` | `9108`: Paylater belum aktif di akun. `8003` atau `P380`: format payload ditolak. `9010`: `merchantKey` atau `iMid` salah. |
| Pesanan tidak melunas padahal sudah bayar | `Nicepay inquiry tanpa hasil` | Status Inquiry gagal atau ditolak. Rekonsiliasi mencoba lagi tiap 15 menit: tagihan terbuka sampai masa berlakunya habis, notifikasi yang tertunda sampai dua hari. |
| Tidak ada baris baru di `payment_webhook_events` | Tidak ada | Notifikasi tidak sampai. Pesanan tetap melunas lewat polling halaman dan rekonsiliasi. Periksa firewall. |
| Baris `payment_webhook_events` berstatus `invalid_signature` | Tidak ada | Token tidak cocok: `merchantKey` di `.env` salah, atau permintaan bukan dari Nicepay. |
| Halaman redirect tampil tanpa gaya | Tidak ada | `npm run build` belum dijalankan. Tombolnya tetap berfungsi. |
| Nicepay tidak bisa dihubungi | `Nicepay tidak dapat dihubungi` atau `Nicepay membalas error HTTP` | Masalah jaringan atau Nicepay sedang gangguan. |

Notifikasi produksi Nicepay datang dari `103.20.51.34`, `103.20.51.33`, dan `18.98.96.50`. Kalau hosting memakai firewall, izinkan ketiganya ke `POST /payments/nicepay/notification`. Aplikasi sendiri tidak membatasi berdasarkan IP; pengamannya adalah token dan konfirmasi lewat Status Inquiry. Rute itu dibatasi 120 permintaan per menit per IP.

## 6. Mematikan fitur

```dotenv
NICEPAY_ENABLED=false
```

```bash
php artisan config:cache
```

Setelah itu kartu Indodana hilang dan tagihan Indodana baru ditolak. Pembayaran Midtrans tidak terpengaruh. Tagihan Indodana yang sudah telanjur dibuat tetap bisa dibayar dan tetap melunas otomatis, selama `NICEPAY_IMID` dan `NICEPAY_MERCHANT_KEY` tidak dihapus dari `.env`: notifikasi, callback, dan rekonsiliasi tidak bergantung pada flag ini.

Migrasi tidak perlu dibatalkan. Kolom `provider` aman dibiarkan walau fitur dimatikan.

## 7. Membuka ke semua pelanggan

Lakukan setelah semua baris tabel di bagian 4 terjawab dan penyesuaiannya (kalau ada) sudah dirilis.

```dotenv
NICEPAY_ADMIN_ONLY=false
```

```bash
php artisan config:cache
```

Buka halaman pesanan tanpa login dan pastikan kartu Indodana tampil.

## 8. Yang ikut berubah untuk Midtrans

Perubahan ini berlaku begitu kode dirilis, walau Nicepay dimatikan:

- Membatalkan pesanan kini juga membatalkan tagihan yang masih terbuka di Midtrans.
- Tagihan yang sudah lewat masa berlakunya tidak dipakai ulang; memilih metode yang sama membuat tagihan baru.
- Saat pelanggan berganti metode, tagihan lama dibatalkan setelah tagihan baru berhasil dibuat, bukan sebelumnya.
- Rekonsiliasi terjadwal bernama `payments:reconcile` dan tidak berjalan tumpang tindih.

## 9. Yang belum dikerjakan

- Refund otomatis. Pesanan lunas yang dibatalkan admin tetap direfund manual lewat back office Nicepay.
- Batas laju Status Inquiry Nicepay belum diketahui. Halaman pesanan memeriksa status tagihan Indodana tiap 120 detik selama terbuka (tagihan Midtrans tetap tiap 30 detik).

Pembayaran yang masuk untuk pesanan yang sudah dibatalkan tetap menandai pesanan Lunas, karena stok dan kuota voucher sudah dikembalikan saat pembatalan. Admin mendapat notifikasi "Pembayaran masuk untuk pesanan yang dibatalkan" di lonceng, dan aktivitas pesanan mencatat "Perlu Ditinjau". Putuskan per kasus: kirim barangnya bila stok masih ada, atau refund.
