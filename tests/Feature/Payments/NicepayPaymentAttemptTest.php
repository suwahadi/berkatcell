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
