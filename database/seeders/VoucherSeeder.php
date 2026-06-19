<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $vouchers = [
            [
                'code' => 'BERKAT10',
                'discount_type' => VoucherType::PERCENTAGE,
                'discount_value' => 10,
                'min_purchase' => 2000000,
                'max_usage' => 100,
            ],
            [
                'code' => 'ONGKIR50K',
                'discount_type' => VoucherType::FIXED,
                'discount_value' => 50000,
                'min_purchase' => 1500000,
                'max_usage' => 0,
            ],
            [
                'code' => 'GADGET15',
                'discount_type' => VoucherType::PERCENTAGE,
                'discount_value' => 15,
                'min_purchase' => 4000000,
                'max_usage' => 50,
            ],
        ];

        foreach ($vouchers as $data) {
            Voucher::query()->updateOrCreate(
                ['code' => $data['code']],
                [...$data, 'used_count' => 0, 'valid_until' => now()->addDays(60)],
            );
        }
    }
}
