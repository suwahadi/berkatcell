<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

class VariantFactory extends Factory
{
    protected $model = Variant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement([
                '100mm', '200mm', 'Spindle 2.2kW', 'Spindle 3.5kW',
                '220V', '380V', 'NEMA 23', 'NEMA 34', 'Diameter 6mm', 'Diameter 8mm',
            ]),
            'sku' => 'JW-'.strtoupper(fake()->unique()->bothify('???-#####')),
            'price' => fake()->numberBetween(50, 4000) * 1000,
            'promo_price' => null,
            'stock' => fake()->numberBetween(0, 60),
            'weight' => fake()->numberBetween(100, 7000),
        ];
    }
}
