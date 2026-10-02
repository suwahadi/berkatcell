<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\OrderActivityService;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use App\Services\Payments\Nicepay\NicepayPaylaterService;
use Illuminate\Support\Facades\DB;

class PaymentAttemptService
{
    public function __construct(
        private readonly MidtransPaymentAttemptService $midtrans,
        private readonly NicepayPaylaterService $nicepay,
        private readonly PaymentSettlementService $settlement,
        private readonly OrderActivityService $activities,
    ) {}

    /**
     * Tagihan lama dibatalkan di penyedianya setelah transaksi selesai. Bila dibatalkan
     * di dalam transaksi lalu pembuatan tagihan baru gagal, database kembali ke tagihan
     * lama padahal penyedia sudah membatalkannya.
     */
    public function createOrReuseActiveAttempt(Order $order, string $paymentMethod): PaymentAttempt
    {
        $provider = PaymentMethods::providerFor($paymentMethod);

        if ($provider === null) {
            throw new BusinessRuleException('Metode pembayaran tidak didukung.');
        }

        if ($provider === PaymentMethods::NICEPAY && ! PaymentMethods::nicepayConfigured()) {
            throw new BusinessRuleException('Metode pembayaran tidak didukung.');
        }

        $superseded = null;

        $attempt = DB::transaction(function () use ($order, $paymentMethod, $provider, &$superseded): PaymentAttempt {
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

            if ($activeAttempt?->isOpen() && ! $activeAttempt->isExpired() && $activeAttempt->payment_method === $paymentMethod) {
                return $activeAttempt;
            }

            if ($activeAttempt?->isOpen()) {
                $activeAttempt->update(['status' => PaymentAttemptStatus::SUPERSEDED]);
                $superseded = $activeAttempt;
            }

            $nextSequence = (int) PaymentAttempt::query()
                ->where('order_id', $lockedOrder->id)
                ->max('attempt_sequence') + 1;

            $attempt = PaymentAttempt::query()->create([
                'order_id' => $lockedOrder->id,
                'provider' => $provider,
                'attempt_sequence' => $nextSequence,
                'midtrans_order_id' => $lockedOrder->order_number.'-A'.$nextSequence,
                'payment_method' => $paymentMethod,
                'status' => PaymentAttemptStatus::CREATING,
                'gross_amount' => $lockedOrder->grand_total,
                'activated_at' => now(),
                'expired_at' => now()->addMinutes($this->expiryMinutes($provider)),
            ]);

            $this->start($provider, $lockedOrder, $attempt, $paymentMethod);

            $lockedOrder->update([
                'active_payment_attempt_id' => $attempt->id,
            ]);

            $this->activities->paymentStarted($lockedOrder, $paymentMethod);

            return $attempt->refresh();
        });

        if ($superseded !== null) {
            $this->settlement->cancelAtGateway($superseded);
        }

        return $attempt;
    }

    public function syncActiveAttempt(Order $order): void
    {
        $order->loadMissing('activePaymentAttempt');
        $attempt = $order->activePaymentAttempt;

        if ($attempt instanceof PaymentAttempt) {
            $this->syncAttempt($attempt);
        }
    }

    public function syncAttempt(PaymentAttempt $attempt): void
    {
        if (! $attempt->isOpen()) {
            return;
        }

        match ($attempt->provider) {
            PaymentMethods::NICEPAY => $this->nicepay->sync($attempt),
            default => $this->midtrans->sync($attempt),
        };
    }

    private function start(string $provider, Order $order, PaymentAttempt $attempt, string $paymentMethod): void
    {
        match ($provider) {
            PaymentMethods::NICEPAY => $this->nicepay->start($order, $attempt),
            default => $this->midtrans->start($order, $attempt, $paymentMethod),
        };
    }

    private function expiryMinutes(string $provider): int
    {
        return $provider === PaymentMethods::NICEPAY
            ? max(1, (int) config('services.nicepay.expiry_minutes', 1440))
            : order_expiry_minutes();
    }
}
