<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutPickupTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::storefront.checkout';

    private const STORE_ADDRESS = 'Jl. Rw. Bebek II No.08, Penjaringan, Jakarta Utara';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        SettingService::set('site_address', self::STORE_ADDRESS);
        SettingService::set('origin_district_id', '73642');
    }

    private function fillCart(int $price = 250000): Product
    {
        $product = Product::factory()->inStock()->create([
            'original_price' => $price,
            'promo_price' => null,
            'weight' => 1000,
        ]);
        app(CartService::class)->add($product->id);

        return $product;
    }

    public function test_pickup_membuat_order_tanpa_ongkir_dan_tanpa_memanggil_rajaongkir(): void
    {
        // Setiap panggilan HTTP keluar akan menggagalkan assertNothingSent di bawah.
        Http::fake();
        $this->fillCart(250000);

        Livewire::test(self::COMPONENT)
            ->set('shipping_mode', 'pickup')
            ->set('customer_name', 'Demo Pickup')
            ->set('customer_email', 'demo@example.test')
            ->set('customer_phone', '081234567890')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame('pickup', $order->shipping_courier);
        $this->assertSame(0, $order->shipping_cost);
        $this->assertSame(self::STORE_ADDRESS, $order->shipping_address);
        $this->assertSame(73642, (int) $order->shipping_district_id);
        $this->assertSame(250000, $order->grand_total, 'Total pickup = subtotal, tanpa ongkir.');

        // Inti fiturnya: kalkulasi ongkir benar-benar dilewati, bukan sekadar diabaikan.
        Http::assertNothingSent();
    }

    public function test_pickup_tidak_mewajibkan_alamat_wilayah_dan_kurir(): void
    {
        Http::fake();
        $this->fillCart();

        Livewire::test(self::COMPONENT)
            ->set('shipping_mode', 'pickup')
            ->set('customer_name', 'Demo Pickup')
            ->set('customer_email', 'demo@example.test')
            ->set('customer_phone', '081234567890')
            ->call('placeOrder')
            ->assertHasNoErrors(['shipping_address', 'destinationId', 'courier', 'shipping_cost']);
    }

    public function test_mode_kirim_tetap_mewajibkan_wilayah_kurir_dan_ongkir(): void
    {
        Http::fake();
        $this->fillCart();

        Livewire::test(self::COMPONENT)
            ->set('shipping_mode', 'delivery')
            ->set('customer_name', 'Demo Kirim')
            ->set('customer_email', 'demo@example.test')
            ->set('customer_phone', '081234567890')
            ->call('placeOrder')
            ->assertHasErrors(['shipping_address', 'destinationId', 'courier', 'shipping_cost']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_berpindah_ke_pickup_membuang_ongkir_yang_sudah_terpilih(): void
    {
        Http::fake();
        $this->fillCart();

        Livewire::test(self::COMPONENT)
            ->set('destinationId', 17673)
            ->set('courier', 'jne')
            ->set('shipping_cost', 22000)
            ->set('shipping_mode', 'pickup')
            ->assertSet('shipping_cost', 0)
            ->assertSet('courier', '')
            ->assertSet('destinationId', null);
    }

    public function test_label_pengiriman_dan_status_menyesuaikan_untuk_pickup(): void
    {
        $order = Order::factory()->create([
            'shipping_courier' => 'pickup',
            'shipping_service' => null,
            'shipping_cost' => 0,
            'status' => OrderStatus::SHIPPED,
        ]);

        $this->assertTrue($order->isPickup());
        $this->assertSame('Ambil di Toko', $order->shippingCourierName());
        $this->assertSame('Sudah Diambil', $order->statusLabel());
    }

    public function test_order_kurir_biasa_tidak_terpengaruh_label_pickup(): void
    {
        $order = Order::factory()->create([
            'shipping_courier' => 'jne',
            'shipping_service' => 'REG',
            'status' => OrderStatus::SHIPPED,
        ]);

        $this->assertFalse($order->isPickup());
        $this->assertSame('JNE REG', $order->shippingCourierName());
        $this->assertSame('Dikirim', $order->statusLabel());
    }
}
