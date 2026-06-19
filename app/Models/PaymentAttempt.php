<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'attempt_sequence',
        'midtrans_order_id',
        'payment_method',
        'status',
        'gross_amount',
        'snap_token',
        'redirect_url',
        'midtrans_transaction_id',
        'midtrans_transaction_status',
        'midtrans_fraud_status',
        'snap_request_payload',
        'snap_response_payload',
        'latest_notification_payload',
        'activated_at',
        'paid_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'attempt_sequence' => 'integer',
            'gross_amount' => 'integer',
            'snap_request_payload' => 'array',
            'snap_response_payload' => 'array',
            'latest_notification_payload' => 'array',
            'activated_at' => 'datetime',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status instanceof PaymentAttemptStatus && $this->status->isOpen();
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    public function vaNumber(): ?string
    {
        $payload = $this->latest_notification_payload ?? [];

        $va = $payload['va_numbers'][0]['va_number'] ?? null;

        return $va ?? ($payload['permata_va_number'] ?? null);
    }

    public function bankLabel(): ?string
    {
        $payload = $this->latest_notification_payload ?? [];

        $bank = $payload['va_numbers'][0]['bank'] ?? null;

        if ($bank === null && isset($payload['permata_va_number'])) {
            $bank = 'permata';
        }

        return $bank !== null ? strtoupper((string) $bank) : null;
    }

    public function billKey(): ?string
    {
        return $this->latest_notification_payload['bill_key'] ?? null;
    }

    public function billerCode(): ?string
    {
        return $this->latest_notification_payload['biller_code'] ?? null;
    }
}
