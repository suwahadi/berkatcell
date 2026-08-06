<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderActivityType;
use App\Jobs\SendBrevoEmail;
use App\Models\Order;
use App\Models\OrderActivity;

class OrderActivityService
{
    private const METHOD_LABELS = [
        'gopay' => 'QRIS',
        'akulaku' => 'Akulaku PayLater',
        'bsi_va' => 'Virtual Account BSI',
        'bni_va' => 'Virtual Account BNI',
        'bri_va' => 'Virtual Account BRI',
        'echannel' => 'Mandiri Bill Payment',
        'permata_va' => 'Virtual Account Permata',
    ];

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
        $label = self::METHOD_LABELS[$method] ?? $method;

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

    public function paymentFailed(Order $order, string $status): void
    {
        $this->log(
            $order,
            OrderActivityType::PAYMENT_FAILED,
            'Midtrans (otomatis)',
            'Pembayaran gagal/kedaluwarsa ('.$status.').',
            ['transaction_status' => $status],
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
