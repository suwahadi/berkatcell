<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        $type = fake()->randomElement(VoucherType::cases());

        return [
            'code' => strtoupper(fake()->unique()->bothify('?????##')),
            'discount_type' => $type,
            'discount_value' => $type === VoucherType::PERCENTAGE
                ? fake()->numberBetween(5, 30)
                : fake()->numberBetween(10, 100) * 1000,
            'min_purchase' => fake()->randomElement([0, 100000, 250000, 500000]),
            'max_usage' => fake()->randomElement([0, 50, 100]),
            'used_count' => 0,
            'valid_until' => now()->addDays(fake()->numberBetween(7, 90)),
        ];
    }
}
