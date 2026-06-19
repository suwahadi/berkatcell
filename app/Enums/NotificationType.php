<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_PAID = 'order_paid';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'Pesanan Baru',
            self::ORDER_PAID => 'Pembayaran Diterima',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'bell',
            self::ORDER_PAID => 'check-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'amber',
            self::ORDER_PAID => 'emerald',
        };
    }
}
