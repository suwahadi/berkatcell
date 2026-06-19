<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use App\Services\SettingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MidtransPaymentAttemptServiceTest extends TestCase
{
    use RefreshDatabase;

    private MidtransPaymentAttemptService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.client_key' => 'test-client-key',
            'services.midtrans.is_production' => false,
        ]);

        // Hanya cancel yang di-fake global; snap di-fake per-test agar test kegagalan
        // bisa mendaftarkan stub 500 sebagai stub snap pertama (first-match-wins).
        Http::fake(['*/v2/*/cancel' => Http::response(['status_code' => '200'], 200)]);

        $this->service = app(MidtransPaymentAttemptService::class);
    }

    private function fakeSnapSuccess(): void
    {
        Http::fake([
            '*/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-abc',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
            ], 201),
        ]);
    }

    public function test_membuat_attempt_pertama_dan_mengaktifkannya(): void
    {
        $this->fakeSnapSuccess();
        $order = Order::factory()->create(['grand_total' => 220000]);

        $attempt = $this->service->createOrReuseActiveAttempt($order, 'bni_va');

        $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->status);
        $this->assertSame('bni_va', $attempt->payment_method);
        $this->assertSame('snap-token-abc', $attempt->snap_token);
        $this->assertSame($order->order_number.'-A1', $attempt->midtrans_order_id);
        $this->assertSame(220000, $attempt->gross_amount);
        $this->assertSame($attempt->id, $order->fresh()->active_payment_attempt_id);
    }

    public function test_double_click_metode_sama_mereuse_attempt(): void
    {
        $this->fakeSnapSuccess();
        $order = Order::factory()->create();

        $first = $this->service->createOrReuseActiveAttempt($order, 'bni_va');
        $second = $this->service->createOrReuseActiveAttempt($order, 'bni_va');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentAttempt::query()->where('order_id', $order->id)->count());
    }

    public function test_ganti_metode_membuat_attempt_baru_dan_supersede_lama(): void
    {
        $this->fakeSnapSuccess();
        $order = Order::factory()->create();

        $a1 = $this->service->createOrReuseActiveAttempt($order, 'bni_va');
        $a2 = $this->service->createOrReuseActiveAttempt($order, 'bri_va');

        $this->assertNotSame($a1->id, $a2->id);
        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $a1->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::PENDING, $a2->status);
        $this->assertSame('bri_va', $a2->payment_method);
        $this->assertSame($a2->id, $order->fresh()->active_payment_attempt_id);
        $this->assertSame($order->order_number.'-A2', $a2->midtrans_order_id);

        // Cancel best effort ke Midtrans untuk attempt lama.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/cancel'));
    }

    public function test_metode_tidak_didukung_ditolak(): void
    {
        $order = Order::factory()->create();

        $this->expectException(BusinessRuleException::class);

        $this->service->createOrReuseActiveAttempt($order, 'dana');
    }

    public function test_order_lunas_tidak_bisa_buat_pembayaran(): void
    {
        $order = Order::factory()->paid()->create();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('sudah lunas');

        $this->service->createOrReuseActiveAttempt($order, 'bni_va');
    }

    public function test_attempt_menyetel_masa_berlaku_dan_expiry_snap(): void
    {
        $this->fakeSnapSuccess();
        SettingService::set('expiry_order', '5');
        $order = Order::factory()->create();

        $attempt = $this->service->createOrReuseActiveAttempt($order, 'bni_va');

        $this->assertNotNull($attempt->expired_at);
        $this->assertEqualsWithDelta(
            now()->addMinutes(5)->timestamp,
            $attempt->expired_at->timestamp,
            60,
        );

        // Payload Snap yang dikirim ke Midtrans memuat blok expiry (menit).
        $this->assertSame('minute', $attempt->snap_request_payload['expiry']['unit']);
        $this->assertSame(5, $attempt->snap_request_payload['expiry']['duration']);
    }

    public function test_db_menolak_dua_attempt_open_untuk_satu_order(): void
    {
        $order = Order::factory()->create();

        // Attempt pertama open (pending).
        PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'attempt_sequence' => 1,
            'midtrans_order_id' => $order->order_number.'-A1',
            'status' => PaymentAttemptStatus::PENDING,
        ]);

        // Attempt kedua open untuk order yang sama harus ditolak oleh unique active_guard.
        $this->expectException(QueryException::class);

        PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'attempt_sequence' => 2,
            'midtrans_order_id' => $order->order_number.'-A2',
            'status' => PaymentAttemptStatus::PENDING,
        ]);
    }

    public function test_kegagalan_snap_api_tidak_menyisakan_attempt(): void
    {
        Http::fake([
            '*/snap/v1/transactions' => Http::response(['error_messages' => ['gagal']], 500),
            '*/v2/*/cancel' => Http::response(['status_code' => '200'], 200),
        ]);

        $order = Order::factory()->create();

        try {
            $this->service->createOrReuseActiveAttempt($order, 'bni_va');
            $this->fail('Seharusnya melempar BusinessRuleException.');
        } catch (BusinessRuleException) {
            // diharapkan.
        }

        // Transaksi di-rollback: tidak ada attempt PENDING tanpa token yang tertinggal.
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertNull($order->fresh()->active_payment_attempt_id);
    }

    public function test_sync_active_attempt_melunasi_dari_status_api(): void
    {
        Queue::fake();
        $this->fakeSnapSuccess();

        $order = Order::factory()->create(['grand_total' => 175000]);
        $attempt = $this->service->createOrReuseActiveAttempt($order, 'bni_va');

        // Midtrans Get Status mengembalikan settlement (sumber tepercaya, tanpa signature).
        Http::fake([
            '*/v2/*/status' => Http::response([
                'order_id' => $attempt->midtrans_order_id,
                'transaction_id' => 'trx-123',
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'gross_amount' => '175000.00',
                'fraud_status' => 'accept',
            ], 200),
        ]);

        $this->service->syncActiveAttempt($order->fresh());

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::PAID, $attempt->fresh()->status);
    }
}
