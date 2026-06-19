<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Order;
use Illuminate\Notifications\Notification;

final class OrderPaidNotification extends Notification
{
    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => NotificationType::ORDER_PAID->value,
            'order_number' => $this->order->order_number,
            'title' => 'Pembayaran diterima',
            'message' => 'Pembayaran pesanan '.$this->order->order_number.' berhasil. Terima kasih!',
        ];
    }
}
