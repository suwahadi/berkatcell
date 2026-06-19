<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MidtransNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.is_production' => false,
        ]);

        Http::fake(['*/v2/*/cancel' => Http::response(['status_code' => '200'], 200)]);
        Queue::fake();
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

    public function test_signature_valid_mengembalikan_200(): void
    {
        $order = Order::factory()->create();
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number.'-A1',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        $response = $this->postJson(
            route('payments.midtrans.notification'),
            $this->notification($attempt, 'settlement'),
        );

        $response->assertOk();
    }

    public function test_signature_tidak_valid_mengembalikan_403(): void
    {
        $order = Order::factory()->create();
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number.'-A1',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
        ]);

        $response = $this->postJson(
            route('payments.midtrans.notification'),
            $this->notification($attempt, 'settlement', ['signature_key' => 'palsu']),
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('payment_webhook_events', [
            'midtrans_order_id' => $attempt->midtrans_order_id,
            'processing_status' => 'invalid_signature',
        ]);
    }
}
