# Indodana PayLater lewat Nicepay Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah metode pembayaran Indodana PayLater lewat Nicepay (API Non-SNAP) di halaman invoice, berdampingan dengan tujuh kanal Midtrans yang tetap berjalan tanpa perubahan perilaku.

**Architecture:** Siklus attempt dan logika pelunasan dipindahkan dari kelas Midtrans ke dua kelas netral (`PaymentAttemptService`, `PaymentSettlementService`). Midtrans dan Nicepay menjadi cabang `match` pada kolom `provider`, tanpa interface. Status lunas Nicepay selalu dikonfirmasi lewat Status Inquiry; notifikasi dan callback hanya pemicu.

**Tech Stack:** Laravel 13, Livewire 4 (Volt single-file pages), Flux UI, PHPUnit 12, MySQL, `Illuminate\Support\Facades\Http`.

**Spec:** `specs/2026-10-02-nicepay-indodana-design.md`

## Global Constraints

- **Aturan `CLAUDE.md` proyek berlaku untuk setiap file yang disentuh:** tanpa komentar anotasi `//` di kode implementasi maupun tes; hapus blok komentar pemisah section yang sudah ada di file PHP yang disentuh; hapus komentar Blade `{{-- --}}`. Yang boleh: PHPDoc `/** */`, komentar yang menjelaskan logika teknis yang tidak jelas dari kodenya, dan penanda section HTML seperti `<!-- ABOUT US -->`. Penghapusan komentar tidak boleh mengubah perilaku.
- **Penanda `# === ... ===` di `.env.example` dipertahankan** dan ditambah satu untuk Nicepay; file itu bukan kode implementasi.
- **Antislop diterapkan selama pengerjaan.** Sebelum menulis UI atau teks yang dilihat pelanggan (Task 6 dan Task 9), muat skill `antislop:antislop`, `antislop:antislop-ui`, `antislop:antislop-copywriting`, `antislop:antislop-human`, dan `antislop:antislop-layoutmobile`.
- **Folder `docs/` terlarang:** jangan dibaca, ditulis, dicari, atau di-commit, termasuk lewat shell.
- **Tidak ada panggilan HTTP nyata di tes.** Setiap kelas tes baru memanggil `Http::preventStrayRequests()` di `setUp()`.
- **`merchantKey` tidak pernah ditulis ke log, pesan error, atau respons.**
- **Nilai tetap dari spec:** `payMethod` = `06`; `mitraCd` = `IDNA`; `currency` = `IDR`; base URL `https://dev.nicepay.co.id` dan `https://www.nicepay.co.id`; total pesanan Indodana 10.000 sampai 50.000.000; email pelanggan maksimal 40 karakter; masa berlaku attempt Nicepay 1.440 menit; `timeStamp` berformat `YmdHis` zona `Asia/Jakarta`.
- **Tes lama yang tidak boleh diubah:** `MidtransWebhookServiceTest`, `MidtransNotificationControllerTest`, dan `MidtransInvoicePageTest` harus lolos tanpa disentuh di Task 1 sampai Task 8.
- **Menjalankan tes:** `php artisan test --filter=NamaKelasTes`. Database tes adalah MySQL `jajarwayang_test` (lihat `phpunit.xml`) dan harus sudah ada di Laragon.
- **Gaya kode:** `declare(strict_types=1);` di setiap file PHP baru, sesuai file yang ada. Jalankan `vendor/bin/pint --dirty` sebelum setiap commit.
- **Commit:** format `tipe(cakupan): ringkasan`, diakhiri baris `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Bekerja di branch `feat/nicepay-indodana`. Tidak ada push tanpa diminta.

## Review Focus

1. **Klik ganda pada "Bayar" untuk Indodana.** Harapan: satu attempt dan satu panggilan Registration. Tes di Task 8.
2. **Indodana dibayar setelah pelanggan pindah ke metode lain.** Attempt Indodana sudah `SUPERSEDED`, pesanan belum lunas. Harapan: pesanan tetap dilunasi dan attempt terbuka lain dibatalkan ke penyedianya. Tes di Task 5.
3. **Non-admin mengirim `indodana` secara paksa saat mode khusus admin.** Harapan: ditolak validasi, tidak ada attempt. Tes di Task 9.
4. **Tautan bayar dibuka lagi setelah attempt kedaluwarsa atau pesanan lunas.** Harapan: diarahkan ke halaman pesanan, tanpa form ke Nicepay. Tes di Task 6.
5. **Nomor telepon berisi `+62`, spasi, atau tanda hubung, dan nama atau alamat yang sangat panjang.** Harapan: telepon dikirim angka saja maksimal 15 digit, field lain dipotong sesuai batas. Tes di Task 7.

## Struktur file

| File | Status | Tanggung jawab |
|---|---|---|
| `database/migrations/2026_10_02_000000_add_provider_to_payment_tables.php` | baru | Kolom `provider` di dua tabel |
| `app/Services/Payments/PaymentSettlementService.php` | baru | Pelunasan, pending, gagal, pembatalan ke penyedia |
| `app/Services/Payments/PaymentMethods.php` | baru | Daftar metode dan syarat ketersediaan |
| `app/Services/Payments/PaymentAttemptService.php` | baru | Siklus hidup attempt, pemilihan penyedia |
| `app/Services/Payments/Nicepay/NicepayClient.php` | baru | HTTP ke Nicepay dan pembuatan token |
| `app/Services/Payments/Nicepay/NicepayRegistrationPayload.php` | baru | Menyusun payload Registration |
| `app/Services/Payments/Nicepay/NicepayPaylaterService.php` | baru | `start()`, `sync()`, `handleNotification()` |
| `app/Http/Controllers/Payment/NicepayNotificationController.php` | baru | Endpoint notifikasi |
| `app/Http/Controllers/Payment/NicepayPaymentController.php` | baru | Halaman redirect dan callback |
| `resources/views/payments/nicepay-redirect.blade.php` | baru | Form yang terkirim otomatis ke Nicepay |
| `app/Console/Commands/ReconcilePayments.php` | ganti nama dari `ReconcileMidtransPayments.php` | Rekonsiliasi kedua penyedia |
| `app/Services/Payments/Midtrans/MidtransWebhookService.php` | ubah | Meneruskan keputusan ke `PaymentSettlementService` |
| `app/Services/Payments/Midtrans/MidtransPaymentAttemptService.php` | ubah | Tinggal `start()`, `sync()`, `supportedMethods()` |
| `app/Services/OrderActivityService.php` | ubah | Label metode dari `PaymentMethods`, aktor per penyedia |
| `app/Models/PaymentAttempt.php`, `app/Models/PaymentWebhookEvent.php` | ubah | `provider` |
| `resources/views/pages/storefront/⚡order-success.blade.php` | ubah | Memakai kelas netral, redirect untuk Nicepay |
| `routes/web.php`, `routes/console.php`, `bootstrap/app.php`, `config/services.php`, `.env.example` | ubah | Rute, jadwal, pengecualian CSRF, konfigurasi |

`NicepayRegistrationPayload` tidak disebut di spec sebagai kelas tersendiri. Di sini dipisah dari `NicepayPaylaterService` supaya penyusunan payload bisa diuji tanpa HTTP.

Urutan task: Task 1 sampai 3 adalah pemindahan tanpa perubahan perilaku dan bisa dirilis sendiri. Task 4 sampai 9 membangun Nicepay di balik flag yang mati. Task 10 adalah verifikasi.

---

### Task 1: Kolom `provider` dan `PaymentSettlementService`

**Files:**
- Create: `database/migrations/2026_10_02_000000_add_provider_to_payment_tables.php`
- Create: `app/Services/Payments/PaymentSettlementService.php`
- Modify: `app/Models/PaymentAttempt.php`
- Modify: `app/Models/PaymentWebhookEvent.php`
- Modify: `app/Services/Payments/Midtrans/MidtransWebhookService.php`
- Test: `tests/Feature/Payments/PaymentSettlementServiceTest.php`

**Interfaces:**
- Consumes: `OrderService::markAsPaid(Order $order, ?string $actor = null): Order`, `OrderActivityService::paymentFailed(Order $order, string $status): void`, `MidtransClient::cancel(string $midtransOrderId): array`.
- Produces:
  - `PaymentSettlementService::paid(Order $order, PaymentAttempt $attempt, int $paidAmount, string $actor, ?PaymentWebhookEvent $event = null): void`
  - `PaymentSettlementService::pending(Order $order, PaymentAttempt $attempt, ?PaymentWebhookEvent $event = null): void`
  - `PaymentSettlementService::failed(Order $order, PaymentAttempt $attempt, PaymentAttemptStatus $status, string $reason, ?PaymentWebhookEvent $event = null): void`
  - `PaymentSettlementService::cancelAtGateway(PaymentAttempt $attempt): void`
  - Atribut `provider` (string, default `midtrans`) pada `PaymentAttempt` dan `PaymentWebhookEvent`.

- [ ] **Step 1: Tulis tes yang gagal**

Buat `tests/Feature/Payments/PaymentSettlementServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentSettlementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.is_production' => false,
        ]);

        Http::preventStrayRequests();
        Http::fake(['*/v2/*/cancel' => Http::response(['status_code' => '200'], 200)]);
        Queue::fake();

        $this->service = app(PaymentSettlementService::class);
    }

    private function attemptFor(Order $order, int $seq = 1, PaymentAttemptStatus $status = PaymentAttemptStatus::PENDING): PaymentAttempt
    {
        return PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'attempt_sequence' => $seq,
            'midtrans_order_id' => $order->order_number.'-A'.$seq,
            'status' => $status,
            'gross_amount' => $order->grand_total,
        ]);
    }

    public function test_attempt_berprovider_midtrans_secara_default(): void
    {
        $attempt = $this->attemptFor(Order::factory()->create());

        $this->assertSame('midtrans', $attempt->provider);
        $this->assertSame('midtrans', $attempt->fresh()->provider);
    }

    public function test_paid_melunasi_order_dan_membatalkan_attempt_terbuka_lain(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $late = $this->attemptFor($order, 1, PaymentAttemptStatus::SUPERSEDED);
        $open = $this->attemptFor($order, 2);
        $order->update(['active_payment_attempt_id' => $open->id]);

        $this->service->paid($order->fresh(), $late, 150000, 'Tes (otomatis)');

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame($late->id, $order->fresh()->active_payment_attempt_id);
        $this->assertSame(PaymentAttemptStatus::PAID, $late->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $open->fresh()->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/'.$open->midtrans_order_id.'/cancel'));
    }

    public function test_paid_dengan_nominal_berbeda_tidak_melunasi(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->attemptFor($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->paid($order->fresh(), $attempt, 149999, 'Tes (otomatis)');

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->fresh()->status);
    }

    public function test_failed_menandai_attempt_tanpa_mengubah_order(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->attemptFor($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->failed($order->fresh(), $attempt, PaymentAttemptStatus::EXPIRED, 'expire');

        $this->assertSame(PaymentAttemptStatus::EXPIRED, $attempt->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_cancel_attempt_midtrans_dikirim_ke_midtrans(): void
    {
        $attempt = $this->attemptFor(Order::factory()->create());

        $this->service->cancelAtGateway($attempt);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'midtrans.com/v2/'.$attempt->midtrans_order_id.'/cancel'));
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter=PaymentSettlementServiceTest`
Expected: FAIL, `Class "App\Services\Payments\PaymentSettlementService" not found`.

- [ ] **Step 3: Buat migrasi**

Buat `database/migrations/2026_10_02_000000_add_provider_to_payment_tables.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->string('provider')->default('midtrans')->after('order_id')->index();
        });

        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->string('provider')->default('midtrans')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->dropColumn('provider');
        });

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropIndex(['provider']);
            $table->dropColumn('provider');
        });
    }
};
```

- [ ] **Step 4: Tambah `provider` ke kedua model**

Di `app/Models/PaymentAttempt.php`, tambahkan `'provider',` setelah `'order_id',` di `$fillable`, dan tambahkan properti ini tepat di atas `$fillable`:

```php
    protected $attributes = [
        'provider' => 'midtrans',
    ];
```

Di `app/Models/PaymentWebhookEvent.php`, tambahkan `'provider',` sebagai elemen pertama `$fillable`, dan properti `$attributes` yang sama di atasnya.

- [ ] **Step 5: Buat `PaymentSettlementService`**

Buat `app/Services/Payments/PaymentSettlementService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\OrderActivityService;
use App\Services\OrderService;
use App\Services\Payments\Midtrans\MidtransClient;
use Illuminate\Support\Facades\Log;

/**
 * Pemanggil wajib sudah memegang kunci baris (lockForUpdate) untuk order dan
 * attempt di dalam transaksi; kelas ini tidak mengunci sendiri.
 */
class PaymentSettlementService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderActivityService $activities,
        private readonly MidtransClient $midtrans,
    ) {}

    public function paid(Order $order, PaymentAttempt $attempt, int $paidAmount, string $actor, ?PaymentWebhookEvent $event = null): void
    {
        if ($order->status === OrderStatus::PAID) {
            $attempt->update([
                'status' => PaymentAttemptStatus::PAID,
                'paid_at' => $attempt->paid_at ?? now(),
            ]);

            if ((int) $order->active_payment_attempt_id === (int) $attempt->id) {
                $event?->update([
                    'processing_status' => 'ignored',
                    'notes' => 'Order sudah lunas. Notifikasi paid duplikat diabaikan.',
                ]);

                return;
            }

            Log::warning('Pembayaran ganda dari attempt lain pada order yang sudah lunas.', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'provider' => $attempt->provider,
                'reference' => $attempt->midtrans_order_id,
            ]);

            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Pembayaran dari attempt lain saat order sudah lunas. Perlu review refund.',
            ]);

            return;
        }

        if ($paidAmount !== (int) $order->grand_total) {
            Log::warning('Nominal pembayaran tidak cocok dengan total order.', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'provider' => $attempt->provider,
                'paid_amount' => $paidAmount,
                'grand_total' => $order->grand_total,
            ]);

            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Nominal pembayaran tidak cocok dengan total order. Perlu review.',
            ]);

            return;
        }

        $attempt->update([
            'status' => PaymentAttemptStatus::PAID,
            'paid_at' => now(),
        ]);

        $order->forceFill([
            'paid_at' => now(),
            'active_payment_attempt_id' => $attempt->id,
        ])->save();

        $this->orderService->markAsPaid($order, $actor);

        $this->cancelOtherOpenAttempts($order, $attempt);

        $event?->update([
            'processing_status' => 'processed',
            'notes' => 'Order ditandai lunas.',
        ]);
    }

    public function pending(Order $order, PaymentAttempt $attempt, ?PaymentWebhookEvent $event = null): void
    {
        if ($order->status === OrderStatus::PAID) {
            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Order sudah lunas; notifikasi pending diabaikan.',
            ]);

            return;
        }

        if ((int) $order->active_payment_attempt_id !== (int) $attempt->id) {
            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Pending dari attempt non-aktif diabaikan.',
            ]);

            return;
        }

        $attempt->update(['status' => PaymentAttemptStatus::PENDING]);

        $event?->update([
            'processing_status' => 'processed',
            'notes' => 'Status pending diproses.',
        ]);
    }

    public function failed(Order $order, PaymentAttempt $attempt, PaymentAttemptStatus $status, string $reason, ?PaymentWebhookEvent $event = null): void
    {
        $attempt->update([
            'status' => $status,
            'expired_at' => $status === PaymentAttemptStatus::EXPIRED ? now() : $attempt->expired_at,
        ]);

        if ($order->status !== OrderStatus::PAID) {
            $this->activities->paymentFailed($order, $reason);
        }

        $event?->update([
            'processing_status' => 'processed',
            'notes' => 'Status gagal diproses: '.$reason,
        ]);
    }

    public function cancelAtGateway(PaymentAttempt $attempt): void
    {
        rescue(fn () => $this->midtrans->cancel($attempt->midtrans_order_id), report: false);
    }

    private function cancelOtherOpenAttempts(Order $order, PaymentAttempt $paidAttempt): void
    {
        $order->paymentAttempts()
            ->where('id', '!=', $paidAttempt->id)
            ->get()
            ->each(function (PaymentAttempt $other): void {
                if ($other->isOpen()) {
                    $other->update(['status' => PaymentAttemptStatus::SUPERSEDED]);
                    $this->cancelAtGateway($other);
                }
            });
    }
}
```

- [ ] **Step 6: Jadikan `MidtransWebhookService` pemanggil `PaymentSettlementService`**

Di `app/Services/Payments/Midtrans/MidtransWebhookService.php`:

1. Ganti konstruktor menjadi:

```php
    public function __construct(
        private readonly MidtransSignatureVerifier $signatureVerifier,
        private readonly MidtransStatusMapper $statusMapper,
        private readonly PaymentSettlementService $settlement,
    ) {}
