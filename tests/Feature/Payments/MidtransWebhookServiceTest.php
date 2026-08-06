<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\Midtrans\MidtransWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MidtransWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private MidtransWebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.is_production' => false,
        ]);

        Http::fake(['*/v2/*/cancel' => Http::response(['status_code' => '200'], 200)]);

        $this->service = app(MidtransWebhookService::class);
    }

    private function attemptFor(Order $order, int $seq = 1, string $method = 'bni_va', PaymentAttemptStatus $status = PaymentAttemptStatus::PENDING): PaymentAttempt
    {
        return PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'attempt_sequence' => $seq,
            'midtrans_order_id' => $order->order_number.'-A'.$seq,
            'payment_method' => $method,
            'status' => $status,
            'gross_amount' => $order->grand_total,
        ]);
    }

    private function notification(PaymentAttempt $attempt, string $transactionStatus, array $overrides = []): array
    {
        $payload = array_merge([
            'order_id' => $attempt->midtrans_order_id,
            'transaction_id' => (string) Str::uuid(),
            'transaction_status' => $transactionStatus,
            'status_code' => '200',
            'gross_amount' => number_format((int) $attempt->order->grand_total, 2, '.', ''),
            'fraud_status' => 'accept',
        ], $overrides);

        if (! isset($payload['signature_key'])) {
            $payload['signature_key'] = hash('sha512',
                $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('services.midtrans.server_key')
            );
        }

        return $payload;
    }

    public function test_signature_tidak_valid_ditolak(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->attemptFor($order);

        $payload = $this->notification($attempt, 'settlement', ['signature_key' => 'palsu']);

        try {
            $this->service->handle($payload);
            $this->fail('Seharusnya melempar InvalidWebhookSignatureException.');
        } catch (InvalidWebhookSignatureException) {
            // diharapkan: signature tidak valid ditolak.
        }

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'midtrans_order_id' => $attempt->midtrans_order_id,
            'processing_status' => 'invalid_signature',
        ]);
    }

    public function test_attempt_tidak_dikenal_diabaikan(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->attemptFor($order);

        // order_id valid signaturenya, tapi tidak ada attempt yang cocok di DB.
        $payload = $this->notification($attempt, 'settlement', ['order_id' => 'TIDAK-ADA-A9']);
        // signature mengikuti order_id baru.
        $payload['signature_key'] = hash('sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('services.midtrans.server_key')
        );

        $this->service->handle($payload);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'midtrans_order_id' => 'TIDAK-ADA-A9',
            'processing_status' => 'ignored',
        ]);
    }

    public function test_expire_menandai_attempt_expired(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->attemptFor($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->handle($this->notification($attempt, 'expire', ['fraud_status' => null]));

        $attempt->refresh();
        $this->assertSame(PaymentAttemptStatus::EXPIRED, $attempt->status);
        $this->assertNotNull($attempt->expired_at);
        // Order tetap PENDING agar user bisa retry / ganti metode.
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_deny_menandai_attempt_denied(): void
    {
        $order = Order::factory()->create();
        $attempt = $this->attemptFor($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->handle($this->notification($attempt, 'deny', ['fraud_status' => 'deny']));

        $this->assertSame(PaymentAttemptStatus::DENIED, $attempt->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_pemulihan_idempotensi_saat_percobaan_sebelumnya_gagal(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->attemptFor($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $payload = $this->notification($attempt, 'settlement');

        // Simulasikan percobaan sebelumnya yang crash setelah event tercatat namun
        // sebelum sempat diproses: event ada dengan status 'received'.
        PaymentWebhookEvent::query()->create([
            'event_hash' => hash('sha256', implode('|', [
                $payload['order_id'], $payload['transaction_id'], $payload['transaction_status'],
                $payload['status_code'], $payload['gross_amount'], $payload['signature_key'],
            ])),
            'midtrans_order_id' => $payload['order_id'],
            'transaction_id' => $payload['transaction_id'],
            'transaction_status' => $payload['transaction_status'],
            'status_code' => $payload['status_code'],
            'gross_amount' => $payload['gross_amount'],
            'signature_key' => $payload['signature_key'],
            'payload' => $payload,
            'processing_status' => 'received',
        ]);

        // Retry dari Midtrans: harus DIPROSES (bukan di-skip), order menjadi lunas.
        $this->service->handle($payload);

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame('processed', PaymentWebhookEvent::query()->first()->processing_status);
    }

    public function test_settlement_melunasi_order(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['grand_total' => 220000]);
        $attempt = $this->attemptFor($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->handle($this->notification($attempt, 'settlement'));

        $order->refresh();
        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(PaymentAttemptStatus::PAID, $attempt->fresh()->status);
        Queue::assertPushed(SendBrevoEmail::class, 1);
    }

    public function test_notifikasi_paid_duplikat_diabaikan(): void
    {
        Queue::fake();

        $order = Order::factory()->create();
        $attempt = $this->attemptFor($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $payload = $this->notification($attempt, 'settlement');
        $this->service->handle($payload);
        $this->service->handle($payload); // persis sama -> dedup oleh event_hash

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        Queue::assertPushed(SendBrevoEmail::class, 1);
    }

    public function test_expire_setelah_lunas_tidak_menurunkan_status(): void
    {
        $order = Order::factory()->paid()->create();
        $attempt = $this->attemptFor($order, status: PaymentAttemptStatus::PAID);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->handle($this->notification($attempt, 'expire', ['fraud_status' => null]));

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_paid_dari_attempt_non_aktif_tetap_melunasi_dan_supersede_lainnya(): void
    {
        Queue::fake();

        $order = Order::factory()->create();
        $a1 = $this->attemptFor($order, seq: 1, method: 'bni_va', status: PaymentAttemptStatus::SUPERSEDED);
        $a2 = $this->attemptFor($order, seq: 2, method: 'bri_va', status: PaymentAttemptStatus::PENDING);
        $order->update(['active_payment_attempt_id' => $a2->id]);

        // User ternyata membayar attempt lama (A1) yang sudah superseded.
        $this->service->handle($this->notification($a1, 'settlement'));

        $order->refresh();
        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertSame(PaymentAttemptStatus::PAID, $a1->fresh()->status);
        $this->assertSame($a1->id, $order->active_payment_attempt_id);
        // Attempt lain yang masih hidup dibatalkan (superseded).
        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $a2->fresh()->status);
    }

    /**
     * Mandiri Bill Payment (echannel) tidak mengirim va_numbers, melainkan
     * biller_code + bill_key. Keduanya harus tersimpan dan terbaca untuk invoice.
     */
    public function test_echannel_menyimpan_biller_code_dan_bill_key(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['grand_total' => 310000]);
        $attempt = $this->attemptFor($order, method: 'echannel');
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->handle($this->notification($attempt, 'settlement', [
            'payment_type' => 'echannel',
            'biller_code' => '70012',
            'bill_key' => '990000000260',
        ]));

        $attempt->refresh();
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame('70012', $attempt->billerCode());
        $this->assertSame('990000000260', $attempt->billKey());
        // echannel tidak punya nomor VA - invoice harus jatuh ke cabang bill key.
        $this->assertNull($attempt->vaNumber());
    }

    /**
     * QRIS GoPay Dynamic: payment_type datang sebagai 'qris' (bukan 'gopay')
     * dengan acquirer + transaction_type, dan tanpa fraud_status.
     */
    public function test_qris_gopay_melunasi_meski_payment_type_qris_tanpa_fraud_status(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['grand_total' => 95000]);
        $attempt = $this->attemptFor($order, method: 'gopay');
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->handle($this->notification($attempt, 'settlement', [
            'payment_type' => 'qris',
            'acquirer' => 'gopay',
            'transaction_type' => 'on-us',
            'fraud_status' => null,
        ]));

        $attempt->refresh();
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::PAID, $attempt->status);
        $this->assertNull($attempt->vaNumber());
        $this->assertNull($attempt->billKey());
    }

    public function test_bsi_va_menyimpan_nomor_va_dan_label_bank(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['grand_total' => 480000]);
        $attempt = $this->attemptFor($order, method: 'bsi_va');
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->handle($this->notification($attempt, 'settlement', [
            'payment_type' => 'bank_transfer',
            'va_numbers' => [['bank' => 'bsi', 'va_number' => '9001234567890']],
        ]));

        $attempt->refresh();
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame('9001234567890', $attempt->vaNumber());
        $this->assertSame('BSI', $attempt->bankLabel());
    }

    public function test_permata_va_terbaca_dari_field_khususnya(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['grand_total' => 275000]);
        $attempt = $this->attemptFor($order, method: 'permata_va');
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $this->service->handle($this->notification($attempt, 'settlement', [
            'payment_type' => 'bank_transfer',
            'permata_va_number' => '8562000012345678',
        ]));

        $attempt->refresh();
        $this->assertSame('8562000012345678', $attempt->vaNumber());
        $this->assertSame('PERMATA', $attempt->bankLabel());
    }

    public function test_gross_amount_tidak_cocok_tidak_melunasi(): void
    {
        $order = Order::factory()->create(['grand_total' => 220000]);
        $attempt = $this->attemptFor($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $payload = $this->notification($attempt, 'settlement', ['gross_amount' => '999999.00']);

        $this->service->handle($payload);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', ['processing_status' => 'ignored']);
    }
}
