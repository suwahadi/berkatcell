# Panduan Rilis: Indodana PayLater lewat Nicepay

Panduan ini untuk orang yang menjalankan rilis di server produksi. Spec ada di `specs/2026-10-02-nicepay-indodana-design.md`.

Fitur ini mati secara default. Selama `NICEPAY_ENABLED=false`, pelanggan tidak melihat Indodana dan pembayaran Midtrans berjalan seperti biasa. Kalau ada masalah di tahap mana pun, matikan flag itu (lihat bagian 6).

## 1. Yang harus siap sebelum mulai

- `iMid` dan `merchantKey` produksi dari Nicepay.
- Konfirmasi dari Nicepay bahwa Paylater dengan mitra Indodana sudah aktif di akun itu. Tanpa ini Registration ditolak dengan `resultCd 9108`.
- Kota, provinsi, dan kode pos toko. Dipakai sebagai data penjual dan sebagai alamat untuk pesanan ambil di toko.
- Cadangan database terbaru.
- Akun admin toko, untuk transaksi uji.
- Akses SSH ke server Hostinger dengan SSH key, dan catatan hasil "kenali server" (bagian 11.1 dan 11.2). Keduanya cukup dikerjakan sekali.

## 2. Urutan rilis

Jalankan dari folder aplikasi di server, berurutan. Server produksi memakai Hostinger shared hosting, yang berbeda dari server biasa di beberapa langkah: Composer, build aset, cron, dan cache. Baca bagian 11.3 sebelum mulai; langkah di bawah menunjuk ke sana bila ada bedanya.

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

Di Hostinger perintahnya bisa `composer2`, dan bisa berhenti di tahap skrip; lihat 11.3.

4. Jalankan migrasi. Langkah ini harus selesai sebelum kode baru melayani pembayaran: kode baru menulis kolom `provider`, jadi tanpa migrasi pembayaran Midtrans ikut gagal.

```bash
php artisan migrate --force
```

5. Bangun aset. Folder `public/build` tidak ikut di git, dan halaman redirect serta halaman pesanan memakai kelas CSS yang belum ada di build lama.

```bash
npm ci
npm run build
```

Kalau server tidak punya Node, jalankan `npm run build` di komputer lain lalu unggah folder `public/build`. Perintah unggahnya ada di 11.3.

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

8. Periksa cron. Rekonsiliasi berganti nama dari `payments:midtrans:reconcile` menjadi `payments:reconcile`. Cron yang menjalankan `schedule:run` tidak perlu diubah. Cron yang memanggil nama lama secara langsung harus diganti. Di Hostinger cron hanya bisa dilihat dan diubah lewat hPanel (11.3).

## 3. Periksa sebelum transaksi uji

| Yang diperiksa | Cara | Hasil yang benar |
|---|---|---|
| Rute Nicepay terdaftar | `php artisan route:list --path=payments/nicepay` | Tiga rute: `notification`, `pay/{order}`, `callback` |
| Jadwal rekonsiliasi | `php artisan schedule:list` | `payments:reconcile` tiap 15 menit |
| Midtrans tidak terganggu | Buat pesanan kecil, pilih salah satu Virtual Account | Popup Snap muncul dan menampilkan nomor VA |
| Indodana tersembunyi dari pelanggan | Buka halaman pesanan tanpa login | Tidak ada kartu Indodana |
| Indodana terlihat oleh admin | Buka halaman pesanan yang sama setelah login sebagai admin | Kartu Indodana ada di daftar metode |
| Logo metode pembayaran tampil | Buka halaman pesanan yang belum dibayar, di ponsel dan di komputer | Tiap kartu metode menampilkan logo, tidak ada ikon gambar rusak (bagian 12) |

Kartu Indodana hanya muncul untuk pesanan dengan total Rp10.000 sampai Rp50.000.000 dan email pelanggan paling panjang 40 karakter.

## 4. Transaksi uji oleh admin

