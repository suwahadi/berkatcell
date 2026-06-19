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
use Livewire\Livewire;
use Tests\TestCase;

class MidtransInvoicePageTest extends TestCase
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
        ]);

        Http::fake([
            '*/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-abc',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
            ], 201),
            '*/v2/*/cancel' => Http::response(['status_code' => '200'], 200),
        ]);

        Queue::fake();
    }

    public function test_pay_membuat_attempt_dan_membuka_snap(): void
    {
        $order = Order::factory()->create();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->set('payment_method', 'bni_va')
            ->call('pay')
            ->assertDispatched('snap-pay', token: 'snap-token-abc');

        $this->assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'payment_method' => 'bni_va',
            'status' => PaymentAttemptStatus::PENDING->value,
        ]);
    }

    public function test_continue_payment_mereuse_attempt_aktif(): void
    {
        $order = Order::factory()->create();
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number.'-A1',
            'payment_method' => 'bni_va',
            'status' => PaymentAttemptStatus::PENDING,
            'snap_token' => 'snap-token-abc',
            'gross_amount' => $order->grand_total,
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->call('continuePayment')
            ->assertDispatched('snap-pay', token: 'snap-token-abc');

        // Tidak membuat attempt baru.
        $this->assertSame(1, PaymentAttempt::query()->where('order_id', $order->id)->count());
    }

    public function test_ganti_metode_men_supersede_attempt_lama(): void
    {
        $order = Order::factory()->create();
        $a1 = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number.'-A1',
            'payment_method' => 'bni_va',
            'status' => PaymentAttemptStatus::PENDING,
            'snap_token' => 'snap-token-abc',
            'gross_amount' => $order->grand_total,
        ]);
        $order->update(['active_payment_attempt_id' => $a1->id]);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->call('startChangeMethod')
            ->assertSet('changingMethod', true)
            ->set('payment_method', 'bri_va')
            ->call('pay')
            ->assertSet('changingMethod', false)
            ->assertDispatched('snap-pay');

        $this->assertSame(PaymentAttemptStatus::SUPERSEDED, $a1->fresh()->status);
        $this->assertSame('bri_va', $order->fresh()->activePaymentAttempt->payment_method);
    }

    public function test_refresh_status_melunasi_saat_settlement(): void
    {
        $order = Order::factory()->create(['grand_total' => 130000]);
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number.'-A1',
            'payment_method' => 'bni_va',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        Http::fake([
            '*/v2/*/status' => Http::response([
                'order_id' => $attempt->midtrans_order_id,
                'transaction_id' => 'trx-9',
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'gross_amount' => '130000.00',
                'fraud_status' => 'accept',
            ], 200),
        ]);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->call('refreshStatus');

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }
}
