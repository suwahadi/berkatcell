<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutPrefillTest extends TestCase
{
    use RefreshDatabase;

    private function fillCart(): void
    {
        $product = Product::factory()->inStock()->create(['original_price' => 100000, 'weight' => 1000]);
        app(CartService::class)->add($product->id);
    }

    public function test_checkout_prefills_customer_data_from_logged_in_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
        ]);
        $this->fillCart();

        Livewire::actingAs($user)
            ->test('pages::storefront.checkout')
            ->assertSet('customer_name', 'Budi Santoso')
            ->assertSet('customer_email', 'budi@example.com')
            ->assertSet('customer_phone', '081234567890');
    }

    public function test_checkout_phone_falls_back_to_last_order_when_user_has_none(): void
    {
        $user = User::factory()->create(['phone' => null]);
        Order::factory()->create(['customer_email' => $user->email, 'customer_phone' => '089876543210']);
        $this->fillCart();

        Livewire::actingAs($user)
            ->test('pages::storefront.checkout')
            ->assertSet('customer_phone', '089876543210');
    }

    public function test_checkout_fields_are_empty_for_guest(): void
    {
        $this->fillCart();

        Livewire::test('pages::storefront.checkout')
            ->assertSet('customer_name', '')
            ->assertSet('customer_email', '');
    }
}