Ini transaksi sungguhan dengan uang sungguhan. Registration, Payment, Status Inquiry, callback, dan notifikasi sudah dijalankan di sandbox Nicepay (lihat spec bagian 13.3), tetapi belum pernah di produksi dengan kredensial toko. Uji dulu di sandbox dari komputer lokal (bagian 9) sebelum langkah ini.

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
| Apakah Nicepay mengirim ulang notifikasi yang sama? | Log akses server: `POST /payments/nicepay/notification` berulang tiap menit. Tabel `payment_webhook_events` tidak menunjukkannya, karena kiriman ulang memakai baris yang sama. Di sandbox: paling banyak tiga kiriman ulang, berhenti begitu dibalas `200` | Sesuaikan isi balasan di `NicepayNotificationController` |
| Apakah zona waktu `timeStamp` benar? | Tidak ada penolakan soal waktu di `storage/logs/laravel.log` | Ganti zona di `NicepayClient::timestamp()` |
| Apakah cancel diterima untuk tagihan yang belum dibayar? | Buat pesanan kedua, pilih Indodana, lalu ganti ke VA. Cari "Nicepay cancel tidak berhasil" di log | Bila hanya ditolak, tidak perlu perubahan. Bila kodenya salah, ganti `cancelType` di `NicepayClient::cancel()` |

Yang juga harus benar setelah pembayaran:

- Pesanan berstatus Lunas dalam beberapa detik sampai paling lama 15 menit (rekonsiliasi).
- Aktivitas pesanan mencatat "Pembayaran diterima" dengan pelaku `Indodana via Nicepay (otomatis)`.
- Email "pembayaran diterima" terkirim seperti pada pembayaran Midtrans.

Setelah selesai, void transaksi uji lewat back office Nicepay. Tunggu beberapa menit setelah pembayaran: di sandbox, cancel yang dikirim satu sampai tiga menit setelah pembayaran dibalas `9302 Server is busy` dan baru berhasil sekitar sembilan menit setelahnya.

Status pesanan tidak berubah otomatis, dan di sandbox Nicepay tidak mengirim notifikasi untuk refund. Jadi jangan menunggu void itu muncul di aktivitas pesanan; batalkan pesanan ujinya sendiri di admin. Catat di spec bila di produksi notifikasinya ternyata datang (aktivitasnya berbunyi "Pembayaran dibatalkan di penyedia").

## 5. Kalau ada yang tidak berjalan

Semua kegagalan Nicepay dicatat di `storage/logs/laravel.log`. `merchantKey` tidak pernah ditulis ke log.

| Gejala | Yang dicari di log | Arti |
|---|---|---|
| "Gagal memulai pembayaran" setelah menekan Bayar | `Nicepay registration gagal` beserta `resultCd` dan `resultMsg` | `9108`: Paylater belum aktif di akun. `8003` atau `P380`: format payload ditolak. `9010`: `merchantKey` atau `iMid` salah. |
| Pesanan tidak melunas padahal sudah bayar | `Nicepay inquiry tanpa hasil` | Status Inquiry gagal atau ditolak. Rekonsiliasi mencoba lagi tiap 15 menit: tagihan terbuka sampai masa berlakunya habis, notifikasi yang tertunda sampai dua hari. |
| Tidak ada baris baru di `payment_webhook_events` | Tidak ada | Notifikasi tidak sampai. Pesanan tetap melunas lewat polling halaman dan rekonsiliasi. Periksa firewall. |
| Baris `payment_webhook_events` tertahan di `received` | `Nicepay inquiry tanpa hasil` | Notifikasi sampai tetapi inquiry gagal, jadi dibalas `503`. Nicepay mengirim ulang tiap menit, paling banyak tiga kali; setelah itu rekonsiliasi yang memprosesnya. |
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

## 9. Uji sandbox dari komputer lokal

Tidak memakai uang sungguhan. Nicepay menolak URL `localhost`, jadi situs harus dibuka lewat tunnel publik.

1. Jalankan tunnel ke server lokal dan catat URL publiknya.
2. Isi `.env` lokal. Nilai sandbox ada di `.env.example`:

```dotenv
APP_URL=<URL tunnel>
NICEPAY_ENABLED=true
NICEPAY_ADMIN_ONLY=false
NICEPAY_IS_PRODUCTION=false
NICEPAY_IMID=PAYLOANTES
NICEPAY_MERCHANT_KEY="<merchantKey uji publik dari .env.example>"
NICEPAY_STORE_CITY="<kota toko>"
NICEPAY_STORE_STATE="<provinsi toko>"
NICEPAY_STORE_POSTCODE=<kode pos toko>
```