```

2. Ganti seluruh method `route()` menjadi:

```php
    private function route(Order $order, PaymentAttempt $attempt, array $payload, ?PaymentWebhookEvent $event): void
    {
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;

        if ($this->statusMapper->isPaid($transactionStatus, $fraudStatus)) {
            $this->settlement->paid(
                $order,
                $attempt,
                (int) round((float) ($payload['gross_amount'] ?? 0)),
                'Midtrans (otomatis)',
                $event,
            );

            return;
        }

        if ($transactionStatus === 'pending') {
            $this->settlement->pending($order, $attempt, $event);

            return;
        }

        if ($this->statusMapper->isFailureLike($transactionStatus)) {
            $this->settlement->failed(
                $order,
                $attempt,
                $this->statusMapper->attemptStatus($transactionStatus, $fraudStatus),
                (string) $transactionStatus,
                $event,
            );

            return;
        }

        $event?->update([
            'processing_status' => 'ignored',
            'notes' => 'transaction_status tidak ditangani: '.(string) $transactionStatus,
        ]);
    }
```

3. Hapus method `handlePaid()`, `handlePending()`, `handleFailed()`, dan `cancelOtherOpenAttempts()`.
4. Di blok `use`, hapus `App\Enums\OrderStatus`, `App\Enums\PaymentAttemptStatus`, `App\Services\OrderActivityService`, `App\Services\OrderService`, dan `Illuminate\Support\Facades\Log`. Tambahkan `use App\Services\Payments\PaymentSettlementService;`.
5. `handle()`, `syncFromStatus()`, `applyToAttempt()`, dan `storeEvent()` tidak diubah.

- [ ] **Step 7: Jalankan tes baru dan seluruh tes pembayaran**

Run: `php artisan test --filter=PaymentSettlementServiceTest`
Expected: PASS, 5 tes.

Run: `php artisan test tests/Feature/Payments`
Expected: PASS semua, termasuk `MidtransWebhookServiceTest` tanpa perubahan.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_10_02_000000_add_provider_to_payment_tables.php app/Models/PaymentAttempt.php app/Models/PaymentWebhookEvent.php app/Services/Payments/PaymentSettlementService.php app/Services/Payments/Midtrans/MidtransWebhookService.php tests/Feature/Payments/PaymentSettlementServiceTest.php
git commit -m "refactor(payments): pindahkan logika pelunasan ke PaymentSettlementService"
```

---

### Task 2: `PaymentMethods` dan `PaymentAttemptService`

**Files:**
- Create: `app/Services/Payments/PaymentMethods.php`
- Create: `app/Services/Payments/PaymentAttemptService.php`
- Modify: `app/Services/Payments/Midtrans/MidtransPaymentAttemptService.php`
- Modify: `app/Services/Payments/PaymentSettlementService.php` (method `failed()`)
- Modify: `app/Services/OrderActivityService.php`
- Modify: `resources/views/pages/storefront/⚡order-success.blade.php` (blok PHP saja)
- Modify: `tests/Feature/Payments/MidtransPaymentAttemptServiceTest.php`
- Test: `tests/Feature/Payments/PaymentMethodsTest.php`

**Interfaces:**
- Consumes: `PaymentSettlementService::cancelAtGateway(PaymentAttempt $attempt): void` dari Task 1.
- Produces:
  - `PaymentMethods::MIDTRANS = 'midtrans'`, `PaymentMethods::NICEPAY = 'nicepay'`
  - `PaymentMethods::providerFor(string $method): ?string`
  - `PaymentMethods::name(string $method): string`
  - `PaymentMethods::actorFor(string $provider): string`
  - `PaymentMethods::available(Order $order, ?User $user): array` (kunci metode ke `['provider', 'name', 'label', 'type', 'brand']`)
  - `PaymentAttemptService::createOrReuseActiveAttempt(Order $order, string $paymentMethod): PaymentAttempt`
  - `PaymentAttemptService::syncAttempt(PaymentAttempt $attempt): void`
  - `PaymentAttemptService::syncActiveAttempt(Order $order): void`
  - `MidtransPaymentAttemptService::start(Order $order, PaymentAttempt $attempt, string $paymentMethod): void`
  - `MidtransPaymentAttemptService::sync(PaymentAttempt $attempt): void`
  - `OrderActivityService::paymentFailed(Order $order, string $status, ?string $actor = null): void`

- [ ] **Step 1: Arahkan tes attempt yang ada ke kelas baru (tes gagal)**

Di `tests/Feature/Payments/MidtransPaymentAttemptServiceTest.php`:

1. Tambahkan `use App\Services\Payments\PaymentAttemptService;` di blok `use`. Baris `use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;` tetap ada karena `supportedMethods()` dan `METHOD_MAP` masih dipakai.
2. Ganti properti menjadi `private PaymentAttemptService $service;`.
3. Ganti baris terakhir `setUp()` menjadi `$this->service = app(PaymentAttemptService::class);`.
4. Sesuai aturan `CLAUDE.md`, bersihkan komentar di file ini tanpa mengubah pemeriksaan:
   - Dua baris komentar di `setUp()` tentang urutan stub (`// Hanya cancel yang di-fake global; ...`) menjelaskan logika yang tidak jelas dari kodenya. Pindahkan menjadi PHPDoc di atas `setUp()`:

```php
    /**
     * Hanya cancel yang di-fake global. Snap di-fake per tes supaya tes kegagalan
     * bisa mendaftarkan stub 500 lebih dulu (Http::fake memakai stub pertama yang cocok).
     */
    protected function setUp(): void
```

   - Hapus komentar anotasi lainnya: `// Cancel best effort ke Midtrans untuk attempt lama.`, `// Elemen pertama jadi default terpilih di halaman invoice.`, kedua `// diharapkan.` (blok `catch` dibiarkan kosong), `// Transaksi di-rollback: ...`, `// Payload Snap yang dikirim ke Midtrans memuat blok expiry (menit).`, `// Attempt pertama open (pending).`, `// Attempt kedua open untuk order yang sama harus ditolak oleh unique active_guard.`, dan `// Midtrans Get Status mengembalikan settlement ...`.
   - PHPDoc di atas `test_setiap_metode_dikirim_sebagai_enabled_payments_yang_benar` tetap.

Buat `tests/Feature/Payments/PaymentMethodsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tujuh_kanal_midtrans_tersedia_dengan_urutan_tetap(): void
    {
        $order = Order::factory()->create();

        $this->assertSame(
            MidtransPaymentAttemptService::supportedMethods(),
            array_keys(PaymentMethods::available($order, null)),
        );
    }

    public function test_setiap_metode_punya_data_tampilan(): void
    {
        $order = Order::factory()->create();

        foreach (PaymentMethods::available($order, null) as $key => $method) {
            $this->assertSame(['provider', 'name', 'label', 'type', 'brand'], array_keys($method), $key);
        }
    }

    public function test_provider_dan_nama_metode(): void
    {
        $this->assertSame(PaymentMethods::MIDTRANS, PaymentMethods::providerFor('bni_va'));
        $this->assertNull(PaymentMethods::providerFor('bca_va'));
        $this->assertSame('Virtual Account BNI', PaymentMethods::name('bni_va'));
        $this->assertSame('bca_va', PaymentMethods::name('bca_va'));
    }

    public function test_aktor_per_penyedia(): void
    {
        $this->assertSame('Midtrans (otomatis)', PaymentMethods::actorFor(PaymentMethods::MIDTRANS));
        $this->assertSame('Indodana via Nicepay (otomatis)', PaymentMethods::actorFor(PaymentMethods::NICEPAY));
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter="PaymentMethodsTest|MidtransPaymentAttemptServiceTest"`
Expected: FAIL, `Class "App\Services\Payments\PaymentMethods" not found` dan `PaymentAttemptService` tidak ditemukan.

- [ ] **Step 3: Buat `PaymentMethods`**

Buat `app/Services/Payments/PaymentMethods.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;

class PaymentMethods
{
    public const MIDTRANS = 'midtrans';

    public const NICEPAY = 'nicepay';

    /**
     * Urutan menentukan urutan tampil di halaman invoice; elemen pertama jadi
     * metode terpilih default. Kunci metode Midtrans harus sama dengan
     * MidtransPaymentAttemptService::METHOD_MAP.
     */
    private const METHODS = [
        'gopay' => ['provider' => self::MIDTRANS, 'name' => 'QRIS', 'label' => 'QRIS', 'type' => 'Scan QR', 'brand' => '#00aed6'],
        'akulaku' => ['provider' => self::MIDTRANS, 'name' => 'Akulaku PayLater', 'label' => 'Akulaku', 'type' => 'Cicilan tanpa kartu', 'brand' => '#e02020'],
        'bsi_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BSI', 'label' => 'BSI', 'type' => 'Virtual Account', 'brand' => '#00a39d'],
        'bni_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BNI', 'label' => 'BNI', 'type' => 'Virtual Account', 'brand' => '#ee7203'],
        'bri_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BRI', 'label' => 'BRI', 'type' => 'Virtual Account', 'brand' => '#00529c'],
        'echannel' => ['provider' => self::MIDTRANS, 'name' => 'Mandiri Bill Payment', 'label' => 'Mandiri', 'type' => 'Bill Payment', 'brand' => '#003d79'],
        'permata_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account Permata', 'label' => 'Permata', 'type' => 'Virtual Account', 'brand' => '#00854a'],
    ];

    public static function providerFor(string $method): ?string
    {
        return self::METHODS[$method]['provider'] ?? null;
    }

    public static function name(string $method): string
    {
        return self::METHODS[$method]['name'] ?? $method;
    }

    public static function actorFor(string $provider): string
    {
        return $provider === self::NICEPAY ? 'Indodana via Nicepay (otomatis)' : 'Midtrans (otomatis)';
    }

    public static function available(Order $order, ?User $user): array
    {
        return self::METHODS;
    }
}
```

- [ ] **Step 4: Sederhanakan `MidtransPaymentAttemptService`**

Di `app/Services/Payments/Midtrans/MidtransPaymentAttemptService.php`:

1. Ganti konstruktor menjadi:

```php
    public function __construct(
        private readonly MidtransClient $client,
        private readonly MidtransWebhookService $webhook,
    ) {}
```

2. Hapus method `createOrReuseActiveAttempt()` dan `syncActiveAttempt()`. Gantikan dengan dua method ini (letakkan setelah `supportedMethods()`):

```php
    public function start(Order $order, PaymentAttempt $attempt, string $paymentMethod): void
    {
        $payload = $this->buildSnapPayload($order, $attempt, $paymentMethod);
        $response = $this->client->createSnapTransaction($payload);

        $attempt->update([
            'status' => PaymentAttemptStatus::PENDING,
            'snap_token' => $response['token'] ?? null,
            'redirect_url' => $response['redirect_url'] ?? null,
            'snap_request_payload' => $payload,
            'snap_response_payload' => $response,
        ]);
    }

    public function sync(PaymentAttempt $attempt): void
    {
        $status = $this->client->status($attempt->midtrans_order_id);

        if (! isset($status['transaction_status'])) {
            return;
        }

        $this->webhook->syncFromStatus($attempt, $status);
    }
```

3. `METHOD_MAP`, `supportedMethods()`, `buildSnapPayload()`, dan `buildItemDetails()` tetap.
4. Di `buildItemDetails()`, hapus baris komentar `// Diskon di-clamp seperti OrderService: max(0, subtotal - diskon) + ongkir.` dan tambahkan isinya ke PHPDoc method itu sebagai kalimat terakhir: `Diskon di-clamp seperti OrderService: max(0, subtotal - diskon) + ongkir.`
5. Di blok `use`, hapus `App\Enums\OrderStatus`, `App\Exceptions\BusinessRuleException`, `App\Services\OrderActivityService`, dan `Illuminate\Support\Facades\DB`.

- [ ] **Step 5: Buat `PaymentAttemptService`**

Buat `app/Services/Payments/PaymentAttemptService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\OrderActivityService;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use Illuminate\Support\Facades\DB;

class PaymentAttemptService
{
    public function __construct(
        private readonly MidtransPaymentAttemptService $midtrans,
        private readonly PaymentSettlementService $settlement,
        private readonly OrderActivityService $activities,
    ) {}

    public function createOrReuseActiveAttempt(Order $order, string $paymentMethod): PaymentAttempt
    {
        $provider = PaymentMethods::providerFor($paymentMethod);

        if ($provider === null) {
            throw new BusinessRuleException('Metode pembayaran tidak didukung.');
        }

        return DB::transaction(function () use ($order, $paymentMethod, $provider): PaymentAttempt {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === OrderStatus::PAID) {
                throw new BusinessRuleException('Pesanan sudah lunas. Tidak bisa membuat pembayaran baru.');
            }

            if ($lockedOrder->status === OrderStatus::CANCELLED) {
                throw new BusinessRuleException('Pesanan sudah dibatalkan.');
            }

            $activeAttempt = $lockedOrder->activePaymentAttempt;

            if ($activeAttempt?->isOpen() && $activeAttempt->payment_method === $paymentMethod) {
                return $activeAttempt;
            }

            if ($activeAttempt?->isOpen()) {
                $activeAttempt->update(['status' => PaymentAttemptStatus::SUPERSEDED]);
                $this->settlement->cancelAtGateway($activeAttempt);
            }

            $nextSequence = (int) PaymentAttempt::query()
                ->where('order_id', $lockedOrder->id)
                ->max('attempt_sequence') + 1;

            $attempt = PaymentAttempt::query()->create([
                'order_id' => $lockedOrder->id,
                'provider' => $provider,
                'attempt_sequence' => $nextSequence,
                'midtrans_order_id' => $lockedOrder->order_number.'-A'.$nextSequence,
                'payment_method' => $paymentMethod,
                'status' => PaymentAttemptStatus::CREATING,
                'gross_amount' => $lockedOrder->grand_total,
                'activated_at' => now(),
                'expired_at' => now()->addMinutes($this->expiryMinutes($provider)),
            ]);

            $this->start($provider, $lockedOrder, $attempt, $paymentMethod);

            $lockedOrder->update([
                'active_payment_attempt_id' => $attempt->id,
            ]);

            $this->activities->paymentStarted($lockedOrder, $paymentMethod);

            return $attempt->refresh();
        });
    }

    public function syncActiveAttempt(Order $order): void
    {
        $order->loadMissing('activePaymentAttempt');
        $attempt = $order->activePaymentAttempt;

        if ($attempt instanceof PaymentAttempt) {
            $this->syncAttempt($attempt);
        }
    }

    public function syncAttempt(PaymentAttempt $attempt): void
    {
        if (! $attempt->isOpen()) {
            return;
        }

        match ($attempt->provider) {
            PaymentMethods::MIDTRANS => $this->midtrans->sync($attempt),
        };
    }

    private function start(string $provider, Order $order, PaymentAttempt $attempt, string $paymentMethod): void
    {
        match ($provider) {
            PaymentMethods::MIDTRANS => $this->midtrans->start($order, $attempt, $paymentMethod),
        };
    }

    private function expiryMinutes(string $provider): int
    {
        return order_expiry_minutes();
    }
}
```

- [ ] **Step 6: Label metode dan aktor di `OrderActivityService`**

Di `app/Services/OrderActivityService.php`:

1. Hapus konstanta `METHOD_LABELS`.
2. Tambahkan `use App\Services\Payments\PaymentMethods;`.
3. Di `paymentStarted()`, ganti baris `$label = ...` menjadi `$label = PaymentMethods::name($method);`.
4. Ganti method `paymentFailed()` menjadi:

```php
    public function paymentFailed(Order $order, string $status, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::PAYMENT_FAILED,
            $actor ?? PaymentMethods::actorFor(PaymentMethods::MIDTRANS),
            'Pembayaran gagal/kedaluwarsa ('.$status.').',
            ['transaction_status' => $status],
        );
    }
```

Di `app/Services/Payments/PaymentSettlementService.php`, method `failed()`, ganti pemanggilan aktivitas menjadi:

```php
        if ($order->status !== OrderStatus::PAID) {
            $this->activities->paymentFailed($order, $reason, PaymentMethods::actorFor($attempt->provider));
        }
```

- [ ] **Step 7: Halaman invoice memakai kelas netral**

Di `resources/views/pages/storefront/⚡order-success.blade.php`, ubah blok PHP di atas saja. Bagian HTML tidak disentuh.

1. Di blok `use`, ganti `use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;` dengan:

```php
use App\Services\Payments\PaymentAttemptService;
use App\Services\Payments\PaymentMethods;
```

2. Ganti method `mount()` menjadi:

```php
    public function mount(Order $order): void
    {
        $this->order = $order->load('items.product', 'items.variant', 'voucher', 'activePaymentAttempt');
        $this->payment_method = $this->order->activePaymentAttempt?->payment_method
            ?? array_key_first($this->paymentMethods());
    }
```

3. Ganti method `methods()`, `paymentMethods()`, `pay()`, dan `continuePayment()` menjadi:

