<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderActivityType;
use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Services\Payments\PaymentMethods;

class OrderActivityService
{
    public function __construct(
        private readonly WebNotificationService $web,
    ) {}

    public function created(Order $order): void
    {
        $this->log(
            $order,
            OrderActivityType::CREATED,
            $order->customer_name,
            'Pesanan dibuat oleh '.$order->customer_name.'.',
        );

        SendBrevoEmail::dispatch($order, 'new_order');

        $this->web->orderCreated($order);
    }

    public function paymentStarted(Order $order, string $method): void
    {
        $label = PaymentMethods::name($method);

        $this->log(
            $order,
            OrderActivityType::PAYMENT_STARTED,
            'Pelanggan',
            'Memulai pembayaran via '.$label.'.',
            ['method' => $method],
        );
    }

    public function paid(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::PAID,
            $actor ?? 'Sistem',
            'Pembayaran diterima.',
        );

        SendBrevoEmail::dispatch($order, 'payment_paid');

        $this->web->orderPaid($order);
    }

    public function shipped(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::SHIPPED,
            $actor ?? 'Sistem',
            'Pesanan ditandai telah dikirim.',
        );
    }

    public function cancelled(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::CANCELLED,
            $actor ?? 'Sistem',
            'Pesanan dibatalkan; stok & kuota voucher dikembalikan.',
        );
    }

    public function paymentFailed(Order $order, string $status, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::PAYMENT_FAILED,
            $actor ?? PaymentMethods::actorFor(PaymentMethods::MIDTRANS),
            'Pembayaran gagal/kedaluwarsa ('.$status.').',
            ['transaction_status' => $status],
        );
    }

    /**
     * Pembatalan sudah mengembalikan stok dan kuota voucher, jadi pesanan yang lunas
     * setelah dibatalkan tidak boleh langsung dikirim tanpa diperiksa admin.
     */
    public function paidAfterCancel(Order $order, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::NEEDS_REVIEW,
            $actor ?? 'Sistem',
            'Pembayaran masuk setelah pesanan dibatalkan. Stok dan kuota voucher sudah dikembalikan; tinjau sebelum mengirim atau refund.',
        );

        $this->web->paidAfterCancel($order);
    }

    public function paymentReversed(Order $order, string $reason, ?string $actor = null): void
    {
        $this->log(
            $order,
            OrderActivityType::PAYMENT_FAILED,
            $actor ?? PaymentMethods::actorFor(PaymentMethods::MIDTRANS),
            'Pembayaran dibatalkan di penyedia ('.$reason.'). Perlu ditinjau admin.',
            ['transaction_status' => $reason],
        );
    }

    private function log(Order $order, OrderActivityType $type, ?string $actor, string $description, array $meta = []): OrderActivity
    {
        return $order->activities()->create([
            'type' => $type,
            'description' => $description,
            'actor' => $actor,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
