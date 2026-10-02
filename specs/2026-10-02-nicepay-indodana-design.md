# Desain: Pembayaran Indodana PayLater lewat Nicepay

Tanggal: 2 Oktober 2026
Status: menunggu tinjauan

## 1. Tujuan

Menambah satu metode pembayaran baru, **Indodana PayLater melalui Nicepay (API Non-SNAP)**, di halaman invoice. Tujuh kanal Midtrans yang ada tetap berjalan tanpa perubahan perilaku.

Berhasil berarti: pelanggan memilih Indodana, diarahkan ke halaman Indodana, memilih tenor di sana, dan pesanan melunas otomatis dengan pengaman yang sama seperti Midtrans (notifikasi, polling halaman, rekonsiliasi terjadwal).

## 2. Keputusan yang sudah disepakati

| Topik | Keputusan |
|---|---|
| Tenor | Dipilih pelanggan di halaman Indodana. Kita mengirim nilai default. |
| Akun | Kredensial produksi siap, akun sandbox belum ada. |
| Uji nyata pertama | Fitur di balik flag, awalnya hanya terlihat oleh admin, satu transaksi kecil sungguhan lalu di-void. |
| Arsitektur | Inti bersama (siklus attempt dan pelunasan) dengan penyedia sebagai cabang `match`, tanpa interface. |
| Penamaan kolom | Kolom `midtrans_*` dan `snap_*` dipakai ulang untuk Nicepay, tidak diganti nama. |

## 3. Cakupan

Termasuk:

- Pemindahan siklus attempt dan logika pelunasan dari kelas Midtrans ke kelas netral.
- Modul Nicepay: registration, payment redirect, callback, notifikasi, status inquiry, cancel.
- Pilihan Indodana di halaman invoice dengan syarat ketersediaan.
- Rekonsiliasi terjadwal untuk kedua penyedia.
- Tes otomatis dengan respons HTTP tiruan.

Tidak termasuk:

- Refund otomatis saat admin membatalkan pesanan yang sudah lunas. Tetap manual lewat back office Nicepay, sama seperti Midtrans sekarang.
- Confirm Receipt (hanya berlaku untuk Akulaku), tokenisasi atau seamless, serta Kredivo dan Akulaku lewat Nicepay.
- Mengganti nama kolom atau tabel `midtrans_*`.
- Whitelist IP Nicepay (lihat bagian 10).
- Pemilih tenor di halaman kita. Ini jalur cadangan, hanya dibangun bila uji nyata membuktikan `instmntMon` mengikat (lihat bagian 13).

## 4. Ringkasan API Nicepay yang dipakai

Base URL: `https://dev.nicepay.co.id` (development) dan `https://www.nicepay.co.id` (produksi).

`merchantToken` selalu SHA-256 dalam heksadesimal huruf kecil atas gabungan string tanpa pemisah.

| Langkah | Endpoint | Format | Rumus `merchantToken` |
|---|---|---|---|
| Registration | `POST /nicepay/direct/v2/registration` | JSON, server ke server | `timeStamp + iMid + referenceNo + amt + merchantKey` |
| Payment | `POST /nicepay/direct/v2/payment` | form, lewat browser pelanggan | `timeStamp + iMid + referenceNo + amt + merchantKey` |
| Status Inquiry | `POST /nicepay/direct/v2/inquiry` | JSON, server ke server | `timeStamp + iMid + referenceNo + amt + merchantKey` |
| Cancel | `POST /nicepay/direct/v2/cancel` | JSON, server ke server | `timeStamp + iMid + tXid + amt + merchantKey` |
| Notification | `POST` ke `dbProcessUrl` kita | form, dari Nicepay | `iMid + tXid + amt + merchantKey` |

Nilai tetap: `payMethod = 06` (Paylater), `mitraCd = IDNA` (Indodana), `currency = IDR`.

`timeStamp` berformat `YmdHis` dalam zona waktu `Asia/Jakarta`.