```php
    public function methods(): array
    {
        return array_map(fn (array $method): string => $method['name'], $this->paymentMethods());
    }

    public function paymentMethods(): array
    {
        return PaymentMethods::available($this->order, auth()->user());
    }

    public function pay(PaymentAttemptService $service): void
    {
        $this->validate([
            'payment_method' => ['required', Rule::in(array_keys($this->paymentMethods()))],
        ]);

        $this->startPayment($service, $this->payment_method);
        $this->changingMethod = false;
    }

    public function continuePayment(PaymentAttemptService $service): void
    {
        $attempt = $this->attempt();

        if (! $attempt) {
            return;
        }

        $this->startPayment($service, $attempt->payment_method);
    }
```

4. Ganti method `refreshStatus()` dan `dispatchSnap()` menjadi:

```php
    public function refreshStatus(PaymentAttemptService $service): void
    {
        $service->syncActiveAttempt($this->order);
        $this->order->refresh();

        if ($this->order->status === OrderStatus::PAID) {
            $this->js('window.location.reload()');
        }
    }

    protected function startPayment(PaymentAttemptService $service, string $method): void
    {
        try {
            $attempt = Cache::lock('order-pay:'.$this->order->id, 10)
                ->block(5, fn () => $service->createOrReuseActiveAttempt($this->order, $method));
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->order->refresh();

        if (blank($attempt->snap_token)) {
            Flux::toast(variant: 'danger', text: 'Gagal memuat halaman pembayaran. Coba lagi.');

            return;
        }

        $this->dispatch('snap-pay', token: $attempt->snap_token);
    }
```

`attempt()`, `startChangeMethod()`, dan `cancelChangeMethod()` tidak diubah. Komentar JavaScript `// QRIS (GoPay Dynamic): paksa QR, jangan deeplink aplikasi.` di bagian HTML menjelaskan logika yang tidak jelas dan tetap ada.

- [ ] **Step 8: Jalankan tes**

Run: `php artisan test --filter="PaymentMethodsTest|MidtransPaymentAttemptServiceTest"`
Expected: PASS.

