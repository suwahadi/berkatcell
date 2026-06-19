<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoucherType;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_usage',
        'used_count',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => VoucherType::class,
            'discount_value' => 'integer',
            'min_purchase' => 'integer',
            'max_usage' => 'integer',
            'used_count' => 'integer',
            'valid_until' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasQuotaLeft(): bool
    {
        return $this->max_usage === 0 || $this->used_count < $this->max_usage;
    }
}
