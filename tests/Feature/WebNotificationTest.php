<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderPaidNotification;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebNotificationTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();        // jangan kirim email Brevo sungguhan
        Notification::fake(); // tangkap notifikasi database untuk assertion
        $this->service = app(OrderService::class);
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
            'original_price' => 100000,
            'promo_price' => null,
        ]);
    }

    private function payload(Product $product, string $email = 'budi@email.com'): array
    {
        return [
            'customer' => ['name' => 'Budi', 'email' => $email, 'phone' => '0812'],
            'shipping' => [
                'destination_id' => 17473,
                'destination_label' => 'JATINEGARA, CAKUNG, JAKARTA TIMUR',
                'address' => 'Jl. Mesin 1',
                'courier' => 'jne',
                'cost' => 20000,
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'voucher_code' => null,
        ];
    }

    public function test_pesanan_baru_menotifikasi_admin_dan_pelanggan_yang_punya_akun(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'budi@email.com']);

        $this->service->checkout($this->payload($this->product()), 'key-web-1');

        Notification::assertSentTo($admin, NewOrderNotification::class);
        Notification::assertSentTo($customer, NewOrderNotification::class);
    }

    public function test_pesanan_baru_dari_tamu_hanya_menotifikasi_admin(): void
    {
        $admin = User::factory()->admin()->create();
        // Pembeli tamu: tidak ada akun dengan email tersebut.

        $this->service->checkout($this->payload($this->product(), 'tamu@email.com'), 'key-web-2');

        Notification::assertSentTo($admin, NewOrderNotification::class);
        Notification::assertSentTimes(NewOrderNotification::class, 1);
    }

    public function test_pelunasan_menotifikasi_pelanggan_saja_bukan_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'budi@email.com']);

        $order = $this->service->checkout($this->payload($this->product()), 'key-web-3');
        $this->service->markAsPaid($order, 'Admin Gudang');

        Notification::assertSentTo($customer, OrderPaidNotification::class);
        Notification::assertNotSentTo($admin, OrderPaidNotification::class);
    }

    public function test_checkout_tanpa_admin_tidak_menimbulkan_error(): void
    {
        // Tidak ada admin & pembeli tamu => tidak ada penerima.
        $order = $this->service->checkout($this->payload($this->product(), 'tamu@email.com'), 'key-web-4');

        $this->assertNotNull($order->order_number);
        Notification::assertNothingSent();
    }
}
