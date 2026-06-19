<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VoucherType;
use App\Exceptions\BusinessRuleException;
use App\Models\Voucher;
use Illuminate\Support\Carbon;

class VoucherService
{
    public function validate(string $code, int $subtotal): Voucher
    {
        $voucher = Voucher::query()
            ->where('code', $code)
            ->first();

        if ($voucher === null) {
            throw new BusinessRuleException('Kode voucher tidak ditemukan.');
        }

        if (Carbon::now()->greaterThanOrEqualTo($voucher->valid_until)) {
            throw new BusinessRuleException('Voucher sudah tidak berlaku.');
        }

        if (! $voucher->hasQuotaLeft()) {
            throw new BusinessRuleException('Voucher telah melampaui batas kuota penggunaan.');
        }

        if ($subtotal < $voucher->min_purchase) {
            throw new BusinessRuleException(
                'Minimal belanja '.rupiah($voucher->min_purchase).' untuk memakai voucher ini.',
            );
        }

        return $voucher;
    }

    public function calculateDiscount(Voucher $voucher, int $subtotal): int
    {
        $discount = match ($voucher->discount_type) {
            VoucherType::PERCENTAGE => (int) floor($subtotal * $voucher->discount_value / 100),
            VoucherType::FIXED => $voucher->discount_value,
        };

        return min($discount, $subtotal);
    }
}
