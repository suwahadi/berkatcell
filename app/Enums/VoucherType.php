<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherType: string
{
    case PERCENTAGE = 'persentase';
    case FIXED = 'nominal_tetap';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Persentase',
            self::FIXED => 'Nominal Tetap',
        };
    }
}