Kode `status` pada Status Inquiry untuk Paylater: `0` paid, `1` void, `2` refund, `3` unpaid, `8` fail, `9` init. Pada notifikasi: `0` deposit, `1` reversal.

Batas Indodana: 1 bulan Rp10.000 sampai Rp2.000.000; 3 bulan Rp300.000 sampai Rp50.000.000; 6 bulan Rp500.000 sampai Rp50.000.000; 12, 18, 24 bulan Rp1.000.000 sampai Rp50.000.000. Halaman pembayaran berlaku 24 jam.

## 5. Komponen

### 5.1 Kelas netral (baru)

`app/Services/Payments/PaymentMethods.php`

- Daftar metode: kunci, penyedia (`midtrans` atau `nicepay`), label, tipe, warna merek.
- `available(Order $order, ?User $user): array` mengembalikan metode yang boleh ditawarkan.
- `providerFor(string $method): string`.
- Tujuh metode Midtrans selalu tersedia. `indodana` tersedia bila semua syarat ini terpenuhi:
  - `services.nicepay.enabled` bernilai true, dan `imid` serta `merchant_key` terisi.
  - Bila `services.nicepay.admin_only` true, `$user?->isAdmin()` harus true.
  - `grand_total` antara 10.000 dan 50.000.000.
  - Panjang `customer_email` maksimal 40 karakter.

`app/Services/Payments/PaymentAttemptService.php`

- `createOrReuseActiveAttempt(Order $order, string $method): PaymentAttempt`. Isinya dipindahkan dari `MidtransPaymentAttemptService`: transaksi DB, kunci pesanan, tolak bila lunas atau dibatalkan, pakai ulang attempt terbuka dengan metode yang sama, tandai attempt lama `SUPERSEDED` dan batalkan di penyedianya, buat attempt baru dengan `provider`, lalu `match` ke `MidtransPaymentAttemptService::start()` atau `NicepayPaylaterService::start()`.
- `syncAttempt(PaymentAttempt $attempt): void` dan `syncActiveAttempt(Order $order): void`, juga `match` pada `provider`.
- Masa berlaku attempt: `order_expiry_minutes()` untuk Midtrans, `services.nicepay.expiry_minutes` (default 1.440) untuk Nicepay.

`app/Services/Payments/PaymentSettlementService.php`

- `paid(Order $order, PaymentAttempt $attempt, int $paidAmount, string $actor, ?PaymentWebhookEvent $event = null): void`
- `pending(Order $order, PaymentAttempt $attempt, ?PaymentWebhookEvent $event = null): void`
- `failed(Order $order, PaymentAttempt $attempt, PaymentAttemptStatus $status, string $reason, ?PaymentWebhookEvent $event = null): void`
- `cancelAtGateway(PaymentAttempt $attempt): void`, `match` pada `provider`, dibungkus `rescue` sehingga kegagalan tidak memblokir.
- Isinya dipindahkan apa adanya dari `handlePaid`, `handlePending`, `handleFailed`, dan `cancelOtherOpenAttempts` di `MidtransWebhookService`. Pemanggil tetap bertanggung jawab memegang kunci baris pesanan dan attempt.
- Ini satu-satunya tempat yang memanggil `OrderService::markAsPaid()` dari jalur pembayaran otomatis.

### 5.2 Kelas Midtrans (dikurangi)

- `MidtransClient`, `MidtransSignatureVerifier`, `MidtransStatusMapper`: tidak berubah.
- `MidtransPaymentAttemptService`: tinggal `start(Order, PaymentAttempt, string $method): void` (susun payload Snap, simpan token), `sync(PaymentAttempt): void`, `supportedMethods()`, dan `METHOD_MAP`.
- `MidtransWebhookService`: `handle(array $payload)` dan `syncFromStatus(PaymentAttempt, array $payload)` tetap dengan tanda tangan yang sama. Verifikasi tanda tangan, pencatatan event, dan penguncian tetap di sini; keputusan lunas, pending, dan gagal diteruskan ke `PaymentSettlementService`.