Run: `php artisan test`
Expected: PASS seluruh suite. `MidtransInvoicePageTest` dan `OrderActivityTest` lolos tanpa diubah.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/Payments/PaymentMethods.php app/Services/Payments/PaymentAttemptService.php app/Services/Payments/Midtrans/MidtransPaymentAttemptService.php app/Services/Payments/PaymentSettlementService.php app/Services/OrderActivityService.php "resources/views/pages/storefront/⚡order-success.blade.php" tests/Feature/Payments/MidtransPaymentAttemptServiceTest.php tests/Feature/Payments/PaymentMethodsTest.php
git commit -m "refactor(payments): siklus attempt netral lewat PaymentAttemptService dan PaymentMethods"
```

---

### Task 3: Rekonsiliasi lewat `payments:reconcile`

**Files:**
- Rename: `app/Console/Commands/ReconcileMidtransPayments.php` menjadi `app/Console/Commands/ReconcilePayments.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Payments/ReconcilePaymentsTest.php`

**Interfaces:**
- Consumes: `PaymentAttemptService::syncAttempt(PaymentAttempt $attempt): void` dari Task 2.
- Produces: perintah artisan `payments:reconcile {--minutes=10}`.

- [ ] **Step 1: Tulis tes yang gagal**

Buat `tests/Feature/Payments/ReconcilePaymentsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconcilePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.is_production' => false,
        ]);

        Http::preventStrayRequests();
        Queue::fake();
    }

    private function pendingAttempt(Order $order, int $minutesAgo): PaymentAttempt
    {
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number.'-A1',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
            'activated_at' => now()->subMinutes($minutesAgo),
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        return $attempt;
    }

    public function test_attempt_pending_lama_dilunasi_dari_status_midtrans(): void
    {
        $order = Order::factory()->create(['grand_total' => 175000]);
        $attempt = $this->pendingAttempt($order, 30);

        Http::fake([
            '*/v2/*/status' => Http::response([
                'order_id' => $attempt->midtrans_order_id,
                'transaction_id' => 'trx-1',
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'gross_amount' => '175000.00',
                'fraud_status' => 'accept',
            ], 200),
            '*/v2/*/cancel' => Http::response(['status_code' => '200'], 200),
        ]);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::PAID, $attempt->fresh()->status);
    }

    public function test_attempt_yang_masih_baru_dilewati(): void
    {
        $order = Order::factory()->create();
        $this->pendingAttempt($order, 2);

        $this->artisan('payments:reconcile')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter=ReconcilePaymentsTest`
Expected: FAIL, perintah `payments:reconcile` tidak ditemukan.

- [ ] **Step 3: Ganti nama dan isi perintah**

```bash
git mv app/Console/Commands/ReconcileMidtransPayments.php app/Console/Commands/ReconcilePayments.php
```

Ganti seluruh isi `app/Console/Commands/ReconcilePayments.php` menjadi:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentAttemptStatus;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentAttemptService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes=10 : Usia minimal attempt pending (menit)}';

    protected $description = 'Sinkronkan status payment attempt pending dengan penyedia pembayarannya.';

    public function handle(PaymentAttemptService $service): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));

        $attempts = PaymentAttempt::query()
            ->where('status', PaymentAttemptStatus::PENDING->value)
            ->where('activated_at', '<=', $threshold)
            ->get();

        if ($attempts->isEmpty()) {
            $this->info('Tidak ada attempt pending untuk direkonsiliasi.');

            return self::SUCCESS;
        }

        foreach ($attempts as $attempt) {
            rescue(fn () => $service->syncAttempt($attempt));
        }

        $this->info("Rekonsiliasi selesai: {$attempts->count()} attempt diperiksa.");

        return self::SUCCESS;
    }
}
```

Di `routes/console.php`, ganti baris jadwal menjadi:

```php
Schedule::command('payments:reconcile')->everyFifteenMinutes();
```

Cron di server menjalankan `schedule:run`, jadi tidak ada perubahan cron. Nama lama `payments:midtrans:reconcile` tidak ada lagi.

- [ ] **Step 4: Jalankan tes**

Run: `php artisan test --filter=ReconcilePaymentsTest`
Expected: PASS, 2 tes.

Run: `php artisan test`
Expected: PASS seluruh suite.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Console/Commands routes/console.php tests/Feature/Payments/ReconcilePaymentsTest.php
git commit -m "refactor(payments): rekonsiliasi netral lewat payments:reconcile"
```

---

### Task 4: Konfigurasi dan `NicepayClient`

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`
- Create: `app/Services/Payments/Nicepay/NicepayClient.php`
- Modify: `app/Services/Payments/PaymentSettlementService.php` (konstruktor dan `cancelAtGateway()`)
- Modify: `tests/Feature/Payments/PaymentSettlementServiceTest.php` (tambah satu tes)
- Test: `tests/Feature/Payments/NicepayClientTest.php`

**Interfaces:**
- Consumes: `PaymentMethods::NICEPAY` dari Task 2; kolom `midtrans_order_id` (berisi `referenceNo`), `midtrans_transaction_id` (berisi `tXid`), dan `gross_amount` pada `PaymentAttempt`.
- Produces:
  - Konfigurasi `services.nicepay.*`: `enabled`, `admin_only`, `is_production`, `imid`, `merchant_key`, `expiry_minutes`, `store_city`, `store_state`, `store_postcode`, `development_url`, `production_url`
  - `NicepayClient::baseUrl(): string`
  - `NicepayClient::merchantId(): string`
  - `NicepayClient::timestamp(): string`
  - `NicepayClient::token(string ...$parts): string`
  - `NicepayClient::transactionToken(string $timeStamp, string $referenceNo, int $amount): string`
  - `NicepayClient::notificationTokenIsValid(array $payload): bool`
  - `NicepayClient::register(array $payload): array` (melempar `BusinessRuleException` bila gagal)
  - `NicepayClient::inquiry(PaymentAttempt $attempt): array` (mengembalikan `[]` bila HTTP gagal)
  - `NicepayClient::cancel(PaymentAttempt $attempt): array`

- [ ] **Step 1: Tulis tes yang gagal**

Buat `tests/Feature/Payments/NicepayClientTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Nicepay\NicepayClient;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NicepayClientTest extends TestCase
{
    use RefreshDatabase;

    private NicepayClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);

        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-10-02 10:15:00', 'Asia/Jakarta'));

        $this->client = app(NicepayClient::class);
    }

    private function attempt(array $overrides = []): PaymentAttempt
    {
        $order = Order::factory()->create(['grand_total' => 150000]);

        return PaymentAttempt::factory()->create(array_merge([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => 'JW-TEST-A1',
            'midtrans_transaction_id' => 'TESTIMID0106202610021015001234',
            'payment_method' => 'indodana',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => 150000,
        ], $overrides));
    }

    public function test_base_url_mengikuti_mode(): void
    {
        $this->assertSame('https://dev.nicepay.co.id', $this->client->baseUrl());

        config(['services.nicepay.is_production' => true]);

        $this->assertSame('https://www.nicepay.co.id', $this->client->baseUrl());
    }

    public function test_timestamp_berzona_jakarta(): void
    {
        $this->assertSame('20261002101500', $this->client->timestamp());
    }

    public function test_token_transaksi_memakai_rumus_registration(): void
    {
        $this->assertSame(
            hash('sha256', '20261002101500'.'TESTIMID01'.'JW-TEST-A1'.'150000'.'test-merchant-key'),
            $this->client->transactionToken('20261002101500', 'JW-TEST-A1', 150000),
        );
    }

    public function test_token_notifikasi_valid_dan_tidak_valid(): void
    {
        $valid = [
            'tXid' => 'TX123',
            'amt' => '150000',
            'merchantToken' => hash('sha256', 'TESTIMID01'.'TX123'.'150000'.'test-merchant-key'),
        ];

        $this->assertTrue($this->client->notificationTokenIsValid($valid));
        $this->assertFalse($this->client->notificationTokenIsValid(array_merge($valid, ['amt' => '1'])));
        $this->assertFalse($this->client->notificationTokenIsValid(array_merge($valid, ['merchantToken' => 'palsu'])));
        $this->assertFalse($this->client->notificationTokenIsValid(['tXid' => 'TX123']));
    }

    public function test_register_mengirim_json_dan_mengembalikan_respons(): void
    {
        Http::fake([
            '*/nicepay/direct/v2/registration' => Http::response(['resultCd' => '0000', 'resultMsg' => 'SUCCESS', 'tXid' => 'TX999'], 200),
        ]);

        $response = $this->client->register(['referenceNo' => 'JW-TEST-A1', 'amt' => '150000']);

        $this->assertSame('TX999', $response['tXid']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://dev.nicepay.co.id/nicepay/direct/v2/registration'
            && $request->isJson()
            && $request['referenceNo'] === 'JW-TEST-A1');
    }

    public function test_register_gagal_melempar_exception(): void
    {
        Http::fake([
            '*/nicepay/direct/v2/registration' => Http::sequence()
                ->push(['resultCd' => '9999', 'resultMsg' => 'Invalid merchant token'], 200)
                ->push(['error' => 'server'], 500)
                ->push(['resultCd' => '0000', 'resultMsg' => 'SUCCESS'], 200),
        ]);

        foreach (range(1, 3) as $percobaan) {
            try {
                $this->client->register(['referenceNo' => 'JW-TEST-A1']);
                $this->fail("Percobaan {$percobaan} seharusnya melempar BusinessRuleException.");
            } catch (BusinessRuleException $e) {
                $this->assertSame('Gagal memulai pembayaran. Silakan coba lagi.', $e->getMessage());
            }
        }
    }

    public function test_inquiry_mengirim_field_dan_token_yang_benar(): void
    {
        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::response(['resultCd' => '0000', 'status' => '3'], 200)]);

        $response = $this->client->inquiry($this->attempt());

        $this->assertSame('3', $response['status']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://dev.nicepay.co.id/nicepay/direct/v2/inquiry'
            && $request['timeStamp'] === '20261002101500'
            && $request['tXid'] === 'TESTIMID0106202610021015001234'
            && $request['iMid'] === 'TESTIMID01'
            && $request['referenceNo'] === 'JW-TEST-A1'
            && $request['amt'] === '150000'
            && $request['merchantToken'] === hash('sha256', '20261002101500'.'TESTIMID01'.'JW-TEST-A1'.'150000'.'test-merchant-key'));
    }

    public function test_inquiry_mengembalikan_array_kosong_saat_http_gagal(): void
    {
        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::response(['error' => 'server'], 500)]);

        $this->assertSame([], $this->client->inquiry($this->attempt()));
    }

    public function test_inquiry_mengembalikan_array_kosong_saat_koneksi_gagal(): void
    {
        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::failedConnection()]);

        $this->assertSame([], $this->client->inquiry($this->attempt()));
    }

    public function test_cancel_memakai_rumus_token_cancel(): void
    {
        Http::fake(['*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '0000'], 200)]);

        $this->client->cancel($this->attempt());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://dev.nicepay.co.id/nicepay/direct/v2/cancel'
            && $request['payMethod'] === '06'
            && $request['cancelType'] === '1'
            && $request['amt'] === '150000'
            && $request['merchantToken'] === hash('sha256', '20261002101500'.'TESTIMID01'.'TESTIMID0106202610021015001234'.'150000'.'test-merchant-key'));
    }

    public function test_cancel_tanpa_txid_tidak_memanggil_api(): void
    {
        $this->assertSame([], $this->client->cancel($this->attempt(['midtrans_transaction_id' => null])));

        Http::assertNothingSent();
    }
}
```

Tambahkan tes ini ke `tests/Feature/Payments/PaymentSettlementServiceTest.php` (dan `use App\Services\Payments\PaymentMethods;` di blok `use`):

```php
    public function test_cancel_attempt_nicepay_dikirim_ke_nicepay(): void
    {
        config([
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);
        Http::fake(['*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '0000'], 200)]);

        $order = Order::factory()->create();
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $order->order_number.'-A1',
            'midtrans_transaction_id' => 'TX123',
            'payment_method' => 'indodana',
            'gross_amount' => $order->grand_total,
        ]);

        $this->service->cancelAtGateway($attempt);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/nicepay/direct/v2/cancel') && $request['tXid'] === 'TX123');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'midtrans.com'));
    }
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter="NicepayClientTest|PaymentSettlementServiceTest"`
Expected: FAIL, `Class "App\Services\Payments\Nicepay\NicepayClient" not found`.

- [ ] **Step 3: Tambah konfigurasi**

Di `config/services.php`, tambahkan blok ini setelah blok `'midtrans'`:

```php
    'nicepay' => [
        'enabled' => env('NICEPAY_ENABLED', false),
        'admin_only' => env('NICEPAY_ADMIN_ONLY', true),
        'is_production' => env('NICEPAY_IS_PRODUCTION', false),
        'imid' => env('NICEPAY_IMID'),
        'merchant_key' => env('NICEPAY_MERCHANT_KEY'),
        'expiry_minutes' => (int) env('NICEPAY_EXPIRY_MINUTES', 1440),
        'store_city' => env('NICEPAY_STORE_CITY'),
        'store_state' => env('NICEPAY_STORE_STATE'),
        'store_postcode' => env('NICEPAY_STORE_POSTCODE'),
        'development_url' => env('NICEPAY_DEVELOPMENT_URL', 'https://dev.nicepay.co.id'),
        'production_url' => env('NICEPAY_PRODUCTION_URL', 'https://www.nicepay.co.id'),
    ],
```

Di `.env.example`, tambahkan blok ini setelah blok Midtrans:

```dotenv

# === Payment Gateway (Nicepay - Indodana PayLater) ===
NICEPAY_ENABLED=false
NICEPAY_ADMIN_ONLY=true
NICEPAY_IS_PRODUCTION=false
NICEPAY_IMID=
NICEPAY_MERCHANT_KEY=
NICEPAY_EXPIRY_MINUTES=1440
NICEPAY_STORE_CITY="Jakarta Utara"
NICEPAY_STORE_STATE="DKI Jakarta"
NICEPAY_STORE_POSTCODE=14440
```

Nilai kota, provinsi, dan kode pos di atas adalah contoh yang diturunkan dari `site_address` di `SettingSeeder`; pemilik toko memastikan kode posnya sebelum produksi.

- [ ] **Step 4: Buat `NicepayClient`**

Buat `app/Services/Payments/Nicepay/NicepayClient.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Payments\Nicepay;

use App\Exceptions\BusinessRuleException;
use App\Models\PaymentAttempt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NicepayClient
{
    public const PAY_METHOD_PAYLATER = '06';

    public function baseUrl(): string
    {
        return rtrim((string) (config('services.nicepay.is_production')
            ? config('services.nicepay.production_url')
            : config('services.nicepay.development_url')), '/');
    }

    public function merchantId(): string
    {
        return (string) config('services.nicepay.imid');
    }

    public function timestamp(): string
    {
        return now('Asia/Jakarta')->format('YmdHis');
    }

    public function token(string ...$parts): string
    {
        return hash('sha256', implode('', $parts).config('services.nicepay.merchant_key'));
    }

    public function transactionToken(string $timeStamp, string $referenceNo, int $amount): string
    {
        return $this->token($timeStamp, $this->merchantId(), $referenceNo, (string) $amount);
    }

    public function notificationTokenIsValid(array $payload): bool
    {
        foreach (['tXid', 'amt', 'merchantToken'] as $key) {
            if (blank($payload[$key] ?? null)) {
                return false;
            }
        }

        $expected = $this->token($this->merchantId(), (string) $payload['tXid'], (string) $payload['amt']);

        return hash_equals($expected, (string) $payload['merchantToken']);
    }

    public function register(array $payload): array
    {
        $response = $this->post('/nicepay/direct/v2/registration', $payload);

        if (($response['resultCd'] ?? null) !== '0000' || blank($response['tXid'] ?? null)) {
            Log::error('Nicepay registration gagal', [
                'referenceNo' => $payload['referenceNo'] ?? null,
                'resultCd' => $response['resultCd'] ?? null,
                'resultMsg' => $response['resultMsg'] ?? null,
            ]);

            throw new BusinessRuleException('Gagal memulai pembayaran. Silakan coba lagi.');
        }

        return $response;
    }

    public function inquiry(PaymentAttempt $attempt): array
    {
        $timeStamp = $this->timestamp();
        $amount = (int) $attempt->gross_amount;

        return $this->post('/nicepay/direct/v2/inquiry', [
            'timeStamp' => $timeStamp,
            'tXid' => (string) $attempt->midtrans_transaction_id,
            'iMid' => $this->merchantId(),
            'referenceNo' => $attempt->midtrans_order_id,
            'amt' => (string) $amount,
            'merchantToken' => $this->transactionToken($timeStamp, $attempt->midtrans_order_id, $amount),
        ]);
    }

    public function cancel(PaymentAttempt $attempt): array
    {
        if (blank($attempt->midtrans_transaction_id)) {
            return [];
        }

        $timeStamp = $this->timestamp();
        $tXid = (string) $attempt->midtrans_transaction_id;
        $amount = (string) (int) $attempt->gross_amount;

        return $this->post('/nicepay/direct/v2/cancel', [
            'timeStamp' => $timeStamp,
            'tXid' => $tXid,
            'iMid' => $this->merchantId(),
            'payMethod' => self::PAY_METHOD_PAYLATER,
            'cancelType' => '1',
            'cancelMsg' => 'Metode pembayaran diganti',
            'amt' => $amount,
            'merchantToken' => $this->token($timeStamp, $this->merchantId(), $tXid, $amount),
        ]);
    }

    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::timeout(15)->acceptJson()->asJson()->post($this->baseUrl().$path, $payload);
        } catch (ConnectionException $e) {
            Log::warning('Nicepay tidak dapat dihubungi', ['path' => $path, 'error' => $e->getMessage()]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('Nicepay membalas error HTTP', ['path' => $path, 'status' => $response->status()]);

            return [];
        }

        return $response->json() ?? [];
    }
}
```

- [ ] **Step 5: Pembatalan memilih penyedia**

Di `app/Services/Payments/PaymentSettlementService.php`:

1. Tambahkan `use App\Services\Payments\Nicepay\NicepayClient;`.
2. Tambahkan parameter terakhir konstruktor: `private readonly NicepayClient $nicepay,`.
3. Ganti method `cancelAtGateway()` menjadi:

```php
    public function cancelAtGateway(PaymentAttempt $attempt): void
    {
        rescue(fn () => match ($attempt->provider) {
            PaymentMethods::NICEPAY => $this->nicepay->cancel($attempt),
            default => $this->midtrans->cancel($attempt->midtrans_order_id),
        }, report: false);
    }
```

- [ ] **Step 6: Jalankan tes**

Run: `php artisan test --filter="NicepayClientTest|PaymentSettlementServiceTest"`
Expected: PASS.

Run: `php artisan test tests/Feature/Payments`
Expected: PASS semua.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add config/services.php .env.example app/Services/Payments/Nicepay/NicepayClient.php app/Services/Payments/PaymentSettlementService.php tests/Feature/Payments/NicepayClientTest.php tests/Feature/Payments/PaymentSettlementServiceTest.php
git commit -m "feat(payments): NicepayClient dan konfigurasi Nicepay"
```

---

### Task 5: Sinkronisasi status dan notifikasi Nicepay

**Files:**
- Create: `app/Services/Payments/Nicepay/NicepayPaylaterService.php`
- Create: `app/Http/Controllers/Payment/NicepayNotificationController.php`
- Modify: `app/Services/Payments/PaymentAttemptService.php` (konstruktor dan `syncAttempt()`)
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Modify: `tests/Feature/Payments/ReconcilePaymentsTest.php` (tambah satu tes)
- Test: `tests/Feature/Payments/NicepayStatusSyncTest.php`
- Test: `tests/Feature/Payments/NicepayNotificationControllerTest.php`

**Interfaces:**
- Consumes: `NicepayClient::inquiry()`, `NicepayClient::notificationTokenIsValid()` dari Task 4; `PaymentSettlementService::paid()`, `pending()`, `failed()` dari Task 1; `PaymentMethods::NICEPAY`, `PaymentMethods::actorFor()` dari Task 2; `App\Exceptions\InvalidWebhookSignatureException` yang sudah ada.
- Produces:
  - `NicepayPaylaterService::sync(PaymentAttempt $attempt): void`
  - `NicepayPaylaterService::handleNotification(array $payload): void` (melempar `InvalidWebhookSignatureException`)
  - Rute `POST /payments/nicepay/notification` bernama `payments.nicepay.notification`

- [ ] **Step 1: Tulis tes sinkronisasi yang gagal**

Buat `tests/Feature/Payments/NicepayStatusSyncTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Nicepay\NicepayPaylaterService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NicepayStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    private NicepayPaylaterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.is_production' => false,
            'services.nicepay.enabled' => true,
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);

        Http::preventStrayRequests();
        Queue::fake();

        $this->service = app(NicepayPaylaterService::class);
    }

    private function nicepayAttempt(Order $order, array $overrides = []): PaymentAttempt
    {
        return PaymentAttempt::factory()->create(array_merge([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'attempt_sequence' => 1,
            'midtrans_order_id' => $order->order_number.'-A1',
            'midtrans_transaction_id' => 'TESTIMID0106202610021015001234',
            'payment_method' => 'indodana',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
            'snap_token' => null,
            'redirect_url' => null,
            'expired_at' => now()->addDay(),
        ], $overrides));
    }

    private function fakeInquiry(string $status, ?string $amt = null, string $resultCd = '0000'): void
    {
        Http::fake([
            '*/nicepay/direct/v2/inquiry' => fn (Request $request) => Http::response([
                'resultCd' => $resultCd,
                'resultMsg' => 'SUCCESS',
                'tXid' => $request['tXid'],
                'referenceNo' => $request['referenceNo'],
                'amt' => $amt ?? $request['amt'],
                'status' => $status,
                'payMethod' => '06',
                'mitraCd' => 'IDNA',
            ], 200),
            '*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '0000'], 200),
            '*/v2/*/cancel' => Http::response(['status_code' => '200'], 200),
        ]);
    }

    public function test_status_nol_melunasi_order(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->nicepayAttempt($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);
        $this->fakeInquiry('0');

        $this->service->sync($attempt);

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::PAID, $attempt->fresh()->status);
        $this->assertSame('0', $attempt->fresh()->midtrans_transaction_status);
        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id,
            'actor' => 'Indodana via Nicepay (otomatis)',
        ]);
    }

    public function test_status_nol_dengan_nominal_berbeda_tidak_melunasi(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->nicepayAttempt($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);
        $this->fakeInquiry('0', '100000');

        $this->service->sync($attempt);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->fresh()->status);
    }

    public function test_status_belum_bayar_tetap_pending(): void
    {
        foreach (['3', '9'] as $status) {
            $order = Order::factory()->create();
            $attempt = $this->nicepayAttempt($order);
            $order->update(['active_payment_attempt_id' => $attempt->id]);
            $this->fakeInquiry($status);

            $this->service->sync($attempt);

            $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->fresh()->status, "status {$status}");
            $this->assertSame(OrderStatus::PENDING, $order->fresh()->status, "status {$status}");
        }
    }

    public function test_belum_bayar_setelah_masa_berlaku_ditandai_expired(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->nicepayAttempt($order, ['expired_at' => now()->subMinute()]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);
        $this->fakeInquiry('3');

        $this->service->sync($attempt);

        $this->assertSame(PaymentAttemptStatus::EXPIRED, $attempt->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_attempt_expired_tidak_dibuka_lagi_oleh_status_belum_bayar(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->nicepayAttempt($order, [
            'status' => PaymentAttemptStatus::EXPIRED,
            'expired_at' => now()->subHour(),
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);
        $this->fakeInquiry('3');

        $this->service->sync($attempt);

        $this->assertSame(PaymentAttemptStatus::EXPIRED, $attempt->fresh()->status);
    }

    public function test_status_delapan_menandai_gagal(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->nicepayAttempt($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);
        $this->fakeInquiry('8');

        $this->service->sync($attempt);

        $this->assertSame(PaymentAttemptStatus::FAILED, $attempt->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_void_atau_refund_tidak_mengubah_order(): void
    {
        foreach (['1', '2'] as $status) {
            $order = Order::factory()->paid()->create();
            $attempt = $this->nicepayAttempt($order, ['status' => PaymentAttemptStatus::PAID]);
            $order->update(['active_payment_attempt_id' => $attempt->id]);
            $this->fakeInquiry($status);

            $this->service->sync($attempt);

            $this->assertSame(PaymentAttemptStatus::CANCELLED, $attempt->fresh()->status, "status {$status}");
            $this->assertSame(OrderStatus::PAID, $order->fresh()->status, "status {$status}");
        }
    }

    public function test_inquiry_gagal_tidak_mengubah_apa_pun(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->nicepayAttempt($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->fakeInquiry('0', null, '9999');
        $this->service->sync($attempt);

        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::response(['error' => 'server'], 500)]);
        $this->service->sync($attempt);

        $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertNull($attempt->fresh()->midtrans_transaction_status);
    }

    public function test_pembayaran_terlambat_dari_attempt_superseded_tetap_melunasi(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $late = $this->nicepayAttempt($order, ['status' => PaymentAttemptStatus::SUPERSEDED]);
        $open = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'attempt_sequence' => 2,
            'midtrans_order_id' => $order->order_number.'-A2',
            'payment_method' => 'bni_va',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
        ]);
        $order->update(['active_payment_attempt_id' => $open->id]);
        $this->fakeInquiry('0');

        $this->service->sync($late);

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame($late->id, $order->fresh()->active_payment_attempt_id);
        $this->assertSame(PaymentAttemptStatus::PAID, $late->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $open->fresh()->status);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'midtrans.com/v2/'.$open->midtrans_order_id.'/cancel'));
    }
}
```

- [ ] **Step 2: Tulis tes notifikasi yang gagal**

Buat `tests/Feature/Payments/NicepayNotificationControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NicepayNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nicepay.enabled' => true,
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);

        Http::preventStrayRequests();
        Queue::fake();
    }

    private function attemptFor(Order $order): PaymentAttempt
    {
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $order->order_number.'-A1',
            'midtrans_transaction_id' => 'TESTIMID0106202610021015001234',
            'payment_method' => 'indodana',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
            'expired_at' => now()->addDay(),
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        return $attempt;
    }

    private function notification(array $overrides = []): array
    {
        $payload = array_merge([
            'tXid' => 'TESTIMID0106202610021015001234',
            'referenceNo' => 'TIDAK-ADA-A1',
            'amt' => '150000',
            'payMethod' => '06',
            'mitraCd' => 'IDNA',
            'status' => '0',
            'currency' => 'IDR',
        ], $overrides);

        if (! isset($payload['merchantToken'])) {
            $payload['merchantToken'] = hash('sha256', 'TESTIMID01'.$payload['tXid'].$payload['amt'].'test-merchant-key');
        }

        return $payload;
    }

    private function fakeInquiry(string $status, ?string $amt = null): void
    {
        Http::fake([
            '*/nicepay/direct/v2/inquiry' => fn (Request $request) => Http::response([
                'resultCd' => '0000',
                'resultMsg' => 'SUCCESS',
                'tXid' => $request['tXid'],
                'referenceNo' => $request['referenceNo'],
                'amt' => $amt ?? $request['amt'],
                'status' => $status,
            ], 200),
        ]);
    }

    public function test_notifikasi_valid_melunasi_order_setelah_inquiry(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->attemptFor($order);
        $this->fakeInquiry('0');

        $response = $this->post(
            route('payments.nicepay.notification'),
            $this->notification(['referenceNo' => $attempt->midtrans_order_id]),
        );

        $response->assertOk();
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $attempt->midtrans_order_id,
            'transaction_id' => 'TESTIMID0106202610021015001234',
            'processing_status' => 'processed',
        ]);
    }

    public function test_token_tidak_valid_ditolak_403(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->attemptFor($order);

        $response = $this->post(
            route('payments.nicepay.notification'),
            $this->notification(['referenceNo' => $attempt->midtrans_order_id, 'merchantToken' => 'palsu']),
        );

        $response->assertForbidden();
        Http::assertNothingSent();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $attempt->midtrans_order_id,
            'processing_status' => 'invalid_signature',
        ]);
    }

    public function test_referensi_tidak_dikenal_diabaikan(): void
    {
        $response = $this->post(route('payments.nicepay.notification'), $this->notification());

        $response->assertOk();
        Http::assertNothingSent();
        $this->assertDatabaseHas('payment_webhook_events', [
            'midtrans_order_id' => 'TIDAK-ADA-A1',
            'processing_status' => 'ignored',
        ]);
    }

    public function test_notifikasi_ganda_hanya_diproses_sekali(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->attemptFor($order);
        $this->fakeInquiry('0');
        $payload = $this->notification(['referenceNo' => $attempt->midtrans_order_id]);

        $this->post(route('payments.nicepay.notification'), $payload)->assertOk();
        $this->post(route('payments.nicepay.notification'), $payload)->assertOk();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_nominal_inquiry_tidak_cocok_tidak_melunasi(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->attemptFor($order);
        $this->fakeInquiry('0', '100000');

        $this->post(
            route('payments.nicepay.notification'),
            $this->notification(['referenceNo' => $attempt->midtrans_order_id]),
        )->assertOk();

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'midtrans_order_id' => $attempt->midtrans_order_id,
            'processing_status' => 'ignored',
        ]);
    }
}
```

Tambahkan tes ini ke `tests/Feature/Payments/ReconcilePaymentsTest.php` (dan `use App\Services\Payments\PaymentMethods;` di blok `use`):

```php
    public function test_attempt_nicepay_pending_lama_dilunasi_dari_inquiry(): void
    {
        config([
            'services.nicepay.enabled' => true,
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);

        $order = Order::factory()->create(['grand_total' => 175000]);
        $attempt = $this->pendingAttempt($order, 30);
        $attempt->update([
            'provider' => PaymentMethods::NICEPAY,
            'payment_method' => 'indodana',
            'midtrans_transaction_id' => 'TESTIMID0106202610021015001234',
            'expired_at' => now()->addDay(),
        ]);

        Http::fake([
            '*/nicepay/direct/v2/inquiry' => Http::response([
                'resultCd' => '0000',
                'resultMsg' => 'SUCCESS',
                'amt' => '175000',
                'status' => '0',
            ], 200),
        ]);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::PAID, $attempt->fresh()->status);
    }
```

- [ ] **Step 3: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter="NicepayStatusSyncTest|NicepayNotificationControllerTest|ReconcilePaymentsTest"`
Expected: FAIL, `NicepayPaylaterService` tidak ditemukan dan rute `payments.nicepay.notification` belum ada.

- [ ] **Step 4: Buat `NicepayPaylaterService`**

