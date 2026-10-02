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

    /**
     * Mengembalikan false bila status belum bisa dipastikan lewat inquiry, supaya
     * pemanggil meminta Nicepay mengirim ulang notifikasinya.
     */
    public function handleNotification(array $payload): bool
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
            return true;
        }

        return $this->reprocessEvent($event);
    }

    public function reprocessEvent(PaymentWebhookEvent $event): bool
    {
        $attempt = PaymentAttempt::query()
            ->where('provider', PaymentMethods::NICEPAY)
            ->where('midtrans_order_id', (string) $event->midtrans_order_id)
            ->first();

        if (! $attempt) {
            $event->update([
                'processing_status' => 'ignored',
                'notes' => 'Payment attempt tidak ditemukan.',
            ]);

            return true;
        }

        return $this->applyInquiry($attempt, $event);
    }

    /**
     * Status lunas hanya diambil dari Status Inquiry. Notifikasi dan callback bisa
     * dipalsukan atau terlambat, jadi keduanya hanya memicu inquiry ini. Bila
     * inquiry gagal, event dibiarkan berstatus received: notifikasi dibalas 503
     * dan payments:reconcile memprosesnya ulang lewat reprocessEvent().
     */
    private function applyInquiry(PaymentAttempt $attempt, ?PaymentWebhookEvent $event): bool
    {
        $inquiry = $this->client->inquiry($attempt);

        if (($inquiry['resultCd'] ?? null) !== '0000' || ! isset($inquiry['status'])) {
            Log::warning('Nicepay inquiry tanpa hasil', [
                'referenceNo' => $attempt->midtrans_order_id,
                'resultCd' => $inquiry['resultCd'] ?? null,
                'resultMsg' => $inquiry['resultMsg'] ?? null,
            ]);

            return false;
        }

        DB::transaction(function () use ($attempt, $inquiry, $event): void {
            $lockedEvent = $event === null
                ? null
                : PaymentWebhookEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($lockedEvent !== null && in_array($lockedEvent->processing_status, self::TERMINAL_EVENT_STATUSES, true)) {
                return;
            }

            $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            if ($this->isStaleUnpaidResult($locked, $inquiry)) {
                $lockedEvent?->update([
                    'processing_status' => 'ignored',
                    'notes' => 'Hasil inquiry belum bayar diabaikan; attempt sudah lunas.',
                ]);

                return;
            }

            $locked->update([
                'midtrans_transaction_status' => (string) $inquiry['status'],
                'latest_notification_payload' => $inquiry,
            ]);

            $this->route($order, $locked, $inquiry, $lockedEvent);
        });

        return true;
    }

    /**
     * Inquiry diambil sebelum baris dikunci, jadi hasil "belum bayar" bisa datang
     * setelah permintaan lain melunasi attempt yang sama.
     */
    private function isStaleUnpaidResult(PaymentAttempt $attempt, array $inquiry): bool
    {
        return $attempt->status === PaymentAttemptStatus::PAID
            && in_array((string) $inquiry['status'], ['3', '9'], true);
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
            if (! $attempt->isOpen()) {
                $event?->update([
                    'processing_status' => 'ignored',
                    'notes' => 'Gagal pada attempt yang sudah tidak terbuka diabaikan.',
                ]);

                return;
            }

            $this->settlement->failed($order, $attempt, PaymentAttemptStatus::FAILED, 'failure', $event);

            return;
        }

        if (in_array($status, ['1', '2'], true)) {
            $this->settlement->reversed($order, $attempt, $status === '1' ? 'void' : 'refund', $event);

            return;
        }

        $event?->update([
            'processing_status' => 'ignored',
            'notes' => 'status tidak ditangani: '.$status,
        ]);
    }

    private function storeEvent(array $payload): PaymentWebhookEvent
    {
        $text = fn (string $key): string => is_scalar($payload[$key] ?? null) ? (string) $payload[$key] : '';

        $eventHash = hash('sha256', implode('|', [
            PaymentMethods::NICEPAY,
            $text('referenceNo'),
            $text('tXid'),
            $text('status'),
            $text('amt'),
            $text('merchantToken'),
        ]));

        return PaymentWebhookEvent::query()->firstOrCreate(
            ['event_hash' => $eventHash],
            [
                'provider' => PaymentMethods::NICEPAY,
                'midtrans_order_id' => $text('referenceNo'),
                'transaction_id' => $text('tXid') ?: null,
                'transaction_status' => $text('status') ?: null,
                'gross_amount' => $text('amt') ?: null,
                'signature_key' => $text('merchantToken') ?: null,
                'payload' => $payload,
                'processing_status' => 'received',
            ]
        );
    }
}
