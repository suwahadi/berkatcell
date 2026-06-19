<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'type',
        'description',
        'actor',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderActivityType::class,
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