Buat `app/Services/Payments/Nicepay/NicepayPaylaterService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Payments\Nicepay;

use App\Enums\PaymentAttemptStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaymentMethods;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NicepayPaylaterService
{
    private const TERMINAL_EVENT_STATUSES = ['processed', 'ignored', 'invalid_signature'];

    public function __construct(
        private readonly NicepayClient $client,
        private readonly PaymentSettlementService $settlement,
    ) {}

    public function sync(PaymentAttempt $attempt): void
    {
        $this->applyInquiry($attempt, null);
    }

    public function handleNotification(array $payload): void
    {
        if (! $this->client->notificationTokenIsValid($payload)) {
            $this->storeEvent($payload)->update([
                'processing_status' => 'invalid_signature',
                'notes' => 'Token Nicepay tidak valid.',
            ]);

            throw new InvalidWebhookSignatureException('Token Nicepay tidak valid.');
        }

        $event = $this->storeEvent($payload);

        if (in_array($event->processing_status, self::TERMINAL_EVENT_STATUSES, true)) {
            return;
        }

        $attempt = PaymentAttempt::query()
            ->where('provider', PaymentMethods::NICEPAY)
            ->where('midtrans_order_id', (string) ($payload['referenceNo'] ?? ''))
            ->first();

        if (! $attempt) {
            $event->update([
                'processing_status' => 'ignored',
                'notes' => 'Payment attempt tidak ditemukan.',
            ]);

            return;
        }

        $this->applyInquiry($attempt, $event);
    }

    /**
     * Status lunas hanya diambil dari Status Inquiry. Notifikasi dan callback bisa
     * dipalsukan atau terlambat, jadi keduanya hanya memicu inquiry ini. Bila
     * inquiry gagal, event dibiarkan berstatus received supaya notifikasi ulang
     * atau rekonsiliasi memprosesnya lagi.
     */
    private function applyInquiry(PaymentAttempt $attempt, ?PaymentWebhookEvent $event): void
    {
        $inquiry = $this->client->inquiry($attempt);

        if (($inquiry['resultCd'] ?? null) !== '0000' || ! isset($inquiry['status'])) {
            return;
        }

        DB::transaction(function () use ($attempt, $inquiry, $event): void {
            $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'midtrans_transaction_status' => (string) $inquiry['status'],
                'latest_notification_payload' => $inquiry,
            ]);

            $this->route($order, $locked, $inquiry, $event);
        });
    }

    private function route(Order $order, PaymentAttempt $attempt, array $inquiry, ?PaymentWebhookEvent $event): void
    {
        $status = (string) $inquiry['status'];

        if ($status === '0') {
            $this->settlement->paid(
                $order,
                $attempt,
                (int) ($inquiry['amt'] ?? 0),
                PaymentMethods::actorFor(PaymentMethods::NICEPAY),
                $event,
            );

            return;
        }

        if (in_array($status, ['3', '9'], true)) {
            if (! $attempt->isOpen()) {
                $event?->update([
                    'processing_status' => 'ignored',
                    'notes' => 'Belum dibayar; attempt sudah tidak terbuka.',
                ]);

                return;
            }

            if ($attempt->isExpired()) {
                $this->settlement->failed($order, $attempt, PaymentAttemptStatus::EXPIRED, 'expire', $event);

                return;
            }

            $this->settlement->pending($order, $attempt, $event);

            return;
        }

        if ($status === '8') {
            $this->settlement->failed($order, $attempt, PaymentAttemptStatus::FAILED, 'failure', $event);

            return;
        }

        if (in_array($status, ['1', '2'], true)) {
            Log::warning('Nicepay: transaksi di-void atau di-refund; perlu ditinjau admin.', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'status' => $status,
            ]);

            $attempt->update(['status' => PaymentAttemptStatus::CANCELLED]);

            $event?->update([
                'processing_status' => 'processed',
                'notes' => 'Transaksi void atau refund di Nicepay. Perlu ditinjau.',
            ]);

            return;
        }

        $event?->update([
            'processing_status' => 'ignored',
            'notes' => 'status tidak ditangani: '.$status,
        ]);
    }

    private function storeEvent(array $payload): PaymentWebhookEvent
    {
        $eventHash = hash('sha256', implode('|', [
            PaymentMethods::NICEPAY,
            $payload['referenceNo'] ?? '',
            $payload['tXid'] ?? '',
            $payload['status'] ?? '',
            $payload['amt'] ?? '',
            $payload['merchantToken'] ?? '',
        ]));

        return PaymentWebhookEvent::query()->firstOrCreate(
            ['event_hash' => $eventHash],
            [
                'provider' => PaymentMethods::NICEPAY,
                'midtrans_order_id' => (string) ($payload['referenceNo'] ?? ''),
                'transaction_id' => $payload['tXid'] ?? null,
                'transaction_status' => $payload['status'] ?? null,
                'gross_amount' => $payload['amt'] ?? null,
                'signature_key' => $payload['merchantToken'] ?? null,
                'payload' => $payload,
                'processing_status' => 'received',
            ]
        );
    }
}
```

- [ ] **Step 5: Controller, rute, dan pengecualian CSRF**

Buat `app/Http/Controllers/Payment/NicepayNotificationController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Services\Payments\Nicepay\NicepayPaylaterService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NicepayNotificationController extends Controller
{
    public function __invoke(Request $request, NicepayPaylaterService $service): Response
    {
        try {
            $service->handleNotification($request->all());
        } catch (InvalidWebhookSignatureException) {
            return response('Invalid token', 403);
        }

        return response('OK', 200);
    }
}
```

Di `routes/web.php`, tambahkan `use App\Http\Controllers\Payment\NicepayNotificationController;` dan rute ini setelah rute `payments.midtrans.finish`:

```php
Route::post('/payments/nicepay/notification', NicepayNotificationController::class)
    ->name('payments.nicepay.notification');
```

Di `bootstrap/app.php`, ganti blok `validateCsrfTokens` beserta komentarnya menjadi (komentarnya menjelaskan alasan konfigurasi, jadi tetap ada):

```php
        // Webhook dan callback pembayaran adalah POST dari luar tanpa token CSRF.
        // Dikecualikan di sini (cara Laravel 11+) agar tahan terhadap penggantian
        // nama kelas middleware CSRF (VerifyCsrfToken -> ValidateCsrfToken).
        $middleware->validateCsrfTokens(except: [
            'payments/midtrans/notification',
            'payments/nicepay/notification',
        ]);
```

- [ ] **Step 6: `PaymentAttemptService` menyinkronkan attempt Nicepay**

Di `app/Services/Payments/PaymentAttemptService.php`:

1. Tambahkan `use App\Services\Payments\Nicepay\NicepayPaylaterService;`.
2. Tambahkan parameter konstruktor setelah `$midtrans`: `private readonly NicepayPaylaterService $nicepay,`.
3. Ganti `match` di `syncAttempt()` menjadi:

```php
        match ($attempt->provider) {
            PaymentMethods::NICEPAY => $this->nicepay->sync($attempt),
            default => $this->midtrans->sync($attempt),
        };
```

- [ ] **Step 7: Jalankan tes**

Run: `php artisan test --filter="NicepayStatusSyncTest|NicepayNotificationControllerTest|ReconcilePaymentsTest"`
Expected: PASS.

