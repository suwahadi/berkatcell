<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\WishlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_persists_to_database_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock()->create();
        $this->actingAs($user);

        $service = app(WishlistService::class);

        $this->assertTrue($service->toggle($product->id));
        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
        $this->assertTrue($service->has($product->id));
        $this->assertSame(1, $service->count());

        $this->assertFalse($service->toggle($product->id));
        $this->assertDatabaseMissing('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_guest_uses_session_not_database(): void
    {
        $product = Product::factory()->inStock()->create();

        $service = app(WishlistService::class);
        $service->toggle($product->id);

        $this->assertTrue($service->has($product->id));
        $this->assertDatabaseMissing('wishlists', ['product_id' => $product->id]);
    }

    public function test_guest_session_wishlist_merges_into_account_on_login(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock()->create();

        $this->withSession(['wishlist' => [$product->id]])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
    }
}
