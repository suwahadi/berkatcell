<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

use App\Enums\PaymentAttemptStatus;

class MidtransStatusMapper
{
    public function isPaid(?string $transactionStatus, ?string $fraudStatus): bool
    {
        if ($transactionStatus === 'settlement') {
            return true;
        }

        if ($transactionStatus === 'capture') {
            return $fraudStatus === null || $fraudStatus === 'accept';
        }

        return false;
    }

    public function attemptStatus(?string $transactionStatus, ?string $fraudStatus): PaymentAttemptStatus
    {
        if ($this->isPaid($transactionStatus, $fraudStatus)) {
            return PaymentAttemptStatus::PAID;
        }

        return match ($transactionStatus) {
            'pending' => PaymentAttemptStatus::PENDING,
            'capture' => PaymentAttemptStatus::PENDING,
            'expire' => PaymentAttemptStatus::EXPIRED,
            'cancel' => PaymentAttemptStatus::CANCELLED,
            'deny' => PaymentAttemptStatus::DENIED,
            'failure' => PaymentAttemptStatus::FAILED,
            default => PaymentAttemptStatus::FAILED,
        };
    }

    public function isFailureLike(?string $transactionStatus): bool
    {
        return in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true);
    }
}
