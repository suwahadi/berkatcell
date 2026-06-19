# Plan Implementation Payment Gateway Midtrans Snap Mode di Laravel 13

Dokumen ini adalah rencana implementasi **Midtrans Snap Mode** untuk Laravel 13 dengan fokus pada:

- arsitektur service layer, bukan logic di Livewire/frontend;
- idempotency saat create payment;
- aman dari multi retry webhook Midtrans;
- aman saat user mengganti metode pembayaran setelah invoice/payment dibuat;
- webhook public via `web.php`, tanpa masalah CORS;
- state machine yang tidak mudah rusak hanya karena user klik tombol seperti sedang main whack-a-mole.

Referensi resmi Midtrans yang menjadi dasar:

- Snap Integration Guide: https://docs.midtrans.com/docs/snap-snap-integration-guide
- Snap Advanced Feature - Multiple Payment Attempts: https://docs.midtrans.com/docs/snap-advanced-feature
- HTTP(S) Notification / Webhooks: https://docs.midtrans.com/docs/https-notification-webhooks
- Transaction Status Cycle: https://docs.midtrans.com/docs/transaction-status-cycle
- GET Status API Requests: https://docs.midtrans.com/docs/get-status-api-requests
- Access Keys: https://docs.midtrans.com/docs/access-keys

---

## 1. Prinsip Utama

### 1.1 Snap token dibuat dari backend

Midtrans Snap mengharuskan request token transaksi dilakukan dari backend menggunakan **Server Key**. Frontend, termasuk Livewire component, hanya boleh menerima hasil final seperti `snap_token` atau `redirect_url`, bukan menyusun payload transaksi sendiri.

### 1.2 `order_id` Midtrans harus unik

Setiap transaksi Snap membutuhkan `order_id` unik. Karena fitur ini butuh user bisa mengganti metode pembayaran, jangan gunakan `orders.id` langsung sebagai `order_id` Midtrans.

Gunakan format:

```text
INV-{invoice_number}-A{attempt_sequence}
```

Contoh:

```text
INV-20260604-0001-A1
INV-20260604-0001-A2
INV-20260604-0001-A3
```

`invoice_number` tetap sama untuk invoice internal, tetapi `midtrans_order_id` berbeda untuk setiap attempt pembayaran.

### 1.3 Satu invoice bisa punya banyak payment attempt

Struktur aman:

```text
orders / invoices
  └── payment_attempts
        ├── attempt A1: BNI VA, superseded/cancelled
        ├── attempt A2: BRI VA, active/pending
        └── attempt A3: QRIS, active/pending
```

Yang dianggap valid oleh sistem adalah attempt dengan:

```text
payment_attempts.id = invoices.active_payment_attempt_id
```

Jadi saat webhook lama datang dari attempt A1, sistem tetap menerima request HTTP-nya, tetapi tidak mengubah invoice menjadi paid kalau A1 sudah bukan active attempt.

### 1.4 Jangan percaya webhook begitu saja

Webhook Midtrans bisa terkirim lebih dari sekali dan bisa datang dari attempt lama. Handler harus:

1. validasi signature;
2. simpan raw notification;
3. deduplicate event;
4. lock invoice/attempt dalam database transaction;
5. proses hanya jika attempt masih aktif;
6. tidak downgrade status invoice yang sudah final.

---

## 2. Target Flow

### 2.1 Create invoice pertama kali

```text
User checkout
  → Backend membuat invoice internal
  → Backend membuat payment_attempt A1
  → Backend request Snap token ke Midtrans
  → Simpan snap_token + redirect_url
  → User diarahkan ke halaman pembayaran / invoice detail
```

### 2.2 User memilih VA BNI

Ada dua opsi implementasi:

#### Opsi A - Snap umum

Backend generate Snap token dengan beberapa metode pembayaran aktif. User memilih metode di halaman Snap.

Kelebihan:

- paling sesuai dengan behavior default Snap;
- user bisa ganti metode langsung di Snap;
- lebih sedikit attempt internal.

Kekurangan:

- sistem internal tidak mengontrol pilihan awal BNI/BRI sebelum user memilih di Snap;
- UI invoice tidak bisa memaksa “VA BNI saja” kecuali menggunakan konfigurasi `enabled_payments`.

#### Opsi B - Snap dibatasi per metode