### 5.3 Kelas Nicepay (baru)

`app/Services/Payments/Nicepay/NicepayClient.php`

- `register(array $payload): array`, `inquiry(PaymentAttempt $attempt): array`, `cancel(PaymentAttempt $attempt): array`.
- `paymentFormFields(PaymentAttempt $attempt): array` mengembalikan `timeStamp`, `tXid`, `merchantToken`, `callBackUrl`.
- `paymentUrl(): string`.
- `token(string ...$parts): string` dan `notificationTokenIsValid(array $payload): bool` (memakai `hash_equals`).
- Semua panggilan HTTP memakai timeout 15 detik. Log tidak pernah memuat `merchantKey`.

`app/Services/Payments/Nicepay/NicepayPaylaterService.php`

- `start(Order $order, PaymentAttempt $attempt): void` menyusun payload registration (bagian 7), memanggil `register()`, lalu menyimpan `tXid` dan payload.
- `sync(PaymentAttempt $attempt): void` memanggil inquiry dan menerapkan pemetaan status (bagian 8) di dalam transaksi dengan kunci baris.
- `handleNotification(array $payload): void` memverifikasi token, mencatat event, lalu menjalankan logika yang sama dengan `sync()`.

`app/Http/Controllers/Payment/NicepayNotificationController.php`

- Menerima notifikasi. Balasan `200 OK`, atau `403` bila token tidak valid.

`app/Http/Controllers/Payment/NicepayPaymentController.php`

- `redirect(Order $order)`: bila attempt aktif pesanan itu milik Nicepay, masih terbuka, belum kedaluwarsa, dan punya `tXid`, tampilkan `resources/views/payments/nicepay-redirect.blade.php` (form POST yang terkirim otomatis, dengan tombol manual di dalam `<noscript>`). Selain itu, arahkan ke halaman pesanan.
- `callback(Request $request)`: cari attempt dari `referenceNo`, jalankan `sync()` di dalam `rescue`, lalu arahkan ke halaman pesanan. Bila attempt tidak ditemukan, arahkan ke beranda.

### 5.4 Perubahan lain

- `app/Console/Commands/ReconcileMidtransPayments.php` menjadi `ReconcilePayments.php` dengan signature `payments:reconcile {--minutes=10}`. Untuk tiap attempt `PENDING` yang cukup lama, panggil `PaymentAttemptService::syncAttempt()`. `routes/console.php` disesuaikan.
- `resources/views/pages/storefront/⚡order-success.blade.php`:
  - Daftar metode diambil dari `PaymentMethods::available()`, bukan dari array di dalam komponen.
  - `pay()`, `continuePayment()`, dan `refreshStatus()` memakai `PaymentAttemptService`.
  - Untuk attempt Nicepay, komponen melakukan redirect penuh ke rute `payments.nicepay.pay`. Untuk Midtrans tetap mengirim event `snap-pay`.
- `bootstrap/app.php`: tambah `payments/nicepay/notification` dan `payments/nicepay/callback` ke pengecualian CSRF.
- `config/services.php` dan `.env.example`: blok `nicepay` (bagian 6.3).

## 6. Data

### 6.1 Migrasi

Satu migrasi baru:

- `payment_attempts.provider`: string, default `midtrans`, berindeks.
- `payment_webhook_events.provider`: string, default `midtrans`.

Baris lama otomatis bernilai `midtrans`. `PaymentAttempt` dan `PaymentWebhookEvent` menambah `provider` ke `$fillable`.

### 6.2 Kolom yang dipakai ulang untuk Nicepay

`payment_attempts`:

| Kolom | Isi untuk Nicepay |
|---|---|
| `midtrans_order_id` | `referenceNo`, format `{order_number}-A{urutan}` (maksimal 40 karakter; format sekarang 21 karakter) |
| `midtrans_transaction_id` | `tXid` |
| `midtrans_transaction_status` | kode `status` terakhir dari inquiry |
| `snap_request_payload` | request registration |
| `snap_response_payload` | response registration |
| `latest_notification_payload` | notifikasi atau hasil inquiry terakhir |
| `snap_token`, `redirect_url`, `midtrans_fraud_status` | null |

