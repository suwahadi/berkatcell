<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ROUTES = [
        'admin.dashboard',
        'admin.orders.index',
        'admin.products.index',
        'admin.products.create',
        'admin.categories.index',
        'admin.vouchers.index',
        'admin.pages.index',
        'admin.settings',
        'admin.notifications.index',
    ];

    public function test_guests_are_redirected_to_login_from_admin(): void
    {
        foreach (self::ADMIN_ROUTES as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_customers_are_forbidden_from_every_admin_route(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        foreach (self::ADMIN_ROUTES as $name) {
            $this->actingAs($customer)->get(route($name))
                ->assertForbidden(); // 403
        }
    }

    public function test_admins_can_access_every_admin_route(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (self::ADMIN_ROUTES as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
    }

    public function test_customer_is_forbidden_from_admin_detail_routes(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->create();
        $product = Product::factory()->inStock()->create();

        $this->actingAs($customer)->get(route('admin.orders.show', $order->order_number))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.products.edit', $product))->assertForbidden();
    }

    public function test_admin_can_access_admin_detail_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();
        $product = Product::factory()->inStock()->create();

        $this->actingAs($admin)->get(route('admin.orders.show', $order->order_number))->assertOk();
        $this->actingAs($admin)->get(route('admin.products.edit', $product))->assertOk();
    }

    public function test_customers_can_still_access_their_dashboard(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->actingAs($customer)->get(route('dashboard'))->assertOk();
        $this->actingAs($customer)->get(route('dashboard.orders.index'))->assertOk();
        $this->actingAs($customer)->get(route('dashboard.notifications.index'))->assertOk();
        $this->actingAs($customer)->get(route('dashboard.wishlist'))->assertOk();
    }

    public function test_admins_can_also_access_the_user_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_panel_admin_link_shows_only_for_admins_on_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Panel Admin')
            ->assertSee(route('admin.dashboard', absolute: false));

        $this->actingAs($customer)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Panel Admin');
    }

    public function test_role_helpers_and_default(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($customer->isAdmin());
        // Default factory (tanpa state) = customer.
        $this->assertSame(UserRole::CUSTOMER, $customer->role);
    }

    public function test_newly_registered_user_is_a_customer_and_cannot_reach_admin(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Citra',
            'email' => 'citra@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'citra@example.com')->firstOrFail();
        $this->assertSame(UserRole::CUSTOMER, $user->role);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }
}
