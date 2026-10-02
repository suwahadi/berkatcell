<?php

declare(strict_types=1);

namespace App\Services\Payments\Nicepay;

use App\Enums\PaymentAttemptStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaymentMethods;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NicepayPaylaterService
{
    private const TERMINAL_EVENT_STATUSES = ['processed', 'ignored', 'invalid_signature'];

    public function __construct(
        private readonly NicepayClient $client,
        private readonly PaymentSettlementService $settlement,
        private readonly NicepayRegistrationPayload $payload,
    ) {}

    public function start(Order $order, PaymentAttempt $attempt): void
    {
        $payload = $this->payload->build($order, $attempt);
        $response = $this->client->register($payload);

        $attempt->update([
            'status' => PaymentAttemptStatus::PENDING,
            'midtrans_transaction_id' => $response['tXid'],
            'snap_request_payload' => $payload,
            'snap_response_payload' => $response,
        ]);
    }

    public function sync(PaymentAttempt $attempt): void
    {
        $this->applyInquiry($attempt, null);
    }

    public function handleNotification(array $payload): void
    {
        if (! $this->client->notificationTokenIsValid($payload)) {
            $this->storeEvent($payload)->update([
                'processing_status' => 'invalid_signature',
                'notes' => 'Token Nicepay tidak valid.',
            ]);

            throw new InvalidWebhookSignatureException('Token Nicepay tidak valid.');
        }

        $event = $this->storeEvent($payload);

        if (in_array($event->processing_status, self::TERMINAL_EVENT_STATUSES, true)) {
            return;
        }

        $attempt = PaymentAttempt::query()
            ->where('provider', PaymentMethods::NICEPAY)
            ->where('midtrans_order_id', (string) ($payload['referenceNo'] ?? ''))
            ->first();

        if (! $attempt) {
            $event->update([
                'processing_status' => 'ignored',
                'notes' => 'Payment attempt tidak ditemukan.',
            ]);

            return;
        }

        $this->applyInquiry($attempt, $event);
    }

    /**
     * Status lunas hanya diambil dari Status Inquiry. Notifikasi dan callback bisa
     * dipalsukan atau terlambat, jadi keduanya hanya memicu inquiry ini. Bila
     * inquiry gagal, event dibiarkan berstatus received supaya notifikasi ulang
     * atau rekonsiliasi memprosesnya lagi.
     */
    private function applyInquiry(PaymentAttempt $attempt, ?PaymentWebhookEvent $event): void
    {
        $inquiry = $this->client->inquiry($attempt);

        if (($inquiry['resultCd'] ?? null) !== '0000' || ! isset($inquiry['status'])) {
            return;
        }

        DB::transaction(function () use ($attempt, $inquiry, $event): void {
            $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'midtrans_transaction_status' => (string) $inquiry['status'],
                'latest_notification_payload' => $inquiry,
            ]);

            $this->route($order, $locked, $inquiry, $event);
        });
    }

    private function route(Order $order, PaymentAttempt $attempt, array $inquiry, ?PaymentWebhookEvent $event): void
    {
        $status = (string) $inquiry['status'];

        if ($status === '0') {
            $this->settlement->paid(
                $order,
                $attempt,
                (int) ($inquiry['amt'] ?? 0),
                PaymentMethods::actorFor(PaymentMethods::NICEPAY),
                $event,
            );

            return;
        }

        if (in_array($status, ['3', '9'], true)) {
            if (! $attempt->isOpen()) {
                $event?->update([
                    'processing_status' => 'ignored',
                    'notes' => 'Belum dibayar; attempt sudah tidak terbuka.',
                ]);

                return;
            }

            if ($attempt->isExpired()) {
                $this->settlement->failed($order, $attempt, PaymentAttemptStatus::EXPIRED, 'expire', $event);

                return;
            }

            $this->settlement->pending($order, $attempt, $event);

            return;
        }

        if ($status === '8') {
            $this->settlement->failed($order, $attempt, PaymentAttemptStatus::FAILED, 'failure', $event);

            return;
        }

        if (in_array($status, ['1', '2'], true)) {
            Log::warning('Nicepay: transaksi di-void atau di-refund; perlu ditinjau admin.', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'status' => $status,
            ]);

            $attempt->update(['status' => PaymentAttemptStatus::CANCELLED]);

            $event?->update([
                'processing_status' => 'processed',
                'notes' => 'Transaksi void atau refund di Nicepay. Perlu ditinjau.',
            ]);

            return;
        }

        $event?->update([
            'processing_status' => 'ignored',
            'notes' => 'status tidak ditangani: '.$status,
        ]);
    }

    private function storeEvent(array $payload): PaymentWebhookEvent
    {
        $eventHash = hash('sha256', implode('|', [
            PaymentMethods::NICEPAY,
            $payload['referenceNo'] ?? '',
            $payload['tXid'] ?? '',
            $payload['status'] ?? '',
            $payload['amt'] ?? '',
            $payload['merchantToken'] ?? '',
        ]));

        return PaymentWebhookEvent::query()->firstOrCreate(
            ['event_hash' => $eventHash],
            [
                'provider' => PaymentMethods::NICEPAY,
                'midtrans_order_id' => (string) ($payload['referenceNo'] ?? ''),
                'transaction_id' => $payload['tXid'] ?? null,
                'transaction_status' => $payload['status'] ?? null,
                'gross_amount' => $payload['amt'] ?? null,
                'signature_key' => $payload['merchantToken'] ?? null,
                'payload' => $payload,
                'processing_status' => 'received',
            ]
        );
    }
}