`payment_webhook_events`:

| Kolom | Isi untuk Nicepay |
|---|---|
| `midtrans_order_id` | `referenceNo` |
| `transaction_id` | `tXid` |
| `transaction_status` | `status` |
| `gross_amount` | `amt` |
| `signature_key` | `merchantToken` |
| `event_hash` | SHA-256 dari `nicepay|referenceNo|tXid|status|amt|merchantToken` |

### 6.3 Konfigurasi

| Kunci `.env` | Default | Arti |
|---|---|---|
| `NICEPAY_ENABLED` | `false` | Sakelar utama. |
| `NICEPAY_ADMIN_ONLY` | `true` | Indodana hanya terlihat oleh admin. |
| `NICEPAY_IS_PRODUCTION` | `false` | Memilih base URL. |
| `NICEPAY_IMID` | kosong | Merchant ID. |
| `NICEPAY_MERCHANT_KEY` | kosong | Merchant key. |
| `NICEPAY_EXPIRY_MINUTES` | `1440` | Masa berlaku attempt. |
| `NICEPAY_STORE_CITY` | kosong | Kota toko. |
| `NICEPAY_STORE_STATE` | kosong | Provinsi toko. |
| `NICEPAY_STORE_POSTCODE` | kosong | Kode pos toko. |

Nama, email, telepon, dan alamat toko diambil dari setting yang sudah ada: `site_name`, `site_email`, `site_phone`, `site_address`.

## 7. Payload registration

| Field | Sumber |
|---|---|
| `timeStamp` | Waktu sekarang, `Asia/Jakarta`, `YmdHis` |
| `iMid` | Konfigurasi |
| `payMethod`, `currency`, `mitraCd` | `06`, `IDR`, `IDNA` |
| `amt` | `grand_total` sebagai string |
| `referenceNo` | `midtrans_order_id` milik attempt |
| `goodsNm` | `Pesanan {order_number}` |
| `billingNm` | `customer_name`, dipotong 100 karakter |
| `billingPhone`, `deliveryPhone` | `customer_phone`, angka saja, dipotong 15 |
| `billingEmail` | `customer_email` |
| `billingAddr`, `deliveryAddr` | `shipping_address`, dipotong 100 |
| `billingCity`, `billingState`, `billingPostCd` dan padanan `delivery*` | Hasil urai alamat (7.1) |
| `billingCountry`, `deliveryCountry` | `Indonesia` |
| `deliveryNm` | `customer_name`, dipotong 30 |
| `cartData` | Lihat 7.2 |
| `sellers` | Lihat 7.3 |
| `userIP` | IP pelanggan bila IPv4; selain itu `127.0.0.1` |
| `userAgent` | Header User-Agent, dipotong 255 |
| `dbProcessUrl` | `route('payments.nicepay.notification')` |
| `instmntType`, `instmntMon` | Lihat 7.4 |
| `merchantToken` | Rumus registration |

`callBackUrl` tidak dikirim di Registration. Dokumentasi menandainya opsional, tetapi lingkungan development Nicepay menolak Registration Paylater yang memuatnya dengan `resultCd 8003` (lihat 13.1). Field itu hanya dikirim di langkah Payment.

### 7.1 Alamat

`shipping_destination_label` dari RajaOngkir berformat `KELURAHAN, KECAMATAN, KOTA, PROVINSI, KODEPOS`. Label dipecah dengan koma. Bila hasilnya tepat lima bagian dan bagian terakhir berupa angka, kota, provinsi, dan kode pos diambil dari bagian ketiga, keempat, dan kelima.

Bila pesanan pickup (`Order::isPickup()`) atau label tidak bisa diurai, dipakai `NICEPAY_STORE_CITY`, `NICEPAY_STORE_STATE`, `NICEPAY_STORE_POSTCODE`. Untuk label yang gagal diurai pada pesanan non-pickup, peringatan dicatat di log.

