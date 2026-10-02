<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Nicepay\NicepayClient;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NicepayClientTest extends TestCase
{
    use RefreshDatabase;

    private NicepayClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);

        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-10-02 10:15:00', 'Asia/Jakarta'));

        $this->client = app(NicepayClient::class);
    }

    private function attempt(array $overrides = []): PaymentAttempt
    {
        $order = Order::factory()->create(['grand_total' => 150000]);

        return PaymentAttempt::factory()->create(array_merge([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => 'JW-TEST-A1',
            'midtrans_transaction_id' => 'TESTIMID0106202610021015001234',
            'payment_method' => 'indodana',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => 150000,
        ], $overrides));
    }

    public function test_base_url_mengikuti_mode(): void
    {
        $this->assertSame('https://dev.nicepay.co.id', $this->client->baseUrl());

        config(['services.nicepay.is_production' => true]);

        $this->assertSame('https://www.nicepay.co.id', $this->client->baseUrl());
    }

    public function test_timestamp_berzona_jakarta(): void
    {
        $this->assertSame('20261002101500', $this->client->timestamp());
    }

    public function test_token_transaksi_memakai_rumus_registration(): void
    {
        $this->assertSame(
            hash('sha256', '20261002101500'.'TESTIMID01'.'JW-TEST-A1'.'150000'.'test-merchant-key'),
            $this->client->transactionToken('20261002101500', 'JW-TEST-A1', 150000),
        );
    }

    public function test_token_notifikasi_valid_dan_tidak_valid(): void
    {
        $valid = [
            'tXid' => 'TX123',
            'amt' => '150000',
            'merchantToken' => hash('sha256', 'TESTIMID01'.'TX123'.'150000'.'test-merchant-key'),
        ];

        $this->assertTrue($this->client->notificationTokenIsValid($valid));
        $this->assertFalse($this->client->notificationTokenIsValid(array_merge($valid, ['amt' => '1'])));
        $this->assertFalse($this->client->notificationTokenIsValid(array_merge($valid, ['merchantToken' => 'palsu'])));
        $this->assertFalse($this->client->notificationTokenIsValid(['tXid' => 'TX123']));
    }

    public function test_register_mengirim_json_dan_mengembalikan_respons(): void
    {
        Http::fake([
            '*/nicepay/direct/v2/registration' => Http::response(['resultCd' => '0000', 'resultMsg' => 'SUCCESS', 'tXid' => 'TX999'], 200),
        ]);

        $response = $this->client->register(['referenceNo' => 'JW-TEST-A1', 'amt' => '150000']);

        $this->assertSame('TX999', $response['tXid']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://dev.nicepay.co.id/nicepay/direct/v2/registration'
            && $request->isJson()
            && $request['referenceNo'] === 'JW-TEST-A1');
    }

    public function test_register_gagal_melempar_exception(): void
    {
        Http::fake([
            '*/nicepay/direct/v2/registration' => Http::sequence()
                ->push(['resultCd' => '9999', 'resultMsg' => 'Invalid merchant token'], 200)
                ->push(['error' => 'server'], 500)
                ->push(['resultCd' => '0000', 'resultMsg' => 'SUCCESS'], 200),
        ]);

        foreach (range(1, 3) as $percobaan) {
            try {
                $this->client->register(['referenceNo' => 'JW-TEST-A1']);
                $this->fail("Percobaan {$percobaan} seharusnya melempar BusinessRuleException.");
            } catch (BusinessRuleException $e) {
                $this->assertSame('Gagal memulai pembayaran. Silakan coba lagi.', $e->getMessage());
            }
        }
    }

    public function test_inquiry_mengirim_field_dan_token_yang_benar(): void
    {
        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::response(['resultCd' => '0000', 'status' => '3'], 200)]);

        $response = $this->client->inquiry($this->attempt());

        $this->assertSame('3', $response['status']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://dev.nicepay.co.id/nicepay/direct/v2/inquiry'
            && $request['timeStamp'] === '20261002101500'
            && $request['tXid'] === 'TESTIMID0106202610021015001234'
            && $request['iMid'] === 'TESTIMID01'
            && $request['referenceNo'] === 'JW-TEST-A1'
            && $request['amt'] === '150000'
            && $request['merchantToken'] === hash('sha256', '20261002101500'.'TESTIMID01'.'JW-TEST-A1'.'150000'.'test-merchant-key'));
    }

    public function test_inquiry_mengembalikan_array_kosong_saat_http_gagal(): void
    {
        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::response(['error' => 'server'], 500)]);

        $this->assertSame([], $this->client->inquiry($this->attempt()));
    }

    public function test_inquiry_mengembalikan_array_kosong_saat_koneksi_gagal(): void
    {
        Http::fake(['*/nicepay/direct/v2/inquiry' => Http::failedConnection()]);

        $this->assertSame([], $this->client->inquiry($this->attempt()));
    }

    public function test_cancel_memakai_rumus_token_cancel(): void
    {
        Http::fake(['*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '0000'], 200)]);

        $this->client->cancel($this->attempt());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://dev.nicepay.co.id/nicepay/direct/v2/cancel'
            && $request['payMethod'] === '06'
            && $request['cancelType'] === '1'
            && $request['amt'] === '150000'
            && $request['merchantToken'] === hash('sha256', '20261002101500'.'TESTIMID01'.'TESTIMID0106202610021015001234'.'150000'.'test-merchant-key'));
    }

    public function test_cancel_tanpa_txid_tidak_memanggil_api(): void
    {
        $this->assertSame([], $this->client->cancel($this->attempt(['midtrans_transaction_id' => null])));

        Http::assertNothingSent();
    }

    public function test_kunci_kosong_membuat_token_notifikasi_tidak_valid(): void
    {
        config(['services.nicepay.merchant_key' => '']);

        $this->assertFalse($this->client->notificationTokenIsValid([
            'tXid' => 'TX123',
            'amt' => '150000',
            'merchantToken' => hash('sha256', 'TESTIMID01'.'TX123'.'150000'),
        ]));
    }

    public function test_cancel_yang_ditolak_dicatat_di_log(): void
    {
        Log::spy();
        Http::fake(['*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '9999', 'resultMsg' => 'ditolak'], 200)]);

        $this->client->cancel($this->attempt());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'cancel') && ($context['resultCd'] ?? null) === '9999')
            ->once();
    }
}
