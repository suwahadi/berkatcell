<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_wishlist(): void
    {
        $this->get(route('dashboard.wishlist'))->assertRedirect(route('login'));
    }

    public function test_wishlist_shows_empty_state_when_no_items(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.wishlist'))
            ->assertOk()
            ->assertSee('Belum ada favorit');
    }

    public function test_wishlist_lists_products_saved_to_user_account(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock()->create();
        $user->wishlists()->create(['product_id' => $product->id]);

        $this->actingAs($user)
            ->get(route('dashboard.wishlist'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_wishlist_is_isolated_between_users(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $product = Product::factory()->inStock()->create();
        $owner->wishlists()->create(['product_id' => $product->id]);

        $this->actingAs($stranger)
            ->get(route('dashboard.wishlist'))
            ->assertOk()
            ->assertDontSee($product->name)
            ->assertSee('Belum ada favorit');
    }
}