### 7.2 `cartData`

- Satu baris per `OrderItem`: `goods_id` (id varian, atau id produk, atau id item, sebagai string), `goods_name` (nama produk dan varian, dipotong 100), `goods_amt` (harga satuan), `goods_quantity`, `goods_type` = `others`, `goods_url` (URL halaman produk, atau URL beranda bila produknya sudah dihapus), `goods_sellers_id` = `iMid`, `goods_sellers_name` = `site_name`.
- Bila `shipping_cost` lebih dari nol: satu baris dengan `goods_id` = `shippingfee`, jumlah 1.
- Bila ada diskon: satu baris dengan `goods_id` = `discount`, jumlah 1, nilai positif. Diskon dibatasi `min(discount_amount, subtotal)`, sama seperti `OrderService`.
- Pemeriksaan: jumlah barang ditambah ongkir dikurangi diskon harus sama dengan `grand_total`.
- Bila pemeriksaan gagal atau panjang JSON melebihi 4.000 karakter, dikirim satu baris `Pesanan {order_number}` dengan `goods_amt` = `grand_total`, dan peringatan dicatat di log.

### 7.3 `sellers`

Satu penjual: `sellersId` = `iMid`, `sellersNm` = `site_name`, `sellersEmail` = `site_email`, `sellersUrl` = URL aplikasi, dan `sellersAddress` berisi `site_name`, `site_address`, kota dan kode pos toko, `site_phone` (angka saja), serta `sellerCountry` = `ID`.

### 7.4 Tenor default

`instmntMon` = `1` dan `instmntType` = `1` bila `grand_total` sampai 2.000.000. Selain itu `instmntMon` = `3` dan `instmntType` = `2`. Nilai ini sementara sampai uji nyata (bagian 13).

## 8. Pemetaan status inquiry

| Kondisi | Attempt | Pesanan |
|---|---|---|
| `status` = 0 | `PAID` lewat `PaymentSettlementService::paid()` dengan `amt` dari inquiry | Dilunasi bila `amt` sama dengan `grand_total`. Aktor: `Indodana via Nicepay (otomatis)`. |
| `status` = 3 atau 9, attempt belum lewat `expired_at` | `PENDING` | Tidak berubah |
| `status` = 3 atau 9, attempt sudah lewat `expired_at` | `EXPIRED` | Aktivitas "pembayaran gagal" dicatat bila pesanan belum lunas |
| `status` = 8 | `FAILED` | Aktivitas "pembayaran gagal" dicatat bila pesanan belum lunas |
| `status` = 1 atau 2 | `CANCELLED` | Tidak diubah otomatis; peringatan dicatat di log untuk ditinjau admin |
| `resultCd` bukan `0000` atau tanpa `status` | Tidak berubah | Tidak berubah |

Sumber kebenaran status lunas adalah Status Inquiry. Callback dan notifikasi hanya memicu inquiry.

## 9. Alur

1. Halaman invoice menampilkan Indodana bila `PaymentMethods::available()` memuatnya.
2. Pelanggan menekan Bayar. `PaymentAttemptService` membuat attempt (`provider` = `nicepay`), lalu `NicepayPaylaterService::start()` memanggil Registration dan menyimpan `tXid`.
3. Browser diarahkan ke `GET /payments/nicepay/pay/{order:uuid}`, yang mengirim form ke langkah Payment Nicepay. "Lanjutkan Pembayaran" memakai rute yang sama.
4. Pelanggan memilih tenor dan menyetujui di Indodana.
5. Pelanggan kembali ke `/payments/nicepay/callback` (GET atau POST). Kita menjalankan inquiry lalu mengarahkan ke halaman pesanan.
6. Nicepay mengirim notifikasi ke `POST /payments/nicepay/notification`. Token diverifikasi, event dicatat, inquiry dijalankan, pesanan dilunasi.
7. Pengaman: `wire:poll.30s` di halaman invoice dan `payments:reconcile` tiap 15 menit.

Rute baru:

