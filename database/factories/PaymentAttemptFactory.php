<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'attempt_sequence' => 1,
            'midtrans_order_id' => 'JW-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)).'-A1',
            'payment_method' => 'bni_va',
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => fake()->numberBetween(100000, 1000000),
            'snap_token' => (string) Str::uuid(),
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/'.Str::random(20),
            'activated_at' => now(),
        ];
    }

    public function creating(): static
    {
        return $this->state(fn (): array => ['status' => PaymentAttemptStatus::CREATING]);
    }

    public function superseded(): static
    {
        return $this->state(fn (): array => ['status' => PaymentAttemptStatus::SUPERSEDED]);
    }
}
