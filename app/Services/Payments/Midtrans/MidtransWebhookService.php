<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Support\Facades\DB;

class MidtransWebhookService
{
    public function __construct(
        private readonly MidtransSignatureVerifier $signatureVerifier,
        private readonly MidtransStatusMapper $statusMapper,
        private readonly PaymentSettlementService $settlement,
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
            $this->settlement->paid(
                $order,
                $attempt,
                (int) round((float) ($payload['gross_amount'] ?? 0)),
                'Midtrans (otomatis)',
                $event,
            );

            return;
        }

        if ($transactionStatus === 'pending') {
            $this->settlement->pending($order, $attempt, $event);

            return;
        }

        if ($this->statusMapper->isFailureLike($transactionStatus)) {
            $this->settlement->failed(
                $order,
                $attempt,
                $this->statusMapper->attemptStatus($transactionStatus, $fraudStatus),
                (string) $transactionStatus,
                $event,
            );

            return;
        }

        $event?->update([
            'processing_status' => 'ignored',
            'notes' => 'transaction_status tidak ditangani: '.(string) $transactionStatus,
        ]);
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