| Metode | Path | Nama |
|---|---|---|
| GET | `/payments/nicepay/pay/{order:uuid}` | `payments.nicepay.pay` |
| GET, POST | `/payments/nicepay/callback` | `payments.nicepay.callback` |
| POST | `/payments/nicepay/notification` | `payments.nicepay.notification` |

## 10. Penanganan error

| Kejadian | Perilaku |
|---|---|
| Registration gagal (HTTP gagal atau `resultCd` bukan `0000`) | `BusinessRuleException` di dalam transaksi, attempt ikut rollback, pelanggan melihat "Gagal memulai pembayaran. Silakan coba lagi.", `resultCd` dan `resultMsg` dicatat di log |
| Token notifikasi tidak valid | Event dicatat `invalid_signature`, balasan 403 |
| `referenceNo` tidak dikenal | Event dicatat `ignored`, balasan 200 |
| Nominal inquiry tidak sama dengan total | Tidak dilunasi, event `ignored` dengan catatan perlu tinjauan |
| Notifikasi ganda | `event_hash` unik dan kunci baris pesanan |
| Pelanggan pindah dari Indodana ke metode lain | Attempt lama `SUPERSEDED`, Cancel dikirim ke Nicepay di dalam `rescue` |
| Pelanggan pindah dari Midtrans ke Indodana | Attempt lama `SUPERSEDED`, cancel dikirim ke Midtrans |
| Indodana terbayar setelah pesanan lunas lewat attempt lain | Attempt ditandai `PAID`, event `ignored` dengan catatan perlu review refund |
| Inquiry timeout atau gagal | Sinkronisasi berhenti tanpa mengubah apa pun; percobaan berikutnya dari polling atau rekonsiliasi |

Whitelist IP tidak dipasang. `bootstrap/app.php` mempercayai header proxy dari alamat mana pun, sehingga IP pengirim bisa dipalsukan lewat `X-Forwarded-For`. Pengamannya adalah verifikasi token dan konfirmasi lewat inquiry.

## 11. Antarmuka

- Satu kartu baru di kisi metode: label `Indodana`, tipe `Cicilan tanpa kartu`, warna merek diambil dari logo resmi Indodana.
- Kartu attempt aktif untuk Indodana menampilkan nama metode dan masa berlaku, tanpa nomor VA.
- Halaman redirect: satu kalimat "Mengarahkan ke Indodana…" dan tombol manual untuk browser tanpa JavaScript.

## 12. Pengujian

Semua tes memakai `Http::fake()`. Tidak ada panggilan nyata.

- `NicepayClientTest`: keempat rumus token dengan masukan tetap, base URL per mode, verifikasi token notifikasi.
- `NicepayPaylaterServiceTest`: field payload registration; jumlah `cartData` dengan ongkir dan diskon; baris tunggal sebagai cadangan; alamat dari label; alamat toko untuk pickup; `userIP` untuk IPv6; pemetaan tiap baris tabel bagian 8.
- `PaymentAttemptServiceTest`: attempt Indodana berlaku 24 jam; pakai ulang; Indodana ke VA memanggil cancel Nicepay; VA ke Indodana memanggil cancel Midtrans; registration gagal tidak meninggalkan attempt.
- `NicepayNotificationControllerTest`: valid lalu lunas; token salah 403; referensi tak dikenal; duplikat; nominal tak cocok.
- `NicepayPaymentControllerTest`: halaman redirect memuat empat field form; attempt tidak valid diarahkan ke halaman pesanan; callback memicu inquiry.
- `PaymentMethodsTest`: Indodana tersembunyi saat flag mati, kredensial kosong, non-admin dalam mode admin, total di luar batas, email lebih dari 40 karakter.
- `ReconcilePaymentsTest`: kedua penyedia diproses.
- Regresi: `MidtransWebhookServiceTest` dan `MidtransNotificationControllerTest` lolos tanpa diubah. `MidtransPaymentAttemptServiceTest` dan `MidtransInvoicePageTest` hanya berganti kelas yang dipanggil; pemeriksaannya tetap.

