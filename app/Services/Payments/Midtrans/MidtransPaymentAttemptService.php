<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\OrderActivityService;
use Illuminate\Support\Facades\DB;

class MidtransPaymentAttemptService
{
    public const METHOD_MAP = [
        'bni_va' => 'bni_va',
        'bri_va' => 'bri_va',
        'bca_va' => 'bca_va',
        'permata_va' => 'permata_va',
        'gopay' => 'gopay',
        'qris' => 'qris',
    ];

    public function __construct(
        private readonly MidtransClient $client,
        private readonly OrderActivityService $activities,
        private readonly MidtransWebhookService $webhook,
    ) {}

    public static function supportedMethods(): array
    {
        return array_keys(self::METHOD_MAP);
    }

    public function createOrReuseActiveAttempt(Order $order, string $paymentMethod): PaymentAttempt
    {
        if (! array_key_exists($paymentMethod, self::METHOD_MAP)) {
            throw new BusinessRuleException('Metode pembayaran tidak didukung.');
        }

        return DB::transaction(function () use ($order, $paymentMethod): PaymentAttempt {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === OrderStatus::PAID) {
                throw new BusinessRuleException('Pesanan sudah lunas. Tidak bisa membuat pembayaran baru.');
            }

            if ($lockedOrder->status === OrderStatus::CANCELLED) {
                throw new BusinessRuleException('Pesanan sudah dibatalkan.');
            }

            $activeAttempt = $lockedOrder->activePaymentAttempt;

            if (
                $activeAttempt
                && $activeAttempt->payment_method === $paymentMethod
                && $activeAttempt->status instanceof PaymentAttemptStatus
                && $activeAttempt->status->isOpen()
            ) {
                return $activeAttempt;
            }

            if ($activeAttempt && $activeAttempt->status instanceof PaymentAttemptStatus && $activeAttempt->status->isOpen()) {
                $activeAttempt->update(['status' => PaymentAttemptStatus::SUPERSEDED]);
                rescue(fn () => $this->client->cancel($activeAttempt->midtrans_order_id), report: false);
            }

            $nextSequence = (int) PaymentAttempt::query()
                ->where('order_id', $lockedOrder->id)
                ->max('attempt_sequence') + 1;

            $midtransOrderId = $lockedOrder->order_number.'-A'.$nextSequence;

            $attempt = PaymentAttempt::query()->create([
                'order_id' => $lockedOrder->id,
                'attempt_sequence' => $nextSequence,
                'midtrans_order_id' => $midtransOrderId,
                'payment_method' => $paymentMethod,
                'status' => PaymentAttemptStatus::CREATING,
                'gross_amount' => $lockedOrder->grand_total,
                'activated_at' => now(),
                'expired_at' => now()->addMinutes(order_expiry_minutes()),
            ]);

            $payload = $this->buildSnapPayload($lockedOrder, $attempt, $paymentMethod);
            $response = $this->client->createSnapTransaction($payload);

            $attempt->update([
                'status' => PaymentAttemptStatus::PENDING,
                'snap_token' => $response['token'] ?? null,
                'redirect_url' => $response['redirect_url'] ?? null,
                'snap_request_payload' => $payload,
                'snap_response_payload' => $response,
            ]);

            $lockedOrder->update([
                'active_payment_attempt_id' => $attempt->id,
            ]);

            $this->activities->paymentStarted($lockedOrder, $paymentMethod);

            return $attempt->refresh();
        });
    }

    public function syncActiveAttempt(Order $order): void
    {
        $order->loadMissing('activePaymentAttempt');
        $attempt = $order->activePaymentAttempt;

        if (
            ! $attempt instanceof PaymentAttempt
            || ! $attempt->status instanceof PaymentAttemptStatus
            || ! $attempt->status->isOpen()
        ) {
            return;
        }

        $status = $this->client->status($attempt->midtrans_order_id);

        if (! isset($status['transaction_status'])) {
            return;
        }

        $this->webhook->syncFromStatus($attempt, $status);
    }

    private function buildSnapPayload(Order $order, PaymentAttempt $attempt, string $paymentMethod): array
    {
        return [
            'transaction_details' => [
                'order_id' => $attempt->midtrans_order_id,
                'gross_amount' => (int) $order->grand_total,
            ],
            'enabled_payments' => [self::METHOD_MAP[$paymentMethod]],
            'customer_details' => [
                'first_name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],

            'expiry' => [
                'unit' => 'minute',
                'duration' => order_expiry_minutes(),
            ],
            'callbacks' => [
                'finish' => route('payments.midtrans.finish'),
            ],
        ];
    }
}
