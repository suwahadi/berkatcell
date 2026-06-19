<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\VoucherType;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Voucher;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderService::class);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'stock' => 10,
            'original_price' => 100000,
            'promo_price' => null,
        ], $attributes));
    }

    private function payload(Product $product, int $qty = 2, ?string $voucher = null): array
    {
        return [
            'customer' => ['name' => 'Budi', 'email' => 'budi@email.com', 'phone' => '0812'],
            'shipping' => [
                'destination_id' => 17473,
                'destination_label' => 'JATINEGARA, CAKUNG, JAKARTA TIMUR',
                'address' => 'Jl. Mesin 1',
                'courier' => 'jne',
                'cost' => 20000,
            ],
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
            'voucher_code' => $voucher,
        ];
    }

    public function test_checkout_membuat_pesanan_dan_memotong_stok(): void
    {
        $product = $this->product(['stock' => 10, 'original_price' => 100000]);

        $order = $this->service->checkout($this->payload($product, 2), 'key-1');

        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(200000, $order->subtotal);
        $this->assertSame(220000, $order->grand_total); // + ongkir 20rb
        $this->assertSame(17473, $order->shipping_district_id);
        $this->assertSame('JATINEGARA, CAKUNG, JAKARTA TIMUR', $order->shipping_destination_label);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertCount(1, $order->items);
    }

    public function test_idempotency_mencegah_pesanan_ganda(): void
    {
        $product = $this->product(['stock' => 10]);
        $payload = $this->payload($product, 2);

        $first = $this->service->checkout($payload, 'same-key');
        $second = $this->service->checkout($payload, 'same-key');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $product->fresh()->stock); // stok hanya terpotong sekali
    }

    public function test_stok_tidak_cukup_dilempar(): void
    {
        $product = $this->product(['stock' => 1]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Stok barang tidak mencukupi');

        $this->service->checkout($this->payload($product, 5), 'key-stock');

        $this->assertSame(0, Order::count());
    }

    public function test_voucher_diklaim_saat_checkout(): void
    {
        $product = $this->product(['stock' => 10, 'original_price' => 100000]);
        Voucher::factory()->create([
            'code' => 'DISKON10',
            'discount_type' => VoucherType::PERCENTAGE,
            'discount_value' => 10,
            'min_purchase' => 0,
            'max_usage' => 100,
            'used_count' => 0,
            'valid_until' => now()->addDay(),
        ]);

        $order = $this->service->checkout($this->payload($product, 2, 'DISKON10'), 'key-voucher');

        $this->assertSame(20000, $order->discount_amount); // 10% dari 200rb
        $this->assertSame(200000, $order->grand_total); // 200rb - 20rb + 20rb ongkir
        $this->assertSame(1, Voucher::where('code', 'DISKON10')->value('used_count'));
    }

    public function test_pembatalan_mengembalikan_stok_dan_kuota_voucher(): void
    {
        $product = $this->product(['stock' => 10]);
        Voucher::factory()->create([
            'code' => 'BACK',
            'discount_type' => VoucherType::FIXED,
            'discount_value' => 10000,
            'min_purchase' => 0,
            'max_usage' => 100,
            'used_count' => 0,
            'valid_until' => now()->addDay(),
        ]);

        $order = $this->service->checkout($this->payload($product, 3, 'BACK'), 'key-cancel');
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, Voucher::where('code', 'BACK')->value('used_count'));

        $this->service->cancel($order);

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock); // stok kembali
        $this->assertSame(0, Voucher::where('code', 'BACK')->value('used_count')); // kuota kembali
    }

    public function test_transisi_status_lunas_lalu_dikirim(): void
    {
        $product = $this->product(['stock' => 10]);
        $order = $this->service->checkout($this->payload($product), 'key-status');

        $this->service->markAsPaid($order);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);

        $this->service->markAsShipped($order);
        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);
    }

    public function test_tidak_bisa_kirim_pesanan_belum_lunas(): void
    {
        $product = $this->product(['stock' => 10]);
        $order = $this->service->checkout($this->payload($product), 'key-ship-fail');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('lunas');

        $this->service->markAsShipped($order);
    }

    public function test_tidak_bisa_batalkan_pesanan_terkirim(): void
    {
        $product = $this->product(['stock' => 10]);
        $order = $this->service->checkout($this->payload($product), 'key-cancel-fail');
        $this->service->markAsPaid($order);
        $this->service->markAsShipped($order);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('dikirim');

        $this->service->cancel($order);
    }

    public function test_checkout_produk_bervarian_memotong_stok_varian(): void
    {
        // Harga/stok produk berbeda dari varian agar terbukti varian yang dipakai.
        $product = $this->product(['stock' => 0, 'original_price' => 999999]);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 100000,
            'promo_price' => null,
            'stock' => 10,
            'weight' => 500,
        ]);

        $payload = $this->payload($product, 3);
        $payload['items'][0]['variant_id'] = $variant->id;

        $order = $this->service->checkout($payload, 'key-variant');

        $this->assertSame(7, $variant->fresh()->stock);      // stok varian terpotong
        $this->assertSame(0, $product->fresh()->stock);      // stok produk tak tersentuh
        $this->assertSame(100000, $order->items->first()->price); // snapshot harga varian
        $this->assertSame($variant->id, $order->items->first()->variant_id);
    }

    public function test_produk_bervarian_tanpa_varian_ditolak(): void
    {
        $product = $this->product(['stock' => 0]);
        Variant::factory()->create(['product_id' => $product->id, 'stock' => 10]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('pilih varian');

        // Payload tanpa variant_id untuk produk bervarian.
        $this->service->checkout($this->payload($product, 1), 'key-novariant');
    }

    public function test_cancel_mengembalikan_stok_varian(): void
    {
        $product = $this->product(['stock' => 0, 'original_price' => 999999]);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 100000,
            'promo_price' => null,
            'stock' => 10,
            'weight' => 500,
        ]);

        $payload = $this->payload($product, 3);
        $payload['items'][0]['variant_id'] = $variant->id;

        $order = $this->service->checkout($payload, 'key-varcancel');
        $this->assertSame(7, $variant->fresh()->stock);

        $this->service->cancel($order);
        $this->assertSame(10, $variant->fresh()->stock); // stok varian kembali
    }
}
