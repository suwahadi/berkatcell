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

    public function test_pesanan_dibatalkan_tidak_mendapat_form_bayar(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::CANCELLED]);
        $this->nicepayAttempt($order);

        $this->get(route('payments.nicepay.pay', ['order' => $order->uuid]))
            ->assertRedirect(route('checkout.success', ['order' => $order->uuid]));
    }
}
