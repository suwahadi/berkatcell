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

    public function test_inquiry_gagal_membalas_503_agar_dikirim_ulang(): void
    {
        $order = Order::factory()->create(['grand_total' => 150000]);
        $attempt = $this->attemptFor($order);
        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::response(['error' => 'server'], 500)]);

        $this->post(
            route('payments.nicepay.notification'),
            $this->notification(['referenceNo' => $attempt->midtrans_order_id]),
        )->assertStatus(503);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'midtrans_order_id' => $attempt->midtrans_order_id,
            'processing_status' => 'received',
        ]);
    }

    public function test_field_berbentuk_array_ditolak_403_bukan_error(): void
    {
        $response = $this->post(route('payments.nicepay.notification'), [
            'tXid' => ['x'],
            'referenceNo' => ['y'],
            'amt' => ['1'],
            'merchantToken' => 'palsu',
        ]);

        $response->assertForbidden();
        Http::assertNothingSent();
    }
}