Run: `php artisan test tests/Feature/Payments`
Expected: PASS semua.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/Payments/Nicepay/NicepayPaylaterService.php app/Http/Controllers/Payment/NicepayNotificationController.php app/Services/Payments/PaymentAttemptService.php routes/web.php bootstrap/app.php tests/Feature/Payments/NicepayStatusSyncTest.php tests/Feature/Payments/NicepayNotificationControllerTest.php tests/Feature/Payments/ReconcilePaymentsTest.php
git commit -m "feat(payments): sinkronisasi status dan notifikasi Nicepay"
```

---

### Task 6: Halaman redirect ke Nicepay dan callback

**Files:**
- Modify: `app/Services/Payments/Nicepay/NicepayClient.php` (tambah dua method)
- Create: `app/Http/Controllers/Payment/NicepayPaymentController.php`
- Create: `resources/views/payments/nicepay-redirect.blade.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/Payments/NicepayPaymentControllerTest.php`

**Interfaces:**
- Consumes: `NicepayClient::timestamp()`, `transactionToken()`, `baseUrl()` dari Task 4; `NicepayPaylaterService::sync()` dari Task 5; rute `checkout.success` dan `home` yang sudah ada; helper `rupiah(int $amount): string` yang sudah ada.
- Produces:
  - `NicepayClient::paymentUrl(): string`
  - `NicepayClient::paymentFormFields(PaymentAttempt $attempt): array` dengan kunci `timeStamp`, `tXid`, `merchantToken`, `callBackUrl`
  - Rute `GET /payments/nicepay/pay/{order:uuid}` bernama `payments.nicepay.pay`
  - Rute `GET|POST /payments/nicepay/callback` bernama `payments.nicepay.callback`

- [ ] **Step 1: Tulis tes yang gagal**

Buat `tests/Feature/Payments/NicepayPaymentControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NicepayPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nicepay.enabled' => true,
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);

        Http::preventStrayRequests();
        Queue::fake();
        $this->withoutVite();
    }

    private function nicepayAttempt(Order $order, array $overrides = []): PaymentAttempt
    {
        $attempt = PaymentAttempt::factory()->create(array_merge([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $order->order_number.'-A1',
            'midtrans_transaction_id' => 'TESTIMID0106202610021015001234',
            'payment_method' => 'indodana',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
            'snap_token' => null,
            'redirect_url' => null,
            'expired_at' => now()->addDay(),
        ], $overrides));
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        return $attempt;
    }

    private function fakeInquiry(string $status): void
    {
        Http::fake([
            '*/nicepay/direct/v2/inquiry' => fn (Request $request) => Http::response([
                'resultCd' => '0000',
                'resultMsg' => 'SUCCESS',
                'amt' => $request['amt'],
                'status' => $status,
            ], 200),
        ]);
    }

    public function test_halaman_bayar_memuat_form_ke_nicepay(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 10:15:00', 'Asia/Jakarta'));
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->nicepayAttempt($order);

        $response = $this->get(route('payments.nicepay.pay', ['order' => $order->uuid]));

        $token = hash('sha256', '20261002101500'.'TESTIMID01'.$attempt->midtrans_order_id.'150000'.'test-merchant-key');

        $response->assertOk()
            ->assertSee('action="https://dev.nicepay.co.id/nicepay/direct/v2/payment"', false)
            ->assertSee('name="timeStamp" value="20261002101500"', false)
            ->assertSee('name="tXid" value="TESTIMID0106202610021015001234"', false)
            ->assertSee('name="merchantToken" value="'.$token.'"', false)
            ->assertSee('name="callBackUrl" value="'.route('payments.nicepay.callback').'"', false);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_tanpa_attempt_nicepay_terbuka_diarahkan_ke_pesanan(): void
    {
        $tanpaAttempt = Order::factory()->create();

        $midtrans = Order::factory()->create();
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $midtrans->id,
            'midtrans_order_id' => $midtrans->order_number.'-A1',
            'status' => PaymentAttemptStatus::PENDING,
        ]);
        $midtrans->update(['active_payment_attempt_id' => $attempt->id]);

        foreach ([$tanpaAttempt, $midtrans] as $order) {
            $this->get(route('payments.nicepay.pay', ['order' => $order->uuid]))
                ->assertRedirect(route('checkout.success', ['order' => $order->uuid]));
        }
    }

    public function test_attempt_kedaluwarsa_atau_lunas_diarahkan_ke_pesanan(): void
    {
        $kedaluwarsa = Order::factory()->create();
        $this->nicepayAttempt($kedaluwarsa, ['expired_at' => now()->subMinute()]);

        $lunas = Order::factory()->paid()->create();
        $this->nicepayAttempt($lunas, ['status' => PaymentAttemptStatus::PAID]);

        foreach ([$kedaluwarsa, $lunas] as $order) {
            $this->get(route('payments.nicepay.pay', ['order' => $order->uuid]))
                ->assertRedirect(route('checkout.success', ['order' => $order->uuid]));
        }
    }

    public function test_callback_menjalankan_inquiry_dan_mengarahkan_ke_pesanan(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->nicepayAttempt($order);
        $this->fakeInquiry('0');

        $response = $this->get(route('payments.nicepay.callback', [
            'resultCd' => '9999',
            'referenceNo' => $attempt->midtrans_order_id,
        ]));

        $response->assertRedirect(route('checkout.success', ['order' => $order->uuid]));
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_callback_post_juga_diterima(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->nicepayAttempt($order);
        $this->fakeInquiry('3');

        $response = $this->post(route('payments.nicepay.callback'), [
            'resultCd' => '0000',
            'referenceNo' => $attempt->midtrans_order_id,
        ]);

        $response->assertRedirect(route('checkout.success', ['order' => $order->uuid]));
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_callback_tidak_mempercayai_parameter_sukses(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->nicepayAttempt($order);
        $this->fakeInquiry('3');

        $this->get(route('payments.nicepay.callback', [
            'resultCd' => '0000',
            'resultMsg' => 'SUCCESS',
            'referenceNo' => $attempt->midtrans_order_id,
            'amt' => '150000',
        ]));

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_callback_referensi_tak_dikenal_ke_beranda(): void
    {
        $this->get(route('payments.nicepay.callback', ['referenceNo' => 'TIDAK-ADA-A1']))
            ->assertRedirect(route('home'));

        Http::assertNothingSent();
    }

    public function test_callback_tetap_mengarahkan_saat_inquiry_gagal(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->nicepayAttempt($order);
        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::response(['error' => 'server'], 500)]);

        $this->get(route('payments.nicepay.callback', ['referenceNo' => $attempt->midtrans_order_id]))
            ->assertRedirect(route('checkout.success', ['order' => $order->uuid]));

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter=NicepayPaymentControllerTest`
Expected: FAIL, rute `payments.nicepay.pay` belum ada.

- [ ] **Step 3: Tambah penyusun form di `NicepayClient`**

Di `app/Services/Payments/Nicepay/NicepayClient.php`, tambahkan dua method ini setelah `cancel()`:

```php
    public function paymentUrl(): string
    {
        return $this->baseUrl().'/nicepay/direct/v2/payment';
    }

    public function paymentFormFields(PaymentAttempt $attempt): array
    {
        $timeStamp = $this->timestamp();

        return [
            'timeStamp' => $timeStamp,
            'tXid' => (string) $attempt->midtrans_transaction_id,
            'merchantToken' => $this->transactionToken($timeStamp, $attempt->midtrans_order_id, (int) $attempt->gross_amount),
            'callBackUrl' => route('payments.nicepay.callback'),
        ];
    }
```

- [ ] **Step 4: Buat controller**

Buat `app/Http/Controllers/Payment/NicepayPaymentController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Nicepay\NicepayClient;
use App\Services\Payments\Nicepay\NicepayPaylaterService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NicepayPaymentController extends Controller
{
    public function redirect(Order $order, NicepayClient $client): Response|RedirectResponse
    {
        $attempt = $order->activePaymentAttempt;

        if (
            ! $attempt instanceof PaymentAttempt
            || $attempt->provider !== PaymentMethods::NICEPAY
            || ! $attempt->isOpen()
            || $attempt->isExpired()
            || blank($attempt->midtrans_transaction_id)
        ) {
            return redirect()->route('checkout.success', ['order' => $order->uuid]);
        }

        return response()
            ->view('payments.nicepay-redirect', [
                'order' => $order,
                'action' => $client->paymentUrl(),
                'fields' => $client->paymentFormFields($attempt),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function callback(Request $request, NicepayPaylaterService $service): RedirectResponse
    {
        $attempt = PaymentAttempt::query()
            ->with('order:id,uuid')
            ->where('provider', PaymentMethods::NICEPAY)
            ->where('midtrans_order_id', (string) $request->input('referenceNo', ''))
            ->first();

        if (! $attempt?->order) {
            return redirect()->route('home');
        }

        rescue(fn () => $service->sync($attempt));

        return redirect()->route('checkout.success', ['order' => $attempt->order->uuid]);
    }
}
```

Header `no-store` dipasang karena `timeStamp` dan `merchantToken` dibuat ulang setiap halaman dimuat; salinan dari cache browser akan mengirim token lama.

- [ ] **Step 5: Muat skill antislop, lalu buat view**

Sebelum menulis view, muat skill `antislop:antislop`, `antislop:antislop-ui`, `antislop:antislop-copywriting`, `antislop:antislop-human`, dan `antislop:antislop-layoutmobile`. Terapkan aturannya pada teks dan tampilan view ini. Yang tidak boleh berubah: atribut `id`, `method`, dan `action` form, keempat input tersembunyi, format `name="..." value="..."` pada input (dipakai tes), dan skrip pengirim otomatis.

Buat `resources/views/payments/nicepay-redirect.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="robots" content="noindex" />
        <title>Lanjut ke Indodana - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-paper px-4 text-ink antialiased">
        <main class="w-full max-w-sm">
            <h1 class="text-xl font-extrabold tracking-tight">Lanjut ke Indodana</h1>
            <p class="mt-2 text-sm text-ink/60">
                Pesanan {{ $order->order_number }}, total {{ rupiah((int) $order->grand_total) }}. Tenor cicilan dipilih di halaman Indodana.
            </p>

            <form id="nicepay-payment" method="POST" action="{{ $action }}" class="mt-6">
                @foreach ($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}" />
                @endforeach

                <button type="submit" class="w-full rounded-lg bg-ink px-4 py-3 text-sm font-semibold text-white transition hover:bg-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2">
                    Buka halaman Indodana
                </button>
            </form>

            <a href="{{ route('checkout.success', ['order' => $order->uuid]) }}" class="mt-4 inline-block text-sm text-ink/60 underline underline-offset-4 hover:text-ink">
                Kembali ke pesanan
            </a>
        </main>

        <script>
            document.getElementById('nicepay-payment').submit();
        </script>
    </body>
</html>
```

Tombol selalu terlihat, jadi halaman tetap berfungsi tanpa JavaScript atau bila pengiriman otomatis diblokir. Ini menggantikan tombol di dalam `<noscript>` yang disebut spec bagian 5.3.

- [ ] **Step 6: Rute dan pengecualian CSRF**

Di `routes/web.php`, tambahkan `use App\Http\Controllers\Payment\NicepayPaymentController;` dan dua rute ini setelah rute `payments.nicepay.notification`:

```php
Route::get('/payments/nicepay/pay/{order:uuid}', [NicepayPaymentController::class, 'redirect'])
    ->name('payments.nicepay.pay');

Route::match(['get', 'post'], '/payments/nicepay/callback', [NicepayPaymentController::class, 'callback'])
    ->name('payments.nicepay.callback');
```

Di `bootstrap/app.php`, tambahkan `'payments/nicepay/callback',` ke daftar `except` pada `validateCsrfTokens`.

- [ ] **Step 7: Jalankan tes**

Run: `php artisan test --filter=NicepayPaymentControllerTest`
Expected: PASS, 8 tes.

Run: `php artisan test tests/Feature/Payments`
Expected: PASS semua.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/Payments/Nicepay/NicepayClient.php app/Http/Controllers/Payment/NicepayPaymentController.php resources/views/payments/nicepay-redirect.blade.php routes/web.php bootstrap/app.php tests/Feature/Payments/NicepayPaymentControllerTest.php
git commit -m "feat(payments): halaman redirect ke Nicepay dan callback"
```

---

### Task 7: Penyusun payload Registration

**Files:**
- Create: `app/Services/Payments/Nicepay/NicepayRegistrationPayload.php`
- Test: `tests/Feature/Payments/NicepayRegistrationPayloadTest.php`

**Interfaces:**
- Consumes: `NicepayClient::timestamp()`, `merchantId()`, `transactionToken()`, `PAY_METHOD_PAYLATER` dari Task 4; rute `payments.nicepay.notification` (Task 5) dan `payments.nicepay.callback` (Task 6); rute `products.show` dan `home` yang sudah ada; `Order::isPickup()`; helper `setting(string $key, mixed $default = null)`.
- Produces: `NicepayRegistrationPayload::build(Order $order, PaymentAttempt $attempt): array` (array siap dikirim ke `NicepayClient::register()`; `cartData` dan `sellers` berupa string JSON).

- [ ] **Step 1: Tulis tes yang gagal**

Buat `tests/Feature/Payments/NicepayRegistrationPayloadTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Services\Payments\Nicepay\NicepayRegistrationPayload;
use App\Services\Payments\PaymentMethods;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NicepayRegistrationPayloadTest extends TestCase
{
    use RefreshDatabase;

    private NicepayRegistrationPayload $builder;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
            'services.nicepay.store_city' => 'Jakarta Utara',
            'services.nicepay.store_state' => 'DKI Jakarta',
            'services.nicepay.store_postcode' => '14440',
        ]);

        SettingService::set('site_name', 'Berkat Cell');
        SettingService::set('site_email', 'toko@example.com');
        SettingService::set('site_phone', '0877-7600-6060');
        SettingService::set('site_address', 'Jl. Contoh Toko No. 8');

        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-10-02 10:15:00', 'Asia/Jakarta'));

        $this->builder = app(NicepayRegistrationPayload::class);
    }

    private function orderWithItem(array $overrides = []): Order
    {
        $product = Product::factory()->create(['name' => 'Samsung Galaxy A16 5G']);
        $order = Order::factory()->create(array_merge([
            'customer_name' => 'Budi Santoso',
            'customer_email' => 'budi@example.com',
            'customer_phone' => '+62 812-3456-7890',
            'shipping_destination_label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910',
            'shipping_address' => 'Jl. Contoh No. 1',
            'subtotal' => 200000,
            'discount_amount' => 20000,
            'shipping_cost' => 15000,
            'grand_total' => 195000,
        ], $overrides));
        $order->items()->create([
            'product_id' => $product->id,
            'price' => 100000,
            'quantity' => 2,
            'total' => 200000,
        ]);

        return $order;
    }

    private function attemptFor(Order $order): PaymentAttempt
    {
        return PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $order->order_number.'-A1',
            'payment_method' => 'indodana',
            'status' => PaymentAttemptStatus::CREATING,
            'gross_amount' => $order->grand_total,
        ]);
    }

    private function cartSum(array $cart): int
    {
        $sum = 0;

        foreach ($cart['item'] as $item) {
            $line = (int) $item['goods_amt'] * (int) $item['goods_quantity'];
            $sum += $item['goods_id'] === 'discount' ? -$line : $line;
        }

        return $sum;
    }

    public function test_field_tetap_dan_token(): void
    {
        $order = $this->orderWithItem();
        $attempt = $this->attemptFor($order);

        $payload = $this->builder->build($order, $attempt);

        $this->assertSame('20261002101500', $payload['timeStamp']);
        $this->assertSame('TESTIMID01', $payload['iMid']);
        $this->assertSame('06', $payload['payMethod']);
        $this->assertSame('IDR', $payload['currency']);
        $this->assertSame('IDNA', $payload['mitraCd']);
        $this->assertSame('195000', $payload['amt']);
        $this->assertSame($attempt->midtrans_order_id, $payload['referenceNo']);
        $this->assertSame('Pesanan '.$order->order_number, $payload['goodsNm']);
        $this->assertSame('budi@example.com', $payload['billingEmail']);
        $this->assertSame('Indonesia', $payload['billingCountry']);
        $this->assertSame('Indonesia', $payload['deliveryCountry']);
        $this->assertSame(route('payments.nicepay.notification'), $payload['dbProcessUrl']);
        $this->assertSame(route('payments.nicepay.callback'), $payload['callBackUrl']);
        $this->assertSame(
            hash('sha256', '20261002101500'.'TESTIMID01'.$attempt->midtrans_order_id.'195000'.'test-merchant-key'),
            $payload['merchantToken'],
        );
    }

    public function test_alamat_diurai_dari_label_rajaongkir(): void
    {
        $order = $this->orderWithItem();

        $payload = $this->builder->build($order, $this->attemptFor($order));

        foreach (['billing', 'delivery'] as $prefix) {
            $this->assertSame('JAKARTA TIMUR', $payload[$prefix.'City']);
            $this->assertSame('DKI JAKARTA', $payload[$prefix.'State']);
            $this->assertSame('13910', $payload[$prefix.'PostCd']);
            $this->assertSame('Jl. Contoh No. 1', $payload[$prefix.'Addr']);
        }
    }

    public function test_label_tak_terurai_dan_pickup_memakai_alamat_toko(): void
    {
        $takTerurai = $this->orderWithItem(['shipping_destination_label' => 'Jakarta']);
        $pickup = $this->orderWithItem([
            'shipping_courier' => Order::PICKUP_COURIER,
            'shipping_destination_label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910',
            'shipping_cost' => 0,
            'grand_total' => 180000,
        ]);

        foreach ([$takTerurai, $pickup] as $order) {
            $payload = $this->builder->build($order, $this->attemptFor($order));

            $this->assertSame('Jakarta Utara', $payload['billingCity']);
            $this->assertSame('DKI Jakarta', $payload['billingState']);
            $this->assertSame('14440', $payload['billingPostCd']);
            $this->assertSame('14440', $payload['deliveryPostCd']);
        }
    }

    public function test_telepon_angka_saja_dan_field_panjang_dipotong(): void
    {
        $order = $this->orderWithItem([
            'customer_name' => str_repeat('Nama ', 30),
            'customer_phone' => '+62 812-3456-7890 ext 12345',
            'shipping_address' => str_repeat('Jalan Panjang ', 20),
        ]);

        $payload = $this->builder->build($order, $this->attemptFor($order));

        $this->assertSame('628123456789012', $payload['billingPhone']);
        $this->assertSame('628123456789012', $payload['deliveryPhone']);
        $this->assertSame(100, mb_strlen($payload['billingNm']));
        $this->assertSame(30, mb_strlen($payload['deliveryNm']));
        $this->assertSame(100, mb_strlen($payload['billingAddr']));
        $this->assertSame(100, mb_strlen($payload['deliveryAddr']));
    }

    public function test_keranjang_memuat_ongkir_dan_diskon_dan_jumlahnya_sama_dengan_total(): void
    {
        $order = $this->orderWithItem();

        $payload = $this->builder->build($order, $this->attemptFor($order));
        $cart = json_decode($payload['cartData'], true);

        $this->assertSame('3', $cart['count']);
        $this->assertSame(['shippingfee', 'discount'], [$cart['item'][1]['goods_id'], $cart['item'][2]['goods_id']]);
        $this->assertSame('Samsung Galaxy A16 5G', $cart['item'][0]['goods_name']);
        $this->assertSame('100000', $cart['item'][0]['goods_amt']);
        $this->assertSame('2', $cart['item'][0]['goods_quantity']);
        $this->assertSame('others', $cart['item'][0]['goods_type']);
        $this->assertSame('TESTIMID01', $cart['item'][0]['goods_sellers_id']);
        $this->assertSame('Berkat Cell', $cart['item'][0]['goods_sellers_name']);
        $this->assertSame('20000', $cart['item'][2]['goods_amt']);
        $this->assertSame(195000, $this->cartSum($cart));
    }

    public function test_keranjang_tak_cocok_dikirim_satu_baris(): void
    {
        $order = $this->orderWithItem(['grand_total' => 199999]);

        $payload = $this->builder->build($order, $this->attemptFor($order));
        $cart = json_decode($payload['cartData'], true);

        $this->assertSame('1', $cart['count']);
        $this->assertSame('Pesanan '.$order->order_number, $cart['item'][0]['goods_name']);
        $this->assertSame('199999', $cart['item'][0]['goods_amt']);
        $this->assertSame('1', $cart['item'][0]['goods_quantity']);
    }

    public function test_keranjang_terlalu_panjang_dikirim_satu_baris(): void
    {
        $product = Product::factory()->create(['name' => str_repeat('Produk dengan nama panjang ', 4)]);
        $order = Order::factory()->create([
            'shipping_destination_label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910',
            'subtotal' => 40000,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 40000,
        ]);

        foreach (range(1, 40) as $ignored) {
            $order->items()->create(['product_id' => $product->id, 'price' => 1000, 'quantity' => 1, 'total' => 1000]);
        }

        $payload = $this->builder->build($order, $this->attemptFor($order));
        $cart = json_decode($payload['cartData'], true);

        $this->assertLessThanOrEqual(4000, strlen($payload['cartData']));
        $this->assertSame('1', $cart['count']);
        $this->assertSame('40000', $cart['item'][0]['goods_amt']);
    }

    public function test_penjual_diambil_dari_setting_toko(): void
    {
        $order = $this->orderWithItem();

        $payload = $this->builder->build($order, $this->attemptFor($order));
        $seller = json_decode($payload['sellers'], true)[0];

        $this->assertSame('TESTIMID01', $seller['sellersId']);
        $this->assertSame('Berkat Cell', $seller['sellersNm']);
        $this->assertSame('toko@example.com', $seller['sellersEmail']);
        $this->assertSame('Jl. Contoh Toko No. 8', $seller['sellersAddress']['sellerAddr']);
        $this->assertSame('Jakarta Utara', $seller['sellersAddress']['sellerCity']);
        $this->assertSame('14440', $seller['sellersAddress']['sellerPostCd']);
        $this->assertSame('087776006060', $seller['sellersAddress']['sellerPhone']);
        $this->assertSame('ID', $seller['sellersAddress']['sellerCountry']);
    }

    public function test_tenor_default_mengikuti_nominal(): void
    {
        $kecil = $this->orderWithItem();
        $besar = $this->orderWithItem([
            'subtotal' => 3000000,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 3000000,
        ]);

        $payloadKecil = $this->builder->build($kecil, $this->attemptFor($kecil));
        $payloadBesar = $this->builder->build($besar, $this->attemptFor($besar));

        $this->assertSame(['1', '1'], [$payloadKecil['instmntType'], $payloadKecil['instmntMon']]);
        $this->assertSame(['2', '3'], [$payloadBesar['instmntType'], $payloadBesar['instmntMon']]);
    }

    public function test_user_ip_hanya_ipv4(): void
    {
        $order = $this->orderWithItem();
        $attempt = $this->attemptFor($order);

        request()->server->set('REMOTE_ADDR', '203.0.113.7');
        $this->assertSame('203.0.113.7', $this->builder->build($order, $attempt)['userIP']);

        request()->server->set('REMOTE_ADDR', '2001:db8::1');
        $this->assertSame('127.0.0.1', $this->builder->build($order, $attempt)['userIP']);
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter=NicepayRegistrationPayloadTest`
Expected: FAIL, `Class "App\Services\Payments\Nicepay\NicepayRegistrationPayload" not found`.

- [ ] **Step 3: Buat `NicepayRegistrationPayload`**

Buat `app/Services/Payments/Nicepay/NicepayRegistrationPayload.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Payments\Nicepay;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Log;

class NicepayRegistrationPayload
{
    private const MITRA_INDODANA = 'IDNA';

    private const MAX_CART_LENGTH = 4000;

    private const ONE_MONTH_TENOR_LIMIT = 2_000_000;

    public function __construct(
        private readonly NicepayClient $client,
    ) {}

    public function build(Order $order, PaymentAttempt $attempt): array
    {
        $order->loadMissing('items.product', 'items.variant');

        $timeStamp = $this->client->timestamp();
        $amount = (int) $order->grand_total;
        $address = $this->address($order);
        $phone = mb_substr((string) preg_replace('/\D+/', '', (string) $order->customer_phone), 0, 15);
        $street = mb_substr((string) $order->shipping_address, 0, 100);
        $oneMonth = $amount <= self::ONE_MONTH_TENOR_LIMIT;

        return [
            'timeStamp' => $timeStamp,
            'iMid' => $this->client->merchantId(),
            'payMethod' => NicepayClient::PAY_METHOD_PAYLATER,
            'currency' => 'IDR',
            'amt' => (string) $amount,
            'referenceNo' => $attempt->midtrans_order_id,
            'goodsNm' => 'Pesanan '.$order->order_number,
            'billingNm' => mb_substr((string) $order->customer_name, 0, 100),
            'billingPhone' => $phone,
            'billingEmail' => (string) $order->customer_email,
            'billingAddr' => $street,
            'billingCity' => $address['city'],
            'billingState' => $address['state'],
            'billingPostCd' => $address['postcode'],
            'billingCountry' => 'Indonesia',
            'deliveryNm' => mb_substr((string) $order->customer_name, 0, 30),
            'deliveryPhone' => $phone,
            'deliveryAddr' => $street,
            'deliveryCity' => $address['city'],
            'deliveryState' => $address['state'],
            'deliveryPostCd' => $address['postcode'],
            'deliveryCountry' => 'Indonesia',
            'dbProcessUrl' => route('payments.nicepay.notification'),
            'callBackUrl' => route('payments.nicepay.callback'),
            'userIP' => $this->userIp(),
            'userAgent' => mb_substr((string) request()->userAgent(), 0, 255),
            'cartData' => json_encode($this->cart($order), JSON_UNESCAPED_SLASHES),
            'sellers' => json_encode($this->sellers(), JSON_UNESCAPED_SLASHES),
            'instmntType' => $oneMonth ? '1' : '2',
            'instmntMon' => $oneMonth ? '1' : '3',
            'mitraCd' => self::MITRA_INDODANA,
            'merchantToken' => $this->client->transactionToken($timeStamp, $attempt->midtrans_order_id, $amount),
        ];
    }

    /**
     * Label RajaOngkir berformat "KELURAHAN, KECAMATAN, KOTA, PROVINSI, KODEPOS".
     * Pesanan pickup dan label yang tidak cocok dengan format itu memakai alamat toko.
     */
    private function address(Order $order): array
    {
        $parts = array_map('trim', explode(',', (string) $order->shipping_destination_label));

        if (! $order->isPickup() && count($parts) === 5 && ctype_digit($parts[4])) {
            return [
                'city' => mb_substr($parts[2], 0, 50),
                'state' => mb_substr($parts[3], 0, 50),
                'postcode' => $parts[4],
            ];
        }

        if (! $order->isPickup()) {
            Log::warning('Nicepay: label tujuan tidak bisa diurai; memakai alamat toko.', ['order_id' => $order->id]);
        }

        return [
            'city' => (string) config('services.nicepay.store_city'),
            'state' => (string) config('services.nicepay.store_state'),
            'postcode' => (string) config('services.nicepay.store_postcode'),
        ];
    }

    /**
     * Nicepay mensyaratkan amt = barang + ongkir - diskon. Bila rincian tidak
     * cocok dengan grand_total atau melebihi 4.000 karakter, dikirim satu baris
     * senilai total supaya registrasi tidak ditolak.
     */
    private function cart(Order $order): array
    {
        $items = [];
        $sum = 0;

        foreach ($order->items as $item) {
            $items[] = $this->cartLine(
                (string) ($item->variant_id ?? $item->product_id ?? $item->id),
                trim(($item->product?->name ?? 'Produk').' '.($item->variant?->name ?? '')),
                (int) $item->price,
                (int) $item->quantity,
                $this->productUrl($item),
            );
            $sum += (int) $item->price * (int) $item->quantity;
        }

        $shipping = (int) $order->shipping_cost;

        if ($shipping > 0) {
            $items[] = $this->cartLine('shippingfee', 'Ongkos kirim', $shipping, 1, route('home'));
            $sum += $shipping;
        }

        $discount = min((int) $order->discount_amount, (int) $order->subtotal);

        if ($discount > 0) {
            $items[] = $this->cartLine('discount', 'Diskon', $discount, 1, route('home'));
            $sum -= $discount;
        }

        $cart = ['count' => (string) count($items), 'item' => $items];

        if (
            $order->items->isEmpty()
            || $sum !== (int) $order->grand_total
            || strlen((string) json_encode($cart, JSON_UNESCAPED_SLASHES)) > self::MAX_CART_LENGTH
        ) {
            Log::warning('Nicepay: cartData tidak cocok dengan total atau terlalu panjang; dikirim satu baris.', [
                'order_id' => $order->id,
                'cart_sum' => $sum,
                'grand_total' => $order->grand_total,
            ]);

            return [
                'count' => '1',
                'item' => [$this->cartLine(
                    (string) $order->order_number,
                    'Pesanan '.$order->order_number,
                    (int) $order->grand_total,
                    1,
                    route('home'),
                )],
            ];
        }

        return $cart;
    }

    private function cartLine(string $id, string $name, int $amount, int $quantity, string $url): array
    {
        return [
            'goods_id' => $id,
            'goods_name' => mb_substr($name, 0, 100),
            'goods_amt' => (string) $amount,
            'goods_type' => 'others',
            'goods_quantity' => (string) $quantity,
            'goods_url' => $url,
            'goods_sellers_id' => $this->client->merchantId(),
            'goods_sellers_name' => $this->storeName(),
        ];
    }

    private function productUrl(OrderItem $item): string
    {
        return $item->product ? route('products.show', $item->product) : route('home');
    }

    private function sellers(): array
    {
        return [[
            'sellersId' => $this->client->merchantId(),
            'sellersNm' => $this->storeName(),
            'sellersEmail' => (string) setting('site_email', ''),
            'sellersUrl' => url('/'),
            'sellersAddress' => [
                'sellerNm' => $this->storeName(),
                'sellerLastNm' => $this->storeName(),
                'sellerAddr' => mb_substr((string) setting('site_address', ''), 0, 100),
                'sellerCity' => (string) config('services.nicepay.store_city'),
                'sellerPostCd' => (string) config('services.nicepay.store_postcode'),
                'sellerPhone' => (string) preg_replace('/\D+/', '', (string) setting('site_phone', '')),
                'sellerCountry' => 'ID',
            ],
        ]];
    }

    private function storeName(): string
    {
        return (string) setting('site_name', config('app.name'));
    }

    private function userIp(): string
    {
        $ip = (string) request()->ip();

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1';
    }
}
```

- [ ] **Step 4: Jalankan tes**

Run: `php artisan test --filter=NicepayRegistrationPayloadTest`
Expected: PASS, 10 tes.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/Payments/Nicepay/NicepayRegistrationPayload.php tests/Feature/Payments/NicepayRegistrationPayloadTest.php
git commit -m "feat(payments): penyusun payload Registration Nicepay"
```

---

### Task 8: Memulai pembayaran Indodana

**Files:**
- Modify: `app/Services/Payments/PaymentMethods.php`
- Modify: `app/Services/Payments/Nicepay/NicepayPaylaterService.php` (konstruktor dan `start()`)
- Modify: `app/Services/Payments/PaymentAttemptService.php`
- Modify: `tests/Feature/Payments/PaymentMethodsTest.php` (tambah tes)
- Test: `tests/Feature/Payments/NicepayPaymentAttemptTest.php`

**Interfaces:**
- Consumes: `NicepayRegistrationPayload::build(Order $order, PaymentAttempt $attempt): array` dari Task 7; `NicepayClient::register(array $payload): array` dari Task 4; `PaymentSettlementService::cancelAtGateway()` dari Task 4; `User::isAdmin(): bool` yang sudah ada.
- Produces:
  - `PaymentMethods::INDODANA = 'indodana'`
  - `PaymentMethods::nicepayConfigured(): bool`
  - `PaymentMethods::available()` kini menyaring Indodana sesuai spec bagian 5.1
  - `NicepayPaylaterService::start(Order $order, PaymentAttempt $attempt): void`
  - `PaymentAttemptService::createOrReuseActiveAttempt()` menerima metode `indodana`

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan tes ini ke `tests/Feature/Payments/PaymentMethodsTest.php`, beserta `use App\Models\User;` di blok `use`:

```php
    private function enableNicepay(bool $adminOnly): void
    {
        config([
            'services.nicepay.enabled' => true,
            'services.nicepay.admin_only' => $adminOnly,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);
    }

    private function eligibleOrder(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_email' => 'budi@example.com',
            'grand_total' => 1500000,
        ], $overrides));
    }

    public function test_indodana_tersembunyi_saat_flag_mati_atau_kredensial_kosong(): void
    {
        $order = $this->eligibleOrder();
        $admin = User::factory()->admin()->create();

        $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, $admin));

        $this->enableNicepay(false);
        config(['services.nicepay.merchant_key' => '']);

        $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, $admin));
    }

    public function test_mode_khusus_admin_hanya_menampilkan_indodana_ke_admin(): void
    {
        $this->enableNicepay(true);
        $order = $this->eligibleOrder();

        $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, null));
        $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, User::factory()->create()));

        $forAdmin = PaymentMethods::available($order, User::factory()->admin()->create());

        $this->assertSame(PaymentMethods::INDODANA, array_key_last($forAdmin));
        $this->assertSame(PaymentMethods::NICEPAY, $forAdmin[PaymentMethods::INDODANA]['provider']);
        $this->assertSame('gopay', array_key_first($forAdmin));
    }

    public function test_tanpa_mode_admin_indodana_tampil_untuk_semua(): void
    {
        $this->enableNicepay(false);

        $this->assertArrayHasKey(PaymentMethods::INDODANA, PaymentMethods::available($this->eligibleOrder(), null));
    }

    public function test_indodana_tersembunyi_di_luar_batas_nominal_atau_email_terlalu_panjang(): void
    {
        $this->enableNicepay(false);

        $tidakMemenuhi = [
            $this->eligibleOrder(['grand_total' => 9999]),
            $this->eligibleOrder(['grand_total' => 50000001]),
            $this->eligibleOrder(['customer_email' => str_repeat('a', 30).'@example.com']),
        ];

        foreach ($tidakMemenuhi as $order) {
            $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, null));
        }

        foreach ([10000, 50000000] as $batas) {
            $this->assertArrayHasKey(
                PaymentMethods::INDODANA,
                PaymentMethods::available($this->eligibleOrder(['grand_total' => $batas]), null),
            );
        }
    }
