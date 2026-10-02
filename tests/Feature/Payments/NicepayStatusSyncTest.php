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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NicepayStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    private NicepayPaylaterService $service;

    private array $inquiry = ['status' => '3', 'amt' => null, 'resultCd' => '0000', 'http' => 200];

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
        Http::fake([
            '*/nicepay/direct/v2/inquiry' => fn (Request $request) => Http::response([
                'resultCd' => $this->inquiry['resultCd'],
                'resultMsg' => 'SUCCESS',
                'tXid' => $request['tXid'],
                'referenceNo' => $request['referenceNo'],
                'amt' => $this->inquiry['amt'] ?? $request['amt'],
                'status' => $this->inquiry['status'],
                'payMethod' => '06',
                'mitraCd' => 'IDNA',
            ], $this->inquiry['http']),
            '*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '0000'], 200),
            '*/v2/*/cancel' => Http::response(['status_code' => '200'], 200),
        ]);
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

    /**
     * Http::fake memakai stub pertama yang cocok, jadi stub inquiry didaftarkan sekali
     * di setUp() dan membaca nilai ini; memanggil Http::fake lagi tidak menggantinya.
     */
    private function fakeInquiry(string $status, ?string $amt = null, string $resultCd = '0000', int $http = 200): void
    {
        $this->inquiry = compact('status', 'amt', 'resultCd', 'http');
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
            $this->assertSame($status, $attempt->fresh()->midtrans_transaction_status, "status {$status}");
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
            $this->assertSame($status, $attempt->fresh()->midtrans_transaction_status, "status {$status}");
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

        $this->fakeInquiry('0', null, '0000', 500);
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

    public function test_inquiry_bukan_0000_dicatat_di_log(): void
    {
        Log::spy();
        $order = Order::factory()->create();
        $attempt = $this->nicepayAttempt($order);
        $order->update(['active_payment_attempt_id' => $attempt->id]);
        $this->fakeInquiry('0', null, '9999');

        $this->service->sync($attempt);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'inquiry') && ($context['resultCd'] ?? null) === '9999')
            ->once();
    }
}