`IONPAYTEST` tidak mengaktifkan Indodana; pakai `PAYLOANTES`.

3. Buka situs **lewat URL tunnel**, buat pesanan Rp10.000 sampai Rp50.000.000, pilih Indodana, tekan Bayar.
4. Di halaman Indodana masuk dengan akun uji: telepon `838499610` (setelah `+62`), PIN `123654`, OTP `999999` bila diminta. Pilih tenor, centang persetujuan, tekan Bayar, masukkan PIN lagi.
5. Tekan "Kembali ke halaman merchant". Pesanan seharusnya sudah Lunas, atau menjadi Lunas saat notifikasi tiba beberapa detik kemudian.

Yang sudah dicoba dengan cara ini dan hasilnya sesuai (spec bagian 13.3): pesanan dengan ongkir, pesanan dengan voucher diskon, ganti metode dari Indodana ke VA lalu bayar Indodana yang lama, dan membatalkan pesanan yang tagihan Indodananya masih terbuka lalu membayarnya.

Tagihan Indodana tidak bisa dimatikan dari sisi kita. Setelah pelanggan berganti metode atau pesanan dibatalkan, tagihan lama tetap bisa dibayar di Indodana sampai 24 jam.

## 10. Yang belum dikerjakan

- Refund otomatis. Pesanan lunas yang dibatalkan admin tetap direfund manual lewat back office Nicepay, dan refund itu tidak tercatat sendiri di aktivitas pesanan.
- Batas laju Status Inquiry Nicepay belum diketahui. Halaman pesanan memeriksa status tagihan Indodana tiap 120 detik selama terbuka (tagihan Midtrans tetap tiap 30 detik).

Pembayaran yang masuk untuk pesanan yang sudah dibatalkan tetap menandai pesanan Lunas, karena stok dan kuota voucher sudah dikembalikan saat pembatalan. Admin mendapat notifikasi "Pembayaran masuk untuk pesanan yang dibatalkan" di lonceng, dan aktivitas pesanan mencatat "Perlu Ditinjau". Putuskan per kasus: kirim barangnya bila stok masih ada, atau refund.

## 11. Server Hostinger dan akses SSH

Produksi berjalan di Hostinger shared hosting. Semua domain dalam satu akun berbagi satu pengguna Linux, tanpa akses root. Bagian ini ditulis sebelum ada akses ke server itu: nilai dalam kurung sudut diisi dari hPanel, dan beberapa hal baru pasti setelah 11.2 dijalankan.

### 11.1 Pasang SSH key (sekali per komputer)

Dengan SSH key, perintah rilis bisa dijalankan dari terminal komputer kerja tanpa password dan tanpa membuka hPanel.

1. Di komputer yang dipakai merilis, buat pasangan kunci khusus untuk server ini. Isi passphrase saat diminta.

```bash
ssh-keygen -t ed25519 -C "berkatcell-hostinger" -f ~/.ssh/berkatcell_hostinger
```

