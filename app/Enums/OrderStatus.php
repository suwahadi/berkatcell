<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'menunggu_pembayaran';
    case PAID = 'lunas';
    case SHIPPED = 'dikirim';
    case CANCELLED = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Pembayaran',
            self::PAID => 'Lunas',
            self::SHIPPED => 'Dikirim',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::PAID => 'emerald',
            self::SHIPPED => 'sky',
            self::CANCELLED => 'rose',
        };
    }
}