## 13. Peluncuran dan hal yang harus diverifikasi

Urutan:

1. Pemindahan inti bersama, tanpa perubahan perilaku. Semua tes lama lolos.
2. Modul Nicepay beserta tesnya, flag mati.
3. Uji asap ke `dev.nicepay.co.id` dengan kredensial uji publik Nicepay (`IONPAYTEST`). Hasilnya informasi; penolakan Indodana di akun uji bukan penghalang.
4. Produksi: jalankan migrasi, bangun ulang aset (`npm run build`, karena halaman redirect memakai kelas Tailwind yang belum ada di build lama), isi kredensial, `NICEPAY_ENABLED=true`, `NICEPAY_ADMIN_ONLY=true`. Admin membuat satu pesanan kecil sungguhan, membayar lewat Indodana, lalu transaksi di-void.
5. Sesuaikan berdasarkan hasil langkah 4.
6. `NICEPAY_ADMIN_ONLY=false`.

Yang diverifikasi pada langkah 4, karena dokumentasi tidak menjawabnya:

| Pertanyaan | Asumsi awal | Bila asumsi salah |
|---|---|---|
| Apakah `instmntMon` mengikat? | Tidak; pelanggan memilih di Indodana | Tambah pemilih tenor di halaman invoice, dibatasi sesuai nominal |
| Format ongkir dan diskon di `cartData` | Baris `shippingfee` dan `discount` seperti contoh Kredivo | Pakai baris tunggal senilai total |
| Langkah Payment: POST atau GET | Form POST | Ganti form menjadi redirect GET |
| Balasan yang diharapkan untuk notifikasi | `200 OK` | Sesuaikan isi balasan |
| Zona waktu `timeStamp` | `Asia/Jakarta` | Sesuaikan zona waktu |
| `cancelType` untuk transaksi belum dibayar | `1` | Sesuaikan kode, atau hilangkan cancel untuk attempt yang belum dibayar |
| `userIP` untuk pelanggan IPv6 | `127.0.0.1` diterima | Kirim IP server |

### 13.1 Hasil uji asap ke lingkungan development (2 Oktober 2026)

Diuji ke `https://dev.nicepay.co.id` dengan kredensial uji publik `IONPAYTEST`, tanpa transaksi sungguhan.

| Yang diuji | Hasil |
|---|---|
| Registration Virtual Account sebagai pembanding (token, `timeStamp` WIB, endpoint, JSON) | `0000 SUCCESS`. Rumus token dan format dasar benar. |
| Registration Paylater dengan `callBackUrl` | `8003 Order registration data error`. Field dihapus dari payload. |
| Registration Paylater buatan `NicepayRegistrationPayload`, URL publik | Lolos validasi format, berhenti di `9108` (Paylater tidak aktif di akun uji). Ini batas akun uji, bukan cacat payload. |
| Registration Paylater dengan `goods_url` atau `sellersUrl` berisi `http://localhost:8000` | `P380 format error [cartData]`. Uji lokal butuh `APP_URL` publik (misalnya tunnel); produksi tidak terdampak. |
| Contoh `cartData` dari halaman Registration dokumentasi (angka tanpa tanda kutip) | `P380`. Contoh dari halaman Seamless (semua nilai string) lolos. Payload kita memakai string. |

Karena Paylater tidak aktif di akun uji, langkah Payment, Status Inquiry, Cancel, dan notifikasi belum pernah dijalankan terhadap Nicepay. Semuanya baru terverifikasi lewat tes dengan respons tiruan, dan menunggu transaksi sungguhan di langkah 4.

## 14. Utang yang dicatat

- Kolom `midtrans_order_id`, `midtrans_transaction_id`, `snap_request_payload`, dan `snap_response_payload` kini menyimpan data dua penyedia dengan nama yang hanya menyebut satu. Ganti nama saat ada penyedia ketiga.
- Refund Indodana untuk pesanan lunas yang dibatalkan admin masih manual.
