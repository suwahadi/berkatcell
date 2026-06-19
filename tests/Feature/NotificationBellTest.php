<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_lonceng_menampilkan_notifikasi_terbaru(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();
        $admin->notify(new NewOrderNotification($order));

        $this->assertSame(1, $admin->unreadNotifications()->count());

        $this->actingAs($admin);

        Livewire::test(NotificationBell::class)
            ->assertSee('Pesanan baru')
            ->assertSee($order->order_number);
    }

    public function test_tandai_semua_dibaca_mengosongkan_badge(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();
        $admin->notify(new NewOrderNotification($order));

        $this->actingAs($admin);

        Livewire::test(NotificationBell::class)
            ->call('markAllRead')
            ->assertDispatched('notifications-updated');

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
    }

    public function test_sidebar_render_dengan_badge_count_notifikasi(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();
        $admin->notify(new NewOrderNotification($order));

        // Memuat halaman penuh => sidebar (dengan :badge count) ikut dirender.
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }
}
