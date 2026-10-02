<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\OrderActivityService;
use App\Services\OrderService;
use App\Services\Payments\Midtrans\MidtransClient;
use App\Services\Payments\Nicepay\NicepayClient;
use Illuminate\Support\Facades\Log;

/**
 * Pemanggil wajib sudah memegang kunci baris (lockForUpdate) untuk order dan
 * attempt di dalam transaksi; kelas ini tidak mengunci sendiri.
 */
class PaymentSettlementService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderActivityService $activities,
        private readonly MidtransClient $midtrans,
        private readonly NicepayClient $nicepay,
    ) {}

    public function paid(Order $order, PaymentAttempt $attempt, int $paidAmount, string $actor, ?PaymentWebhookEvent $event = null): void
    {
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

            Log::warning('Pembayaran ganda dari attempt lain pada order yang sudah lunas.', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'provider' => $attempt->provider,
                'reference' => $attempt->midtrans_order_id,
            ]);

            $event?->update([
                'processing_status' => 'ignored',
                'notes' => 'Pembayaran dari attempt lain saat order sudah lunas. Perlu review refund.',
            ]);

            return;
        }

        if ($paidAmount !== (int) $order->grand_total) {
            Log::warning('Nominal pembayaran tidak cocok dengan total order.', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'provider' => $attempt->provider,
                'paid_amount' => $paidAmount,
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

        $this->orderService->markAsPaid($order, $actor);

        $this->cancelOtherOpenAttempts($order, $attempt);

        $event?->update([
            'processing_status' => 'processed',
            'notes' => 'Order ditandai lunas.',
        ]);
    }

    public function pending(Order $order, PaymentAttempt $attempt, ?PaymentWebhookEvent $event = null): void
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

    public function failed(Order $order, PaymentAttempt $attempt, PaymentAttemptStatus $status, string $reason, ?PaymentWebhookEvent $event = null): void
    {
        $attempt->update([
            'status' => $status,
            'expired_at' => $status === PaymentAttemptStatus::EXPIRED ? now() : $attempt->expired_at,
        ]);

        if ($order->status !== OrderStatus::PAID) {
            $this->activities->paymentFailed($order, $reason, PaymentMethods::actorFor($attempt->provider));
        }

        $event?->update([
            'processing_status' => 'processed',
            'notes' => 'Status gagal diproses: '.$reason,
        ]);
    }

    public function closeOpenAttempts(Order $order): void
    {
        $order->paymentAttempts()->get()->each(function (PaymentAttempt $attempt): void {
            if ($attempt->isOpen()) {
                $attempt->update(['status' => PaymentAttemptStatus::CANCELLED]);
                $this->cancelAtGateway($attempt);
            }
        });
    }

    public function cancelAtGateway(PaymentAttempt $attempt): void
    {
        rescue(fn () => match ($attempt->provider) {
            PaymentMethods::NICEPAY => $this->nicepay->cancel($attempt),
            default => $this->midtrans->cancel($attempt->midtrans_order_id),
        }, report: false);
    }

    private function cancelOtherOpenAttempts(Order $order, PaymentAttempt $paidAttempt): void
    {
        $order->paymentAttempts()
            ->where('id', '!=', $paidAttempt->id)
            ->get()
            ->each(function (PaymentAttempt $other): void {
                if ($other->isOpen()) {
                    $other->update(['status' => PaymentAttemptStatus::SUPERSEDED]);
                    $this->cancelAtGateway($other);
                }
            });
    }
}
