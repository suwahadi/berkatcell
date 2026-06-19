<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case CREATING = 'creating';
    case PENDING = 'pending';
    case PAID = 'paid';
    case DENIED = 'denied';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case SUPERSEDED = 'superseded';
    case FAILED = 'failed';

    public static function open(): array
    {
        return [self::CREATING, self::PENDING];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }
}
