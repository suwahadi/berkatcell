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
