<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_PAID = 'order_paid';
    case ORDER_NEEDS_REVIEW = 'order_needs_review';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'Pesanan Baru',
            self::ORDER_PAID => 'Pembayaran Diterima',
            self::ORDER_NEEDS_REVIEW => 'Perlu Ditinjau',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'bell',
            self::ORDER_PAID => 'check-circle',
            self::ORDER_NEEDS_REVIEW => 'exclamation-triangle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'amber',
            self::ORDER_PAID => 'emerald',
            self::ORDER_NEEDS_REVIEW => 'rose',
        };
    }
}