Backend generate Snap token dengan `enabled_payments` sesuai pilihan user.

Contoh:

```php
'enabled_payments' => ['bni_va']
```

Jika user klik “Ganti metode pembayaran” ke BRI, sistem membuat attempt baru:

```php
'enabled_payments' => ['bri_va']
```

Opsi ini paling sesuai dengan kebutuhan:

> tahap pertama user pilih VA BNI, lalu di detail invoice dia ganti ke VA BRI, maka yang berlaku pilihan terakhir.

Rekomendasi dokumen ini memakai **Opsi B**.

---

## 3. Status Internal

### 3.1 Status invoice

Gunakan enum/string:

```text
invoice.status:
- draft
- waiting_payment
- paid
- expired
- cancelled
- failed
- refund_required
```

### 3.2 Status payment attempt

```text
payment_attempts.status:
- creating
- pending
- paid
- denied
- expired
- cancelled
- superseded
- failed
```

### 3.3 Status webhook event

```text
payment_webhook_events.processing_status:
- received
- processed
- ignored
- invalid_signature
- failed
```

---

## 4. Database Design

### 4.1 Tabel `invoices`

Field penting:

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->unsignedBigInteger('amount');
    $table->string('currency', 3)->default('IDR');
    $table->string('status')->default('draft');
    $table->foreignId('active_payment_attempt_id')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```

> Catatan: foreign key `active_payment_attempt_id` bisa ditambahkan setelah tabel `payment_attempts` dibuat untuk menghindari circular migration. Ya, database juga punya drama struktural.

### 4.2 Tabel `payment_attempts`

```php
Schema::create('payment_attempts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('attempt_sequence');
    $table->string('midtrans_order_id')->unique();
    $table->string('payment_method')->nullable(); // bni_va, bri_va, qris, gopay, dll
    $table->string('status')->default('creating');
    $table->string('snap_token')->nullable();
    $table->text('redirect_url')->nullable();
    $table->string('midtrans_transaction_id')->nullable()->index();
    $table->string('midtrans_transaction_status')->nullable();
    $table->string('midtrans_fraud_status')->nullable();
    $table->json('snap_request_payload')->nullable();
    $table->json('snap_response_payload')->nullable();
    $table->json('latest_notification_payload')->nullable();
    $table->timestamp('activated_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('expired_at')->nullable();
    $table->timestamps();

    $table->unique(['invoice_id', 'attempt_sequence']);
});
```

### 4.3 Tabel `payment_webhook_events`

```php
Schema::create('payment_webhook_events', function (Blueprint $table) {
    $table->id();
    $table->string('midtrans_order_id')->index();
    $table->string('transaction_id')->nullable()->index();
    $table->string('transaction_status')->nullable();
    $table->string('status_code')->nullable();
    $table->string('gross_amount')->nullable();
    $table->string('signature_key')->nullable();
    $table->string('event_hash')->unique();
    $table->string('processing_status')->default('received');
    $table->json('payload');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### 4.4 Event hash untuk deduplication

Buat hash dari kombinasi:

```text
midtrans_order_id|transaction_id|transaction_status|status_code|gross_amount|signature_key
```

Contoh:

```php
$eventHash = hash('sha256', implode('|', [
    $payload['order_id'] ?? '',
    $payload['transaction_id'] ?? '',
    $payload['transaction_status'] ?? '',
    $payload['status_code'] ?? '',
    $payload['gross_amount'] ?? '',
    $payload['signature_key'] ?? '',
]));
```

---

## 5. ENV Configuration

Tambahkan ke `.env`:

```env
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_MERCHANT_ID=G123456789
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
MIDTRANS_SANITIZE=true
MIDTRANS_3DS=true

MIDTRANS_SNAP_SANDBOX_URL=https://app.sandbox.midtrans.com/snap/v1/transactions
MIDTRANS_SNAP_PRODUCTION_URL=https://app.midtrans.com/snap/v1/transactions

MIDTRANS_API_SANDBOX_URL=https://api.sandbox.midtrans.com
MIDTRANS_API_PRODUCTION_URL=https://api.midtrans.com

MIDTRANS_NOTIFICATION_PATH=/payments/midtrans/notification
MIDTRANS_FINISH_REDIRECT=/payments/midtrans/finish
MIDTRANS_UNFINISH_REDIRECT=/payments/midtrans/unfinish
MIDTRANS_ERROR_REDIRECT=/payments/midtrans/error
```

Tambahkan ke `config/services.php`:

```php
'midtrans' => [
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'sanitize' => env('MIDTRANS_SANITIZE', true),
    'three_ds' => env('MIDTRANS_3DS', true),

    'snap_sandbox_url' => env('MIDTRANS_SNAP_SANDBOX_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions'),
    'snap_production_url' => env('MIDTRANS_SNAP_PRODUCTION_URL', 'https://app.midtrans.com/snap/v1/transactions'),

    'api_sandbox_url' => env('MIDTRANS_API_SANDBOX_URL', 'https://api.sandbox.midtrans.com'),
    'api_production_url' => env('MIDTRANS_API_PRODUCTION_URL', 'https://api.midtrans.com'),
];
```

---

## 6. Route Design via `web.php`

Gunakan `routes/web.php`, bukan `api.php`, supaya lebih mudah manage middleware dan URL di aplikasi biasa.

```php
use App\Http\Controllers\Payment\MidtransNotificationController;
use App\Http\Controllers\Payment\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/invoices/{invoice}', [PaymentController::class, 'show'])
        ->name('invoices.show');

    Route::post('/invoices/{invoice}/payments/midtrans/create', [PaymentController::class, 'createMidtransPayment'])
        ->name('invoices.payments.midtrans.create');

    Route::post('/invoices/{invoice}/payments/midtrans/change-method', [PaymentController::class, 'changeMidtransMethod'])
        ->name('invoices.payments.midtrans.change-method');
});

Route::post('/payments/midtrans/notification', MidtransNotificationController::class)
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ])
    ->name('payments.midtrans.notification');

Route::get('/payments/midtrans/finish', [PaymentController::class, 'finish'])
    ->name('payments.midtrans.finish');

Route::get('/payments/midtrans/unfinish', [PaymentController::class, 'unfinish'])
    ->name('payments.midtrans.unfinish');

Route::get('/payments/midtrans/error', [PaymentController::class, 'error'])
    ->name('payments.midtrans.error');
```

### 6.1 Kenapa webhook tidak butuh CORS?

CORS hanya relevan untuk browser. Webhook Midtrans adalah server-to-server POST request. Jadi tidak perlu menambahkan CORS header aneh-aneh. Yang penting:

- route public bisa diakses internet;
- tidak dilindungi login/auth;
- tidak terkena CSRF;
- tidak memakai port aneh;
- direkomendasikan HTTPS;
- tidak diblokir firewall/CDN.

---

## 7. Setup Webhook di Midtrans Dashboard

Di Midtrans MAP/Dashboard:

```text
Settings → Configuration
```

Isi:

```text
Payment Notification URL:
https://domain.com/payments/midtrans/notification

Finish Redirect URL:
https://domain.com/payments/midtrans/finish

Unfinished Redirect URL:
https://domain.com/payments/midtrans/unfinish

Error Redirect URL:
https://domain.com/payments/midtrans/error
```

Untuk local development gunakan tunneling public seperti:

```text
https://xxxx.ngrok-free.app/payments/midtrans/notification
```

Jangan pakai localhost, karena Midtrans tidak bisa mengirim webhook ke komputer lokal yang sedang bersembunyi di balik NAT seperti makhluk goa.

---

## 8. Service Layer Structure

Direktori rekomendasi:

```text
app/
  Services/
    Payments/
      Midtrans/
        MidtransClient.php
        MidtransSnapService.php
        MidtransPaymentAttemptService.php
        MidtransWebhookService.php
        MidtransSignatureVerifier.php
        MidtransStatusMapper.php
```

Controller hanya memanggil service. Semua logic utama ada di service layer.

---

## 9. Midtrans Client

```php
namespace App\Services\Payments\Midtrans;

use Illuminate\Support\Facades\Http;

class MidtransClient
{
    public function snapUrl(): string
    {
        return config('services.midtrans.is_production')
            ? config('services.midtrans.snap_production_url')
            : config('services.midtrans.snap_sandbox_url');
    }

    public function apiBaseUrl(): string
    {
        return config('services.midtrans.is_production')
            ? config('services.midtrans.api_production_url')
            : config('services.midtrans.api_sandbox_url');
    }

    public function authHeader(): string
    {
        return 'Basic ' . base64_encode(config('services.midtrans.server_key') . ':');
    }

    public function createSnapTransaction(array $payload): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->post($this->snapUrl(), $payload);

        $response->throw();

        return $response->json();
    }

    public function cancel(string $midtransOrderId): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->post($this->apiBaseUrl() . '/v2/' . urlencode($midtransOrderId) . '/cancel');

        // Cancel bisa gagal jika transaksi sudah settlement/expired/not found.
        // Jangan langsung throw kecuali memang ingin menghentikan flow.
        return $response->json() ?? [
            'http_status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    public function status(string $midtransOrderId): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->get($this->apiBaseUrl() . '/v2/' . urlencode($midtransOrderId) . '/status');

        return $response->json() ?? [
            'http_status' => $response->status(),
            'body' => $response->body(),
        ];
    }
}
```

---

## 10. Signature Verification

Midtrans notification memiliki `signature_key` yang dibuat dari:

```text
SHA512(order_id + status_code + gross_amount + server_key)
```

Implementasi:

```php
namespace App\Services\Payments\Midtrans;

class MidtransSignatureVerifier
{
    public function isValid(array $payload): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        $serverKey = config('services.midtrans.server_key');

        $expected = hash('sha512',
            $payload['order_id'] .
            $payload['status_code'] .
            $payload['gross_amount'] .
            $serverKey
        );

        return hash_equals($expected, $payload['signature_key']);
    }
}
```

---

## 11. Create Payment Attempt Service

```php
namespace App\Services\Payments\Midtrans;

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MidtransPaymentAttemptService
{
    public function __construct(
        private MidtransClient $client,
    ) {}

    public function createOrReuseActiveAttempt(Invoice $invoice, string $paymentMethod): PaymentAttempt
    {
        return DB::transaction(function () use ($invoice, $paymentMethod) {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->status === 'paid') {
                throw new \RuntimeException('Invoice sudah paid. Tidak bisa membuat payment baru.');
            }

            $activeAttempt = $lockedInvoice->activePaymentAttempt;

            if (
                $activeAttempt &&
                $activeAttempt->payment_method === $paymentMethod &&
                in_array($activeAttempt->status, ['creating', 'pending'], true)
            ) {
                return $activeAttempt;
            }

            if ($activeAttempt && in_array($activeAttempt->status, ['creating', 'pending'], true)) {
                $activeAttempt->update(['status' => 'superseded']);

                // Best effort cancel ke Midtrans.
                // Jika gagal, webhook lama tetap aman karena attempt ini bukan active attempt lagi.
                rescue(fn () => $this->client->cancel($activeAttempt->midtrans_order_id));
            }

            $nextSequence = PaymentAttempt::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->max('attempt_sequence') + 1;

            $midtransOrderId = $lockedInvoice->invoice_number . '-A' . $nextSequence;

            $attempt = PaymentAttempt::query()->create([
                'invoice_id' => $lockedInvoice->id,
                'attempt_sequence' => $nextSequence,
                'midtrans_order_id' => $midtransOrderId,
                'payment_method' => $paymentMethod,
                'status' => 'creating',
                'activated_at' => now(),
            ]);

            $payload = $this->buildSnapPayload($lockedInvoice, $attempt, $paymentMethod);
            $response = $this->client->createSnapTransaction($payload);

            $attempt->update([
                'status' => 'pending',
                'snap_token' => $response['token'] ?? null,
                'redirect_url' => $response['redirect_url'] ?? null,
                'snap_request_payload' => $payload,
                'snap_response_payload' => $response,
            ]);

            $lockedInvoice->update([
                'status' => 'waiting_payment',
                'active_payment_attempt_id' => $attempt->id,
            ]);

            return $attempt->refresh();
        });
    }

    private function buildSnapPayload(Invoice $invoice, PaymentAttempt $attempt, string $paymentMethod): array
    {
        return [
            'transaction_details' => [
                'order_id' => $attempt->midtrans_order_id,
                'gross_amount' => $invoice->amount,
            ],
            'enabled_payments' => [$paymentMethod],
            'customer_details' => [
                'first_name' => $invoice->user?->name ?? 'Customer',
                'email' => $invoice->user?->email,
            ],
            'callbacks' => [
                'finish' => route('payments.midtrans.finish'),
            ],
        ];
    }
}
```

### 11.1 Idempotency saat user double click

Karena `createOrReuseActiveAttempt()` memakai `lockForUpdate()`, double click dari user tidak membuat dua active attempt untuk metode yang sama. Kalau payment method sama, attempt lama direuse.

Untuk proteksi tambahan di controller, bisa pakai cache lock:

```php
Cache::lock('invoice-payment-create:' . $invoice->id, 10)->block(5, function () use ($invoice, $method) {
    return app(MidtransPaymentAttemptService::class)
        ->createOrReuseActiveAttempt($invoice, $method);
});
```

---

## 12. Change Payment Method Flow

Saat user klik tombol “Ganti Metode Pembayaran”:

```text
POST /invoices/{invoice}/payments/midtrans/change-method
```

Payload:

```json
{
  "payment_method": "bri_va"
}
```

Controller:

```php
public function changeMidtransMethod(Request $request, Invoice $invoice)
{
    $data = $request->validate([
        'payment_method' => ['required', 'string', 'in:bni_va,bri_va,bca_va,permata_va,gopay,qris'],
    ]);

    $attempt = Cache::lock('invoice-payment-change:' . $invoice->id, 10)->block(5, function () use ($invoice, $data) {
        return app(MidtransPaymentAttemptService::class)
            ->createOrReuseActiveAttempt($invoice, $data['payment_method']);
    });

    return response()->json([
        'snap_token' => $attempt->snap_token,
        'redirect_url' => $attempt->redirect_url,
        'payment_method' => $attempt->payment_method,
    ]);
}
```

### 12.1 Aturan penting

Jika invoice sudah `paid`, tombol “Ganti Metode Pembayaran” harus disembunyikan dan backend wajib tetap menolak request.

Jika attempt lama ternyata sudah dibayar setelah user mengganti metode, webhook attempt lama akan masuk. Sistem harus:

- mencatat event;
- menandai attempt lama sebagai `paid`, jika mau untuk audit;
- tidak mengubah invoice menjadi paid dari attempt lama jika bukan active attempt;
- set invoice ke `refund_required` jika dana benar-benar masuk dari attempt lama tetapi invoice sudah punya active attempt lain atau sudah paid oleh attempt lain.

Kasus ini jarang, tapi bukan mustahil. Payment system itu tempat “jarang” berubah jadi “kenapa ini terjadi jam 2 pagi”.

---

## 13. Webhook Service

```php
namespace App\Services\Payments\Midtrans;

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

class MidtransWebhookService
{
    public function __construct(
        private MidtransSignatureVerifier $signatureVerifier,
    ) {}

    public function handle(array $payload): void
    {
        $event = $this->storeEvent($payload);

        if (! $this->signatureVerifier->isValid($payload)) {
            $event->update([
                'processing_status' => 'invalid_signature',
                'notes' => 'Invalid Midtrans signature.',
            ]);
            return;
        }

        DB::transaction(function () use ($payload, $event) {
            $attempt = PaymentAttempt::query()
                ->where('midtrans_order_id', $payload['order_id'] ?? null)
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                $event->update([
                    'processing_status' => 'ignored',
                    'notes' => 'Payment attempt not found.',
                ]);
                return;
            }

            $invoice = Invoice::query()
                ->whereKey($attempt->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $transactionStatus = $payload['transaction_status'] ?? null;
            $fraudStatus = $payload['fraud_status'] ?? null;

            $attempt->update([
                'midtrans_transaction_id' => $payload['transaction_id'] ?? $attempt->midtrans_transaction_id,
                'midtrans_transaction_status' => $transactionStatus,
                'midtrans_fraud_status' => $fraudStatus,
                'latest_notification_payload' => $payload,
            ]);

            if ($this->isPaidStatus($transactionStatus, $fraudStatus)) {
                $this->handlePaid($invoice, $attempt, $event);
                return;
            }

            if ($transactionStatus === 'pending') {
                $this->handlePending($invoice, $attempt, $event);
                return;
            }

            if (in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)) {
                $this->handleFailedLikeStatus($invoice, $attempt, $event, $transactionStatus);
                return;
            }

            $event->update([
                'processing_status' => 'ignored',
                'notes' => 'Unhandled transaction_status: ' . $transactionStatus,
            ]);
        });
    }

    private function storeEvent(array $payload): PaymentWebhookEvent
    {
        $eventHash = hash('sha256', implode('|', [
            $payload['order_id'] ?? '',
            $payload['transaction_id'] ?? '',
            $payload['transaction_status'] ?? '',
            $payload['status_code'] ?? '',
            $payload['gross_amount'] ?? '',
            $payload['signature_key'] ?? '',
        ]));

        return PaymentWebhookEvent::query()->firstOrCreate(
            ['event_hash' => $eventHash],
            [
                'midtrans_order_id' => $payload['order_id'] ?? '',
                'transaction_id' => $payload['transaction_id'] ?? null,
                'transaction_status' => $payload['transaction_status'] ?? null,
                'status_code' => $payload['status_code'] ?? null,
                'gross_amount' => $payload['gross_amount'] ?? null,
                'signature_key' => $payload['signature_key'] ?? null,
                'payload' => $payload,
                'processing_status' => 'received',
            ]
        );
    }

    private function isPaidStatus(?string $transactionStatus, ?string $fraudStatus): bool
    {
        if ($transactionStatus === 'settlement') {
            return true;
        }

        if ($transactionStatus === 'capture') {
            return $fraudStatus === null || $fraudStatus === 'accept';
        }

        return false;
    }

    private function handlePaid(Invoice $invoice, PaymentAttempt $attempt, PaymentWebhookEvent $event): void
    {
        $attempt->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        if ((int) $invoice->active_payment_attempt_id !== (int) $attempt->id) {
            if ($invoice->status !== 'paid') {
                $invoice->update(['status' => 'refund_required']);
            }

            $event->update([
                'processing_status' => 'ignored',
                'notes' => 'Paid notification from non-active attempt. Marked for audit/refund handling.',
            ]);
            return;
        }

        if ($invoice->status === 'paid') {
            $event->update([
                'processing_status' => 'ignored',
                'notes' => 'Invoice already paid. Duplicate paid webhook ignored.',
            ]);
            return;
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $event->update([
            'processing_status' => 'processed',
            'notes' => 'Invoice marked as paid.',
        ]);
    }

    private function handlePending(Invoice $invoice, PaymentAttempt $attempt, PaymentWebhookEvent $event): void
    {
        if ((int) $invoice->active_payment_attempt_id !== (int) $attempt->id) {
            $event->update([
                'processing_status' => 'ignored',
                'notes' => 'Pending notification from non-active attempt ignored.',
            ]);
            return;
        }

        if ($invoice->status !== 'paid') {
            $invoice->update(['status' => 'waiting_payment']);
        }

        $attempt->update(['status' => 'pending']);

        $event->update([
            'processing_status' => 'processed',
            'notes' => 'Pending status processed.',
        ]);
    }

    private function handleFailedLikeStatus(
        Invoice $invoice,
        PaymentAttempt $attempt,
        PaymentWebhookEvent $event,
        string $transactionStatus
    ): void {
        $mappedStatus = match ($transactionStatus) {
            'expire' => 'expired',
            'cancel' => 'cancelled',
            'deny', 'failure' => 'denied',
            default => 'failed',
        };

        $attempt->update(['status' => $mappedStatus]);

        if ((int) $invoice->active_payment_attempt_id === (int) $attempt->id && $invoice->status !== 'paid') {
            $invoice->update([
                'status' => $transactionStatus === 'expire' ? 'expired' : 'failed',
            ]);
        }

        $event->update([
            'processing_status' => 'processed',
            'notes' => 'Failed-like status processed: ' . $transactionStatus,
        ]);
    }
}
```

---

## 14. Notification Controller

```php
namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payments\Midtrans\MidtransWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransWebhookService $service): Response
    {
        $service->handle($request->all());

        // Selalu balas 200 untuk payload yang berhasil diterima oleh server,
        // bahkan jika event di-ignore secara bisnis.
        // Jika server balas error, Midtrans bisa retry, lalu manusia panik melihat log dobel.
        return response('OK', 200);
    }
}
```

---

## 15. Payment Controller Ringkas

```php
namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    public function show(Invoice $invoice)
    {
        $invoice->load('activePaymentAttempt');

        return view('invoices.show', compact('invoice'));
    }

    public function createMidtransPayment(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:bni_va,bri_va,bca_va,permata_va,gopay,qris'],
        ]);

        $attempt = Cache::lock('invoice-payment-create:' . $invoice->id, 10)->block(5, function () use ($invoice, $data) {
            return app(MidtransPaymentAttemptService::class)
                ->createOrReuseActiveAttempt($invoice, $data['payment_method']);
        });

        return response()->json([
            'snap_token' => $attempt->snap_token,
            'redirect_url' => $attempt->redirect_url,
        ]);
    }

    public function changeMidtransMethod(Request $request, Invoice $invoice)
    {
        return $this->createMidtransPayment($request, $invoice);
    }

    public function finish(Request $request)
    {
        return redirect()->route('invoices.show', [
            'invoice' => $request->query('order_id'),
        ]);
    }

    public function unfinish()
    {
        return redirect()->route('dashboard')->with('status', 'Pembayaran belum selesai.');
    }

    public function error()
    {
        return redirect()->route('dashboard')->with('status', 'Pembayaran gagal atau dibatalkan.');
    }
}
```

> Catatan: method `finish()` di atas perlu disesuaikan karena `order_id` dari Midtrans adalah `midtrans_order_id`, bukan `invoice.id`. Idealnya cari `PaymentAttempt` berdasarkan `order_id`, lalu redirect ke invoice terkait.

---

## 16. Model Relationship

### 16.1 Invoice model

```php
public function paymentAttempts()
{
    return $this->hasMany(PaymentAttempt::class);
}

public function activePaymentAttempt()
{
    return $this->belongsTo(PaymentAttempt::class, 'active_payment_attempt_id');
}
```

### 16.2 PaymentAttempt model

```php
protected $casts = [
    'snap_request_payload' => 'array',
    'snap_response_payload' => 'array',
    'latest_notification_payload' => 'array',
    'activated_at' => 'datetime',
    'paid_at' => 'datetime',
    'expired_at' => 'datetime',
];

public function invoice()
{
    return $this->belongsTo(Invoice::class);
}
```

---

## 17. Frontend / Livewire Boundary

Livewire hanya boleh:

- menampilkan invoice;
- memanggil endpoint create/change payment;
- membuka Snap popup atau redirect URL;
- refresh status invoice.

Livewire tidak boleh:

- membuat payload Snap;
- menyimpan status payment final;
- memproses webhook;
- validasi signature;
- memutuskan invoice paid.

Contoh JS Snap popup:

```html
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>

<script>
async function pay(invoiceId, method) {
    const response = await fetch(`/invoices/${invoiceId}/payments/midtrans/create`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ payment_method: method })
    });

    const data = await response.json();

    window.snap.pay(data.snap_token, {
        onSuccess: function () {
            window.location.reload();
        },
        onPending: function () {
            window.location.reload();
        },
        onError: function () {
            window.location.reload();
        },
        onClose: function () {
            window.location.reload();
        }
    });
}
</script>
```

Production script:

```html
<script src="https://app.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
```

---

## 18. Payment Method Mapping

Internal method ke Midtrans `enabled_payments`:

```php
return [
    'bni_va' => 'bni_va',
    'bri_va' => 'bri_va',
    'bca_va' => 'bca_va',
    'permata_va' => 'permata_va',
    'gopay' => 'gopay',
    'qris' => 'qris',
];
```

Pastikan metode pembayaran sudah aktif di dashboard Midtrans. Kalau belum aktif, request tetap bisa gagal walaupun kode sudah benar. Kode tidak bisa menyogok dashboard, sayangnya.

---

## 19. Handling Retry Webhook

Midtrans dapat mengirim lebih dari satu notification untuk status transaksi. Sistem harus aman terhadap:

### 19.1 Duplicate exact webhook

Solusi:

- `payment_webhook_events.event_hash` unique;
- jika event sudah ada, boleh langsung return 200.

### 19.2 Webhook lama dari attempt yang sudah superseded

Solusi:

- cek `invoice.active_payment_attempt_id`;
- jika berbeda dari attempt webhook, jangan update invoice menjadi paid;
- catat sebagai ignored atau `refund_required` jika status paid.

### 19.3 Status downgrade

Contoh:

```text
invoice sudah paid
lalu webhook pending/expire datang belakangan
```

Solusi:

- invoice `paid` tidak boleh berubah menjadi `waiting_payment`, `expired`, atau `failed`;
- hanya proses refund/cancel manual jika memang dibutuhkan.

### 19.4 Race condition dua webhook bersamaan

Solusi:

- `DB::transaction()`;
- `lockForUpdate()` pada invoice dan payment attempt.

---

## 20. Reconciliation Job

Tambahkan scheduled command untuk memastikan status tidak bergantung 100% pada webhook.

```text
php artisan payments:midtrans:reconcile
```

Logic:

```text
Ambil payment_attempts status pending lebih dari X menit/jam
  → call GET /v2/{midtrans_order_id}/status
  → proses response menggunakan mapper yang sama dengan webhook
```

Jalankan scheduler:

```php
Schedule::command('payments:midtrans:reconcile')->everyFifteenMinutes();
```

Midtrans GET Status API memakai `order_id` atau `transaction_id`, dan tetap butuh Basic Auth memakai Server Key.

---

## 21. Testing Checklist

### 21.1 Sandbox setup

- Gunakan Sandbox Server Key dan Client Key.
- Gunakan URL sandbox Snap JS.
- Gunakan endpoint sandbox Snap transaction.
- Set Notification URL ke public tunnel/domain dev.

### 21.2 Test cases wajib

#### Case 1 - Create payment normal

```text
User pilih BNI VA
Expect:
- invoice waiting_payment
- payment_attempt A1 pending
- active_payment_attempt_id = A1
- snap_token tersimpan
```

#### Case 2 - Double click create payment

```text
User klik bayar 2x cepat
Expect:
- hanya satu active attempt untuk metode yang sama
- tidak ada duplicate midtrans_order_id
```

#### Case 3 - Change method BNI ke BRI

```text
A1 = bni_va
User ganti ke bri_va
Expect:
- A1 superseded/cancelled best effort
- A2 pending
- active_payment_attempt_id = A2
```

#### Case 4 - Webhook pending dari A1 setelah A2 aktif

```text
Expect:
- event tersimpan
- invoice tetap mengikuti A2
- A1 tidak mengubah invoice
```

#### Case 5 - Webhook paid dari A2

```text
Expect:
- A2 paid
- invoice paid
- paid_at terisi
```

#### Case 6 - Duplicate webhook paid dari A2

```text
Expect:
- tidak error
- tidak double fulfilment
- response tetap 200
```

#### Case 7 - Webhook expire setelah invoice paid

```text
Expect:
- invoice tetap paid
- event ignored/processed harmless
```

#### Case 8 - Paid webhook dari A1 yang sudah superseded

```text
Expect:
- invoice tidak otomatis paid dari A1 jika A1 bukan active attempt
- attempt A1 boleh ditandai paid untuk audit
- invoice masuk refund_required jika dana benar-benar masuk dan belum ada paid valid
```

---

## 22. Deployment Checklist

- `.env` production memakai Production Server Key dan Client Key.
- `MIDTRANS_IS_PRODUCTION=true`.
- Snap JS production dipakai.
- Payment Notification URL production sudah diisi di Midtrans Dashboard.
- Route webhook public, HTTPS, tidak auth, tidak CSRF.
- Firewall/CDN tidak block request Midtrans.
- Logging webhook aktif.
- Reconciliation command aktif di scheduler.
- Tombol ganti metode disembunyikan setelah invoice paid.
- Semua status final tidak bisa didowngrade.

---

## 23. Kesimpulan Arsitektur

Desain paling aman untuk kebutuhan ini adalah:

```text
Invoice internal tetap satu
Payment attempt boleh banyak
Setiap attempt punya midtrans_order_id unik
Hanya active attempt yang boleh mengubah invoice menjadi paid
Webhook lama tetap disimpan, tapi tidak dipercaya membabi buta
Attempt lama dicancel best effort saat user ganti metode
Reconciliation job disiapkan untuk backup jika webhook gagal
```

Dengan pola ini, user bebas mengganti metode pembayaran dari BNI VA ke BRI VA atau metode lain, dan sistem tetap aman dari retry webhook, double click, race condition, serta notifikasi lama yang datang terlambat seperti tamu yang baru muncul setelah acara selesai.
