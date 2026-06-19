<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class NewOrderNotification extends Notification
{
    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin();

        return [
            'type' => NotificationType::ORDER_CREATED->value,
            'order_number' => $this->order->order_number,
            'title' => $isAdmin
                ? 'Pesanan baru '.$this->order->order_number
                : 'Pesanan berhasil dibuat',
            'message' => $isAdmin
                ? $this->order->customer_name.' membuat pesanan '.rupiah($this->order->grand_total).'.'
                : 'Pesanan '.$this->order->order_number.' menunggu pembayaran.',
        ];
    }
}
