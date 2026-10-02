<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Order;
use Illuminate\Notifications\Notification;

final class PaidAfterCancelNotification extends Notification
{
    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => NotificationType::ORDER_NEEDS_REVIEW->value,
            'order_number' => $this->order->order_number,
            'title' => 'Pembayaran masuk untuk pesanan yang dibatalkan',
            'message' => 'Pesanan '.$this->order->order_number.' dibayar setelah dibatalkan. Stok dan kuota voucher sudah dikembalikan. Tinjau sebelum mengirim atau refund.',
        ];
    }
}