```

Buat `tests/Feature/Payments/NicepayPaymentAttemptTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentAttemptService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NicepayPaymentAttemptTest extends TestCase
{
    use RefreshDatabase;

    private const TXID = 'TESTIMID0106202610021015009999';

    private PaymentAttemptService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.client_key' => 'test-client-key',
            'services.midtrans.is_production' => false,
            'services.nicepay.enabled' => true,
            'services.nicepay.admin_only' => false,
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
            'services.nicepay.expiry_minutes' => 1440,
            'services.nicepay.store_city' => 'Jakarta Utara',
            'services.nicepay.store_state' => 'DKI Jakarta',
            'services.nicepay.store_postcode' => '14440',
        ]);

        Http::preventStrayRequests();
        Queue::fake();

        $this->service = app(PaymentAttemptService::class);
    }

    private function fakeGateways(): void
    {
        Http::fake([
            '*/nicepay/direct/v2/registration' => Http::response([
                'resultCd' => '0000',
                'resultMsg' => 'SUCCESS',
                'tXid' => self::TXID,
            ], 200),
            '*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '0000'], 200),
            '*/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-abc',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
            ], 201),
            '*/v2/*/cancel' => Http::response(['status_code' => '200'], 200),
        ]);
    }

    public function test_membuat_attempt_indodana_lewat_registration(): void
    {
        $this->fakeGateways();
        $order = Order::factory()->create(['grand_total' => 1500000]);

        $attempt = $this->service->createOrReuseActiveAttempt($order, PaymentMethods::INDODANA);

        $this->assertSame(PaymentMethods::NICEPAY, $attempt->provider);
        $this->assertSame(PaymentMethods::INDODANA, $attempt->payment_method);
        $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->status);
        $this->assertSame(self::TXID, $attempt->midtrans_transaction_id);
        $this->assertSame($order->order_number.'-A1', $attempt->midtrans_order_id);
        $this->assertNull($attempt->snap_token);
        $this->assertSame('IDNA', $attempt->snap_request_payload['mitraCd']);
        $this->assertSame(self::TXID, $attempt->snap_response_payload['tXid']);
        $this->assertSame($attempt->id, $order->fresh()->active_payment_attempt_id);
        $this->assertEqualsWithDelta(now()->addMinutes(1440)->timestamp, $attempt->expired_at->timestamp, 60);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/nicepay/direct/v2/registration')
            && $request['mitraCd'] === 'IDNA'
            && $request['payMethod'] === '06'
            && $request['amt'] === '1500000'
            && $request['referenceNo'] === $order->order_number.'-A1');
    }

    public function test_klik_ganda_memakai_attempt_yang_sama_dan_satu_registration(): void
    {
        $this->fakeGateways();
        $order = Order::factory()->create(['grand_total' => 1500000]);

        $first = $this->service->createOrReuseActiveAttempt($order, PaymentMethods::INDODANA);
        $second = $this->service->createOrReuseActiveAttempt($order, PaymentMethods::INDODANA);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentAttempt::query()->where('order_id', $order->id)->count());
        Http::assertSentCount(1);
    }

    public function test_pindah_dari_indodana_ke_va_membatalkan_di_nicepay(): void
    {
        $this->fakeGateways();
        $order = Order::factory()->create(['grand_total' => 1500000]);

        $indodana = $this->service->createOrReuseActiveAttempt($order, PaymentMethods::INDODANA);
        $va = $this->service->createOrReuseActiveAttempt($order, 'bni_va');

        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $indodana->fresh()->status);
        $this->assertSame(PaymentMethods::MIDTRANS, $va->provider);
        $this->assertSame($order->order_number.'-A2', $va->midtrans_order_id);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/nicepay/direct/v2/cancel') && $request['tXid'] === self::TXID);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'midtrans.com/v2/'));
    }

    public function test_pindah_dari_va_ke_indodana_membatalkan_di_midtrans(): void
    {
        $this->fakeGateways();
        $order = Order::factory()->create(['grand_total' => 1500000]);

        $va = $this->service->createOrReuseActiveAttempt($order, 'bni_va');
        $indodana = $this->service->createOrReuseActiveAttempt($order, PaymentMethods::INDODANA);

        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $va->fresh()->status);
        $this->assertSame(PaymentMethods::NICEPAY, $indodana->provider);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'midtrans.com/v2/'.$va->midtrans_order_id.'/cancel'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/nicepay/direct/v2/cancel'));
    }

    public function test_registration_gagal_tidak_menyisakan_attempt(): void
    {
        Http::fake([
            '*/nicepay/direct/v2/registration' => Http::response(['resultCd' => '9999', 'resultMsg' => 'Invalid'], 200),
        ]);
        $order = Order::factory()->create(['grand_total' => 1500000]);

        try {
            $this->service->createOrReuseActiveAttempt($order, PaymentMethods::INDODANA);
            $this->fail('Seharusnya melempar BusinessRuleException.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('Gagal memulai pembayaran. Silakan coba lagi.', $e->getMessage());
        }

        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertNull($order->fresh()->active_payment_attempt_id);
    }

    public function test_indodana_ditolak_saat_nicepay_belum_dikonfigurasi(): void
    {
        config(['services.nicepay.enabled' => false]);
        $order = Order::factory()->create(['grand_total' => 1500000]);

        try {
            $this->service->createOrReuseActiveAttempt($order, PaymentMethods::INDODANA);
            $this->fail('Seharusnya melempar BusinessRuleException.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('Metode pembayaran tidak didukung.', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_masa_berlaku_mengikuti_konfigurasi(): void
    {
        $this->fakeGateways();
        config(['services.nicepay.expiry_minutes' => 60]);
        $order = Order::factory()->create(['grand_total' => 1500000]);

        $attempt = $this->service->createOrReuseActiveAttempt($order, PaymentMethods::INDODANA);

        $this->assertEqualsWithDelta(now()->addMinutes(60)->timestamp, $attempt->expired_at->timestamp, 60);
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter="PaymentMethodsTest|NicepayPaymentAttemptTest"`
Expected: FAIL, konstanta `PaymentMethods::INDODANA` belum ada.

- [ ] **Step 3: Daftarkan Indodana di `PaymentMethods`**

Di `app/Services/Payments/PaymentMethods.php`:

1. Tambahkan konstanta setelah `NICEPAY`:

```php
    public const INDODANA = 'indodana';

    private const INDODANA_MIN_AMOUNT = 10_000;

    private const INDODANA_MAX_AMOUNT = 50_000_000;

    private const INDODANA_MAX_EMAIL_LENGTH = 40;
```

2. Tambahkan elemen terakhir `METHODS`:

```php
        self::INDODANA => ['provider' => self::NICEPAY, 'name' => 'Indodana PayLater', 'label' => 'Indodana', 'type' => 'Cicilan tanpa kartu', 'brand' => '#1f7a00'],
```

Warna `#1f7a00` adalah hijau situs Indodana (`#33cc00`) yang digelapkan supaya teks putih di atasnya mencapai kontras 4,5:1. Task 9 memeriksanya dengan alat kontras antislop.

3. Ganti method `available()` dan tambahkan dua method di bawahnya:

```php
    public static function available(Order $order, ?User $user): array
    {
        return array_filter(
            self::METHODS,
            fn (array $method): bool => $method['provider'] === self::MIDTRANS || self::indodanaAvailable($order, $user),
        );
    }

    public static function nicepayConfigured(): bool
    {
        return (bool) config('services.nicepay.enabled')
            && filled(config('services.nicepay.imid'))
            && filled(config('services.nicepay.merchant_key'));
    }

    private static function indodanaAvailable(Order $order, ?User $user): bool
    {
        if (! self::nicepayConfigured()) {
            return false;
        }

        if (config('services.nicepay.admin_only') && ! $user?->isAdmin()) {
            return false;
        }

        $total = (int) $order->grand_total;

        return $total >= self::INDODANA_MIN_AMOUNT
            && $total <= self::INDODANA_MAX_AMOUNT
            && mb_strlen((string) $order->customer_email) <= self::INDODANA_MAX_EMAIL_LENGTH;
    }
```

- [ ] **Step 4: `NicepayPaylaterService::start()`**

Di `app/Services/Payments/Nicepay/NicepayPaylaterService.php`:

1. Tambahkan parameter terakhir konstruktor: `private readonly NicepayRegistrationPayload $payload,`.
2. Tambahkan method ini sebelum `sync()`:

```php
    public function start(Order $order, PaymentAttempt $attempt): void
    {
        $payload = $this->payload->build($order, $attempt);
        $response = $this->client->register($payload);

        $attempt->update([
            'status' => PaymentAttemptStatus::PENDING,
            'midtrans_transaction_id' => $response['tXid'],
            'snap_request_payload' => $payload,
            'snap_response_payload' => $response,
        ]);
    }
```

- [ ] **Step 5: `PaymentAttemptService` memulai attempt Nicepay**

Di `app/Services/Payments/PaymentAttemptService.php`:

1. Di `createOrReuseActiveAttempt()`, tambahkan pemeriksaan ini tepat setelah pemeriksaan `$provider === null`:

```php
        if ($provider === PaymentMethods::NICEPAY && ! PaymentMethods::nicepayConfigured()) {
            throw new BusinessRuleException('Metode pembayaran tidak didukung.');
        }
```

2. Ganti method `start()` dan `expiryMinutes()` menjadi:

```php
    private function start(string $provider, Order $order, PaymentAttempt $attempt, string $paymentMethod): void
    {
        match ($provider) {
            PaymentMethods::NICEPAY => $this->nicepay->start($order, $attempt),
            default => $this->midtrans->start($order, $attempt, $paymentMethod),
        };
    }

    private function expiryMinutes(string $provider): int
    {
        return $provider === PaymentMethods::NICEPAY
            ? max(1, (int) config('services.nicepay.expiry_minutes', 1440))
            : order_expiry_minutes();
    }
```

- [ ] **Step 6: Jalankan tes**

Run: `php artisan test --filter="PaymentMethodsTest|NicepayPaymentAttemptTest"`
Expected: PASS.

Run: `php artisan test`
Expected: PASS seluruh suite. `MidtransInvoicePageTest` lolos tanpa diubah karena Nicepay mati secara default.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/Payments/PaymentMethods.php app/Services/Payments/Nicepay/NicepayPaylaterService.php app/Services/Payments/PaymentAttemptService.php tests/Feature/Payments/PaymentMethodsTest.php tests/Feature/Payments/NicepayPaymentAttemptTest.php
git commit -m "feat(payments): mulai pembayaran Indodana lewat Registration Nicepay"
```

---

### Task 9: Indodana di halaman invoice

**Files:**
- Modify: `resources/views/pages/storefront/⚡order-success.blade.php`
- Modify: `app/Services/Payments/PaymentMethods.php` (hanya bila pemeriksaan kontras mengubah warna)
- Test: `tests/Feature/Payments/NicepayInvoicePageTest.php`

**Interfaces:**
- Consumes: `PaymentMethods::available()`, `PaymentMethods::name()`, `PaymentMethods::NICEPAY`, `PaymentMethods::INDODANA` dari Task 2 dan Task 8; `PaymentAttemptService::createOrReuseActiveAttempt()` dari Task 8; rute `payments.nicepay.pay` dari Task 6.
- Produces: halaman invoice yang mengarahkan browser ke `payments.nicepay.pay` untuk attempt Nicepay dan tetap mengirim event `snap-pay` untuk Midtrans.

- [ ] **Step 1: Tulis tes yang gagal**

Buat `tests/Feature/Payments/NicepayInvoicePageTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class NicepayInvoicePageTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::storefront.order-success';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.client_key' => 'test-client-key',
            'services.midtrans.is_production' => false,
            'services.nicepay.enabled' => true,
            'services.nicepay.admin_only' => false,
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
            'services.nicepay.store_city' => 'Jakarta Utara',
            'services.nicepay.store_state' => 'DKI Jakarta',
            'services.nicepay.store_postcode' => '14440',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*/nicepay/direct/v2/registration' => Http::response([
                'resultCd' => '0000',
                'resultMsg' => 'SUCCESS',
                'tXid' => 'TESTIMID0106202610021015009999',
            ], 200),
            '*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '0000'], 200),
            '*/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-abc',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
            ], 201),
            '*/v2/*/cancel' => Http::response(['status_code' => '200'], 200),
        ]);
        Queue::fake();
    }

    private function order(): Order
    {
        return Order::factory()->create([
            'customer_email' => 'budi@example.com',
            'grand_total' => 1500000,
        ]);
    }

    private function activeNicepayAttempt(Order $order): PaymentAttempt
    {
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $order->order_number.'-A1',
            'midtrans_transaction_id' => 'TESTIMID0106202610021015009999',
            'payment_method' => PaymentMethods::INDODANA,
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
            'snap_token' => null,
            'redirect_url' => null,
            'expired_at' => now()->addDay(),
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        return $attempt;
    }

    public function test_indodana_tidak_tampil_saat_flag_mati(): void
    {
        config(['services.nicepay.enabled' => false]);

        Livewire::test(self::COMPONENT, ['order' => $this->order()])
            ->assertDontSee('Indodana');
    }

    public function test_mode_khusus_admin_hanya_menampilkan_indodana_ke_admin(): void
    {
        config(['services.nicepay.admin_only' => true]);
        $order = $this->order();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertDontSee('Indodana');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::COMPONENT, ['order' => $order])
            ->assertSee('Indodana');
    }

    public function test_tamu_melihat_indodana_saat_mode_admin_mati(): void
    {
        Livewire::test(self::COMPONENT, ['order' => $this->order()])
            ->assertSee('Indodana')
            ->assertSet('payment_method', 'gopay');
    }

    public function test_bayar_indodana_mengarahkan_ke_halaman_nicepay(): void
    {
        $order = $this->order();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->set('payment_method', PaymentMethods::INDODANA)
            ->call('pay')
            ->assertHasNoErrors()
            ->assertNotDispatched('snap-pay')
            ->assertRedirect(route('payments.nicepay.pay', ['order' => $order->uuid]));

        $this->assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'payment_method' => PaymentMethods::INDODANA,
            'status' => PaymentAttemptStatus::PENDING->value,
        ]);
    }

    public function test_non_admin_tidak_bisa_memaksa_indodana_dalam_mode_admin(): void
    {
        config(['services.nicepay.admin_only' => true]);
        $order = $this->order();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->set('payment_method', PaymentMethods::INDODANA)
            ->call('pay')
            ->assertHasErrors(['payment_method']);

        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_lanjutkan_pembayaran_indodana_mengarahkan_ulang_tanpa_registration_baru(): void
    {
        $order = $this->order();
        $this->activeNicepayAttempt($order);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->call('continuePayment')
            ->assertRedirect(route('payments.nicepay.pay', ['order' => $order->uuid]));

        $this->assertSame(1, PaymentAttempt::query()->where('order_id', $order->id)->count());
        Http::assertNothingSent();
    }

    public function test_kartu_attempt_aktif_menampilkan_nama_indodana(): void
    {
        config(['services.nicepay.admin_only' => true]);
        $order = $this->order();
        $this->activeNicepayAttempt($order);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertSee('Indodana PayLater')
            ->assertSee('Tenor cicilan dipilih di halaman Indodana');
    }

    public function test_alur_midtrans_tidak_berubah_saat_nicepay_aktif(): void
    {
        $order = $this->order();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->set('payment_method', 'bni_va')
            ->call('pay')
            ->assertDispatched('snap-pay', token: 'snap-token-abc')
            ->assertNoRedirect();
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test --filter=NicepayInvoicePageTest`
Expected: FAIL pada `test_bayar_indodana_mengarahkan_ke_halaman_nicepay`, `test_lanjutkan_pembayaran_indodana_mengarahkan_ulang_tanpa_registration_baru`, dan `test_kartu_attempt_aktif_menampilkan_nama_indodana`. Tes lain di kelas ini sudah lolos berkat Task 8.

- [ ] **Step 3: Muat skill antislop**

Muat `antislop:antislop`, `antislop:antislop-ui`, `antislop:antislop-copywriting`, `antislop:antislop-human`, dan `antislop:antislop-layoutmobile`. Terapkan pada teks dan tampilan yang ditambah di Step 4 dan Step 5. Teks yang dipakai tes (`Indodana PayLater`, `Tenor cicilan dipilih di halaman Indodana`) boleh diubah hanya bersama pemeriksaan tesnya.

- [ ] **Step 4: Redirect untuk attempt Nicepay**

Di blok PHP `resources/views/pages/storefront/⚡order-success.blade.php`, ganti method `startPayment()` menjadi:

```php
    protected function startPayment(PaymentAttemptService $service, string $method): void
    {
        try {
            $attempt = Cache::lock('order-pay:'.$this->order->id, 10)
                ->block(5, fn () => $service->createOrReuseActiveAttempt($this->order, $method));
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->order->refresh();

        if ($attempt->provider === PaymentMethods::NICEPAY) {
            $this->redirect(route('payments.nicepay.pay', ['order' => $this->order->uuid]));

            return;
        }

        if (blank($attempt->snap_token)) {
            Flux::toast(variant: 'danger', text: 'Gagal memuat halaman pembayaran. Coba lagi.');

            return;
        }

        $this->dispatch('snap-pay', token: $attempt->snap_token);
    }
```

- [ ] **Step 5: Nama metode dan keterangan tenor di kartu attempt aktif**

Di bagian HTML file yang sama:

1. Di kartu "Metode Pembayaran Aktif", ganti isi baris "Metode":

```blade
                                    <span class="font-medium text-ink">{{ $this->methods()[$attempt->payment_method] ?? strtoupper($attempt->payment_method) }}</span>
```

menjadi:

```blade
                                    <span class="font-medium text-ink">{{ \App\Services\Payments\PaymentMethods::name($attempt->payment_method) }}</span>
```

Alasannya: dalam mode khusus admin, `methods()` tidak memuat Indodana untuk pelanggan biasa, sehingga pelanggan yang membuka pesanan dengan attempt Indodana aktif akan melihat `INDODANA` alih-alih nama metodenya.

2. Tepat setelah `</div>` penutup baris "Metode" itu, tambahkan:

```blade
                                @if ($attempt->provider === \App\Services\Payments\PaymentMethods::NICEPAY)
                                    <p class="text-xs text-ink/55">Tenor cicilan dipilih di halaman Indodana.</p>
                                @endif
```

3. Di kartu "Ganti Metode Pembayaran", ganti:

```blade
                                    Memilih metode baru akan membatalkan tagihan {{ $this->methods()[$attempt->payment_method] ?? '' }} yang lama.
```

menjadi:

```blade
                                    Memilih metode baru akan membatalkan tagihan {{ \App\Services\Payments\PaymentMethods::name($attempt->payment_method) }} yang lama.
```

Kisi pilihan metode tidak perlu diubah: kartu Indodana muncul sendiri dari `paymentMethods()`.

- [ ] **Step 6: Periksa kontras warna merek Indodana**

Jalankan alat `mcp__plugin_antislop_antislop-contrast__check_contrast` untuk teks `#ffffff` di atas latar `#1f7a00` (label kartu memakai teks putih tebal di atas warna merek). Bila rasio di bawah 4,5:1, gelapkan warna di `PaymentMethods::METHODS[self::INDODANA]['brand']` sampai lolos dan pakai nilai itu.

- [ ] **Step 7: Jalankan tes**

Run: `php artisan test --filter="NicepayInvoicePageTest|MidtransInvoicePageTest"`
Expected: PASS. `MidtransInvoicePageTest` lolos tanpa diubah.

Run: `php artisan test`
Expected: PASS seluruh suite.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add "resources/views/pages/storefront/⚡order-success.blade.php" app/Services/Payments/PaymentMethods.php tests/Feature/Payments/NicepayInvoicePageTest.php
git commit -m "feat(checkout): pilihan Indodana PayLater di halaman invoice"
```

---

### Task 10: Verifikasi dan peluncuran

**Files:**
- Modify: `specs/2026-10-02-nicepay-indodana-design.md` (bagian 13, hasil verifikasi)

**Interfaces:**
- Consumes: seluruh hasil Task 1 sampai Task 9.
- Produces: fitur aktif untuk semua pelanggan, dan spec yang mencatat jawaban tujuh pertanyaan verifikasi.

Langkah 4 sampai 7 menyentuh server produksi dan uang sungguhan. Semuanya dijalankan oleh pemilik toko, atau atas instruksi eksplisit pemilik toko pada saat itu.

- [ ] **Step 1: Seluruh tes dan gaya kode**

Run: `php artisan test`
Expected: PASS seluruh suite.

Run: `vendor/bin/pint --test`
Expected: tidak ada file yang perlu diformat.

- [ ] **Step 2: Audit aturan komentar `CLAUDE.md`**

Run:

```bash
git diff main --name-only -- '*.php' | xargs grep -nE '^\s*//' || true
```

Expected: yang tersisa hanya komentar penjelas konfigurasi di `bootstrap/app.php` (alias middleware, trust proxies, pengecualian CSRF) dan komentar JavaScript `// QRIS (GoPay Dynamic)...` serta `// Diabaikan Snap untuk kanal non-GoPay.` di `⚡order-success.blade.php`. Baris lain dihapus atau dijadikan PHPDoc bila menjelaskan logika yang tidak jelas.

Run:

```bash
git diff main --name-only -- '*.blade.php' | xargs grep -n '{{--' || true
```

Expected: tidak ada hasil.

- [ ] **Step 3: Uji asap ke lingkungan development Nicepay**

1. Di `.env` lokal, isi: `NICEPAY_ENABLED=true`, `NICEPAY_ADMIN_ONLY=true`, `NICEPAY_IS_PRODUCTION=false`, `NICEPAY_IMID=IONPAYTEST`, dan `NICEPAY_MERCHANT_KEY` dengan kunci uji publik dari https://docs.nicepay.co.id/nicepay-api-non-snap-authentication. Isi juga `NICEPAY_STORE_CITY`, `NICEPAY_STORE_STATE`, `NICEPAY_STORE_POSTCODE`.
2. Run: `php artisan migrate` lalu `php artisan config:clear`.
3. Masuk sebagai admin, buat pesanan dengan total di atas Rp10.000, pilih Indodana, tekan Bayar.
4. Catat hasilnya:
   - Bila browser sampai ke halaman Indodana atau Nicepay: Registration dan langkah Payment diterima.
   - Bila muncul "Gagal memulai pembayaran": baca `resultCd` dan `resultMsg` di `storage/logs/laravel.log`. Penolakan karena akun uji tidak mengaktifkan Indodana adalah hasil yang diharapkan dan bukan penghalang. Penolakan karena format field atau token harus diperbaiki sebelum lanjut.
5. Kembalikan `.env` lokal ke `NICEPAY_ENABLED=false`.

- [ ] **Step 4: Rilis ke produksi dengan fitur khusus admin**

1. Di `.env` produksi: `NICEPAY_ENABLED=true`, `NICEPAY_ADMIN_ONLY=true`, `NICEPAY_IS_PRODUCTION=true`, `NICEPAY_IMID` dan `NICEPAY_MERCHANT_KEY` produksi, serta kota, provinsi, dan kode pos toko.
2. Run di server: `php artisan migrate --force`, `npm run build` (atau unggah `public/build` hasil build; halaman redirect memakai kelas Tailwind yang belum ada di build lama), lalu `php artisan config:cache`.
3. Pastikan cron `schedule:run` berjalan (rekonsiliasi kini bernama `payments:reconcile`).

- [ ] **Step 5: Satu transaksi sungguhan oleh admin**

1. Masuk sebagai admin, buat satu pesanan kecil (minimal Rp10.000), pilih Indodana, selesaikan pembayaran di Indodana.
2. Periksa dan catat di bagian 13 spec:

| Pertanyaan | Cara memeriksa |
|---|---|
| Apakah `instmntMon` mengikat? | Apakah halaman Indodana menawarkan tenor selain yang kita kirim |
| Format ongkir dan diskon di `cartData` | Apakah Registration diterima untuk pesanan yang punya ongkir; rincian di halaman Indodana |
| Langkah Payment: POST atau GET | Apakah form POST membuka halaman Indodana |
| Balasan notifikasi | Baris baru di `payment_webhook_events` dengan `provider = nicepay`; apakah Nicepay mengirim ulang notifikasi yang sama |
| Zona waktu `timeStamp` | Tidak ada penolakan token atau waktu di log |
| `cancelType` untuk transaksi belum dibayar | Buat pesanan kedua, pilih Indodana, lalu ganti ke VA; lihat respons cancel di log |
| `userIP` untuk IPv6 | Ulangi dari jaringan seluler yang memakai IPv6 bila tersedia |

3. Pastikan pesanan berstatus lunas dan aktivitasnya mencatat aktor `Indodana via Nicepay (otomatis)`.
4. Void transaksi uji lewat back office Nicepay.

- [ ] **Step 6: Sesuaikan bila ada asumsi yang salah**

Setiap penyesuaian ditulis dengan tesnya lebih dulu dan di-commit terpisah.

| Temuan | Perubahan |
|---|---|
| `instmntMon` mengikat | Pemilih tenor di halaman invoice. Ini cakupan baru: kembali ke brainstorming untuk spec singkat sebelum dikerjakan. |
| Baris `shippingfee` atau `discount` ditolak | Di `NicepayRegistrationPayload::cart()`, selalu kembalikan satu baris senilai total; sesuaikan `test_keranjang_memuat_ongkir_dan_diskon_dan_jumlahnya_sama_dengan_total`. |
| Langkah Payment harus GET | Di `NicepayPaymentController::redirect()`, ganti view dengan `redirect()->away($client->paymentUrl().'?'.http_build_query($client->paymentFormFields($attempt)))`; sesuaikan tes halaman bayar. |
| Nicepay mengharapkan isi balasan tertentu | Ganti isi `response('OK', 200)` di `NicepayNotificationController`. |
| Zona waktu `timeStamp` bukan WIB | Ganti zona di `NicepayClient::timestamp()` dan tesnya. |
| Cancel untuk transaksi belum dibayar ditolak | Tidak perlu perubahan kode bila hanya ditolak (sudah dibungkus `rescue`); bila kode `cancelType` berbeda, ganti nilainya di `NicepayClient::cancel()`. |
| `127.0.0.1` ditolak untuk IPv6 | Di `NicepayRegistrationPayload::userIp()`, kirim `request()->server('SERVER_ADDR')` sebagai cadangan. |

- [ ] **Step 7: Buka untuk semua pelanggan**

Di `.env` produksi: `NICEPAY_ADMIN_ONLY=false`, lalu `php artisan config:cache`. Buka halaman invoice sebagai tamu dan pastikan kartu Indodana tampil.

- [ ] **Step 8: Catat hasil dan perbarui graph**

1. Di `specs/2026-10-02-nicepay-indodana-design.md`, ubah baris status menjadi `Status: diterapkan` dan isi kolom jawaban di bagian 13.
2. Jalankan `/graphify . --update` supaya knowledge graph memuat kelas pembayaran yang baru.
3. Commit:

```bash
git add specs/2026-10-02-nicepay-indodana-design.md
git commit -m "docs(spec): catat hasil verifikasi Indodana di produksi"
```
