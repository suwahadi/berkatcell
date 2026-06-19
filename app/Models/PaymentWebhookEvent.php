<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'midtrans_order_id',
        'transaction_id',
        'transaction_status',
        'status_code',
        'gross_amount',
        'signature_key',
        'event_hash',
        'processing_status',
        'payload',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