Hasilnya dua berkas di folder `.ssh` (di Windows: `C:\Users\<nama>\.ssh\`): `berkatcell_hostinger` adalah kunci privat dan tidak pernah keluar dari komputer ini, `berkatcell_hostinger.pub` adalah kunci publik.

2. Di hPanel buka Websites, Dashboard di sebelah situsnya, lalu SSH Access. Nyalakan SSH, catat IP, port, dan username. Tekan Add SSH key, beri nama, dan tempel seluruh isi `berkatcell_hostinger.pub`.
3. Tambahkan ke `~/.ssh/config` di komputer kerja:

```
Host berkatcell-prod
    HostName <IP dari hPanel>
    Port <port dari hPanel>
    User <username dari hPanel>
    IdentityFile ~/.ssh/berkatcell_hostinger
    IdentitiesOnly yes
```

Port SSH hosting web Hostinger biasanya `65002`, bukan `22`. Pakai angka yang tampil di hPanel.

4. Uji sambungannya:

```bash
ssh berkatcell-prod "php -v"
```

Aturan memegang kunci:

- Kunci privat dan password hPanel tidak masuk repo, chat, atau tiket. Yang dibagikan ke Hostinger hanya berkas `.pub`.
- Satu kunci untuk satu komputer. Hapus kuncinya dari hPanel saat komputer diganti atau orangnya tidak lagi mengurus server.
- Kunci ini membuka seluruh akun, bukan hanya berkatcell: semua situs di akun itu berada di bawah pengguna Linux yang sama.
- Jangan mengubah pengaturan PHP lewat SSH. Di Hostinger pengaturan PHP berlaku untuk seluruh akun, jadi salah ubah bisa menjatuhkan situs lain.

Kalau server mengambil kode dari GitHub lewat git dan repo-nya privat, server butuh kuncinya sendiri. Jalankan di server:

```bash
ssh-keygen -t ed25519 -C "berkatcell-server" -f ~/.ssh/github_berkatcell -N ""
cat ~/.ssh/github_berkatcell.pub
```

Tempel hasil `cat` di GitHub: repo `suwahadi/berkatcell`, Settings, Deploy keys, Add deploy key, tanpa mencentang write access. Kunci ini tanpa passphrase supaya `git fetch` bisa jalan sendiri, jadi biarkan hanya-baca dan hanya untuk repo ini. Lalu di server:

```bash
printf 'Host github.com\n    IdentityFile ~/.ssh/github_berkatcell\n    IdentitiesOnly yes\n' >> ~/.ssh/config
ssh -T git@github.com
git -C <folder aplikasi> remote set-url origin git@github.com:suwahadi/berkatcell.git
```

### 11.2 Kenali server (sekali, catat hasilnya)

Jalankan setelah 11.1, lalu isi kolom terakhir tabel di bawah. Tidak ada perintah di sini yang mengubah apa pun.

```bash
ssh berkatcell-prod
ls -la ~/domains/<domain>/
php -v
ls /opt/alt | grep php
composer --version; composer2 --version
git --version
node -v; npm -v
php -r 'var_dump(function_exists("proc_open"));'
grep -n "^QUEUE_CONNECTION\|^APP_ENV\|^APP_DEBUG" <folder aplikasi>/.env
```

| Yang dicari | Kenapa penting | Hasil di server ini |
|---|---|---|
| Letak folder aplikasi, dan apakah `public_html` itu symlink ke `public/` atau salinan isinya | Kalau symlink, berkas baru di `public/` (logo, `build`) langsung tampil. Kalau salinan, berkas itu harus disalin juga ke `public_html` setiap rilis | belum diisi |
| Versi `php` di SSH | Aplikasi butuh PHP 8.3 atau lebih baru. Kalau `php` lebih tua, pakai biner berversi, biasanya `/opt/alt/php83/usr/bin/php`, di semua perintah `php artisan` dan di cron | belum diisi |
| `composer` atau `composer2` | Butuh Composer 2. Di Hostinger `composer` sering masih versi 1 | belum diisi |
| Ada `node` dan `npm` atau tidak | Menentukan aset dibangun di server atau di komputer kerja | belum diisi |
| Hasil `proc_open` | Kalau `false`, skrip Composer dan `schedule:run` tidak jalan seperti biasa (11.3) | belum diisi |
| Isi halaman Cron Jobs di hPanel | Perintah `crontab` tidak ada di Hostinger, jadi `crontab -l` yang gagal bukan bukti cron kosong | belum diisi |
| `QUEUE_CONNECTION` | Kalau `database`, email pesanan baru terkirim bila ada yang menjalankan antrean | belum diisi |

### 11.3 Penyesuaian langkah rilis

| Langkah di bagian 2 | Di Hostinger |
|---|---|
| 3. Composer | Pakai `composer2 install --no-dev --optimize-autoloader`. Kalau berhenti dengan pesan soal `proc_open`, ulangi dengan tambahan `--no-scripts`, lalu jalankan `php artisan package:discover --ansi` |
| 5. Bangun aset | Kalau server tidak punya Node, bangun di komputer kerja dari commit yang sama dengan server, lalu unggah (perintah di bawah) |
| 7. `queue:restart` | Hanya berpengaruh kalau ada pekerja antrean yang berjalan terus. Dengan antrean lewat cron, perintah ini aman tapi tidak diperlukan |
| 8. Cron | Diatur di hPanel: Advanced, Cron Jobs, tipe Custom. Jam server UTC |

Membangun aset di komputer kerja lalu mengunggahnya:

```bash
git checkout main
git pull
npm ci
npm run build
scp -r public/build berkatcell-prod:<folder aplikasi>/public/
ssh berkatcell-prod "cd <folder aplikasi> && php artisan view:cache"
```

Build tidak membawa nilai `.env` lokal selain nama aplikasi, jadi aman dijalankan dari komputer yang `.env`-nya berisi pengaturan sandbox.

Cron yang dibutuhkan aplikasi, dengan path lengkap ke `php` dan ke `artisan`:

| Jadwal | Perintah | Untuk |
|---|---|---|
| `* * * * *` | `/usr/bin/php /home/<username>/domains/<domain>/<folder aplikasi>/artisan schedule:run` | Rekonsiliasi pembayaran tiap 15 menit |
| `* * * * *` | `/usr/bin/php /home/<username>/domains/<domain>/<folder aplikasi>/artisan queue:work --stop-when-empty --max-time=50` | Mengirim email dari antrean, bila `QUEUE_CONNECTION=database` dan belum ada yang menjalankannya |

Sebelum mengandalkan cron pertama, jalankan sekali lewat SSH: `php artisan schedule:run`. Kalau gagal dengan pesan soal `proc_open`, ganti baris itu dengan `*/15 * * * *` dan perintah `artisan payments:reconcile`, yang tidak butuh `proc_open`.

### 11.4 Memeriksa hasil rilis

CDN Hostinger menyimpan cache, termasuk halaman error. Kode status dari luar bisa basi, jadi periksa server asal dari dalam server:

```bash
curl -sk -o /dev/null -w 'asal=%{http_code}\n' --resolve <domain>:443:127.0.0.1 https://<domain>/
curl -s  -o /dev/null -w 'cdn=%{http_code}\n'  https://<domain>/
curl -sk -o /dev/null -w 'notifikasi=%{http_code}\n' -X POST --resolve <domain>:443:127.0.0.1 https://<domain>/payments/nicepay/notification
tail -n 50 <folder aplikasi>/storage/logs/laravel.log
```

`asal` dan `cdn` seharusnya `200`. `notifikasi` seharusnya `403`: rutenya hidup dan menolak permintaan tanpa token. Kalau `asal` benar tetapi `cdn` belum, kosongkan cache dari hPanel.

Rujukan Hostinger: [membuat dan menambahkan SSH key](https://www.hostinger.com/support/5634532-how-to-generate-ssh-keys-and-add-them-to-hostinger-dashboard/), [masuk lewat SSH](https://www.hostinger.com/support/10441250-how-to-connect-to-a-hosting-plan-remotely-using-ssh-in-hostinger/), [cron job](https://docs.hostinger.com/websites/cron-jobs), [Laravel di Hostinger](https://www.hostinger.com/support/6152127-how-to-deploy-laravel-8-at-hostinger/).

## 12. Perubahan lain yang ikut dalam rilis ini

Halaman pesanan kini menampilkan logo asli tiap metode pembayaran, di pilihan metode dan di kartu tagihan aktif. Ini berlaku juga saat Nicepay dimatikan.

- Logonya ada di `public/images/payments/` dan ikut di git, jadi sampai di server bersama kodenya. Kalau `public_html` berupa salinan (11.2), salin juga folder `public/images`.
- Tampilannya memakai kelas CSS baru. Tanpa langkah 5 di bagian 2, logo tampil tetapi susunannya berantakan.
- Mengganti logo: timpa berkasnya dengan nama yang sama. Menambah metode: taruh berkasnya di folder itu dan isi `logo` di `PaymentMethods::METHODS`. Tes `test_setiap_metode_punya_berkas_logo` gagal bila berkasnya tidak ada.
- Semua logo berformat SVG kecuali Akulaku, yang berupa PNG selebar 320 piksel.

`phpunit.xml` kini mengunci variabel `NICEPAY_*` untuk tes. Ini tidak berpengaruh ke produksi; gunanya agar tes tidak ikut membaca `.env` komputer yang sedang menyalakan sandbox.
