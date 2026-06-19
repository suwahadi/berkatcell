<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Spindle Motor', 'Stepper Driver', 'Ball Screw', 'Linear Guide Rail',
            'CNC Router Bit', 'Servo Motor', 'VFD Inverter', 'Proximity Sensor',
            'Collet Chuck ER20', 'Timing Belt', 'Coupling Flexible', 'Limit Switch',
        ]).' '.strtoupper(fake()->bothify('##??'));

        $original = fake()->numberBetween(150, 5000) * 1000;
        $onPromo = fake()->boolean(40);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'sku' => 'JW-'.strtoupper(fake()->unique()->bothify('???-#####')),
            'original_price' => $original,
            'promo_price' => $onPromo ? (int) ($original * fake()->randomFloat(2, 0.6, 0.9)) : null,
            'description' => fake()->paragraphs(3, true),
            'weight' => fake()->numberBetween(100, 8000),
            'stock' => fake()->numberBetween(0, 120),
            'is_active' => fake()->boolean(90),
            'badge' => fake()->optional(0.3)->randomElement(['new', 'hot']),
        ];
    }

    public function inStock(int $stock = 50): static
    {
        return $this->state(fn (): array => ['stock' => $stock, 'is_active' => true]);
    }
}
