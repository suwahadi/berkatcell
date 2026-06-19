<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\OrderActivityService;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookService
{
    public function __construct(
        private readonly MidtransSignatureVerifier $signatureVerifier,
        private readonly MidtransStatusMapper $statusMapper,
        private readonly OrderService $orderService,
        private readonly MidtransClient $client,
        private readonly OrderActivityService $activities,
    ) {}

    private const TERMINAL_STATUSES = ['processed', 'ignored', 'invalid_signature'];

    public function handle(array $payload): void
    {
        if (! $this->signatureVerifier->isValid($payload)) {
            $this->storeEvent($payload)->update([
                'processing_status' => 'invalid_signature',
                'notes' => 'Signature Midtrans tidak valid.',
            ]);

            throw new InvalidWebhookSignatureException('Signature Midtrans tidak valid.');
        }

        $event = $this->storeEvent($payload);

        DB::transaction(function () use ($payload, $event): void {
            $lockedEvent = PaymentWebhookEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedEvent->processing_status, self::TERMINAL_STATUSES, true)) {
                return;
            }

            $attempt = PaymentAttempt::query()
                ->where('midtrans_order_id', $payload['order_id'] ?? null)
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                $lockedEvent->update([
                    'processing_status' => 'ignored',
                    'notes' => 'Payment attempt tidak ditemukan.',
                ]);

                return;
            }

            $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();

            $this->applyToAttempt($attempt, $payload);
            $this->route($order, $attempt, $payload, $lockedEvent);
        });
    }

    public function syncFromStatus(PaymentAttempt $attempt, array $payload): void
    {
        DB::transaction(function () use ($attempt, $payload): void {
            $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $this->applyToAttempt($locked, $payload);
            $this->route($order, $locked, $payload, null);
        });
    }

    private function route(Order $order, PaymentAttempt $attempt, array $payload, ?PaymentWebhookEvent $event): void
    {
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;

        if ($this->statusMapper->isPaid($transactionStatus, $fraudStatus)) {
            $this->handlePaid($order, $attempt, $payload, $event);

            return;
        }

        if ($transactionStatus === 'pending') {
            $this->handlePending($order, $attempt, $event);

            return;
        }

        if ($this->statusMapper->isFailureLike($transactionStatus)) {
            $this->handleFailed($order, $attempt, (string) $transactionStatus, $fraudStatus, $event);

            return;
        }

        $event?->update([
            'processing_status' => 'ignored',
            'notes' => 'transaction_status tidak ditangani: '.(string) $transactionStatus,
        ]);
    }

    private function handlePaid(Order $order, PaymentAttempt $attempt, array $payload, ?PaymentWebhookEvent $event): void
    {
        $grossMatches = (int) round((float) ($payload['gross_amount'] ?? 0)) === (int) $order->grand_total;

        if ($order->status === OrderStatus::PAID) {
            $attempt->update([
                'status' => PaymentAttemptStatus::PAID,
                'paid_at' => $attempt->paid_at ?? now(),
            ]);

            if ((int) $order->active_payment_attempt_id === (int) $attempt->id) {
                $event?->update([
                    'processing_status' => 'ignored',
                    'notes' => 'Order sudah lunas. Notifikasi paid duplikat diabaikan.',
                ]);

                return;
            }

            Log::warning('Midtrans: pembayaran ganda dari attempt lain pada order yang sudah lunas.', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'midtrans_order_id' => $attempt->midtrans_order_id,
            ]);

            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Pembayaran dari attempt lain saat order sudah lunas. Perlu review refund.',
            ]);

            return;
        }

        if (! $grossMatches) {
            Log::warning('Midtrans: nominal pembayaran tidak cocok dengan total order.', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'gross_amount' => $payload['gross_amount'] ?? null,
                'grand_total' => $order->grand_total,
            ]);

            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Nominal pembayaran tidak cocok dengan total order. Perlu review.',
            ]);

            return;
        }

        $attempt->update([
            'status' => PaymentAttemptStatus::PAID,
            'paid_at' => now(),
        ]);

        $order->forceFill([
            'paid_at' => now(),
            'active_payment_attempt_id' => $attempt->id,
        ])->save();

        $this->orderService->markAsPaid($order, 'Midtrans (otomatis)');

        $this->cancelOtherOpenAttempts($order, $attempt);

        $event?->update([
            'processing_status' => 'processed',
            'notes' => 'Order ditandai lunas.',
        ]);
    }

    private function handlePending(Order $order, PaymentAttempt $attempt, ?PaymentWebhookEvent $event): void
    {
        if ($order->status === OrderStatus::PAID) {
            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Order sudah lunas; notifikasi pending diabaikan.',
            ]);

            return;
        }

        if ((int) $order->active_payment_attempt_id !== (int) $attempt->id) {
            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Pending dari attempt non-aktif diabaikan.',
            ]);

            return;
        }

        $attempt->update(['status' => PaymentAttemptStatus::PENDING]);

        $event?->update([
            'processing_status' => 'processed',
            'notes' => 'Status pending diproses.',
        ]);
    }

    private function handleFailed(
        Order $order,
        PaymentAttempt $attempt,
        string $transactionStatus,
        ?string $fraudStatus,
        ?PaymentWebhookEvent $event
    ): void {
        $attempt->update([
            'status' => $this->statusMapper->attemptStatus($transactionStatus, $fraudStatus),
            'expired_at' => $transactionStatus === 'expire' ? now() : $attempt->expired_at,
        ]);

        if ($order->status !== OrderStatus::PAID) {
            $this->activities->paymentFailed($order, $transactionStatus);
        }

        $event?->update([
            'processing_status' => 'processed',
            'notes' => 'Status gagal diproses: '.$transactionStatus,
        ]);
    }

    private function cancelOtherOpenAttempts(Order $order, PaymentAttempt $paidAttempt): void
    {
        $order->paymentAttempts()
            ->where('id', '!=', $paidAttempt->id)
            ->get()
            ->each(function (PaymentAttempt $other): void {
                if ($other->status instanceof PaymentAttemptStatus && $other->status->isOpen()) {
                    $other->update(['status' => PaymentAttemptStatus::SUPERSEDED]);
                    rescue(fn () => $this->client->cancel($other->midtrans_order_id), report: false);
                }
            });
    }

    private function applyToAttempt(PaymentAttempt $attempt, array $payload): void
    {
        $attempt->update([
            'midtrans_transaction_id' => $payload['transaction_id'] ?? $attempt->midtrans_transaction_id,
            'midtrans_transaction_status' => $payload['transaction_status'] ?? $attempt->midtrans_transaction_status,
            'midtrans_fraud_status' => $payload['fraud_status'] ?? $attempt->midtrans_fraud_status,
            'latest_notification_payload' => $payload,
        ]);
    }

    private function storeEvent(array $payload): PaymentWebhookEvent
    {
        $eventHash = hash('sha256', implode('|', [
            $payload['order_id'] ?? '',
            $payload['transaction_id'] ?? '',
            $payload['transaction_status'] ?? '',
            $payload['status_code'] ?? '',
            $payload['gross_amount'] ?? '',
            $payload['signature_key'] ?? '',
        ]));

        return PaymentWebhookEvent::query()->firstOrCreate(
            ['event_hash' => $eventHash],
            [
                'midtrans_order_id' => $payload['order_id'] ?? '',
                'transaction_id' => $payload['transaction_id'] ?? null,
                'transaction_status' => $payload['transaction_status'] ?? null,
                'status_code' => $payload['status_code'] ?? null,
                'gross_amount' => $payload['gross_amount'] ?? null,
                'signature_key' => $payload['signature_key'] ?? null,
                'payload' => $payload,
                'processing_status' => 'received',
            ]
        );
    }
}
