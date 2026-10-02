<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentMethods;
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
}
