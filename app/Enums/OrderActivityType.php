<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderActivityType: string
{
    case CREATED = 'dibuat';
    case PAYMENT_STARTED = 'pembayaran_dimulai';
    case PAID = 'lunas';
    case SHIPPED = 'dikirim';
    case CANCELLED = 'dibatalkan';
    case PAYMENT_FAILED = 'pembayaran_gagal';
    case NEEDS_REVIEW = 'perlu_ditinjau';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Pesanan Dibuat',
            self::PAYMENT_STARTED => 'Pembayaran Dimulai',
            self::PAID => 'Pembayaran Diterima',
            self::SHIPPED => 'Pesanan Dikirim',
            self::CANCELLED => 'Pesanan Dibatalkan',
            self::PAYMENT_FAILED => 'Pembayaran Gagal',
            self::NEEDS_REVIEW => 'Perlu Ditinjau',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'slate',
            self::PAYMENT_STARTED => 'amber',
            self::PAID => 'emerald',
            self::SHIPPED => 'sky',
            self::CANCELLED => 'rose',
            self::PAYMENT_FAILED => 'rose',
            self::NEEDS_REVIEW => 'amber',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'shopping-bag',
            self::PAYMENT_STARTED => 'credit-card',
            self::PAID => 'check-circle',
            self::SHIPPED => 'truck',
            self::CANCELLED => 'x-circle',
            self::PAYMENT_FAILED => 'exclamation-triangle',
            self::NEEDS_REVIEW => 'exclamation-triangle',
        };
    }
}
