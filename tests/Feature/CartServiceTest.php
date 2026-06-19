<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\Variant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cart = new CartService;
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'stock' => 50,
            'original_price' => 100000,
            'promo_price' => null,
            'weight' => 1000,
        ], $attributes));
    }

    public function test_menambah_item_menambah_jumlah(): void
    {
        $product = $this->product();

        $this->cart->add($product->id, 2);

        $this->assertSame(2, $this->cart->count());
        $this->assertSame(200000, $this->cart->subtotal());
    }

    public function test_menambah_produk_sama_menggabungkan_kuantitas(): void
    {
        $product = $this->product();

        $this->cart->add($product->id, 2);
        $this->cart->add($product->id, 3);

        $this->assertSame(5, $this->cart->count());
        $this->assertCount(1, $this->cart->items());
    }

    public function test_melebihi_stok_ditolak(): void
    {
        $product = $this->product(['stock' => 3]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Stok barang tidak mencukupi');

        $this->cart->add($product->id, 4);
    }

    public function test_produk_nonaktif_ditolak(): void
    {
        $product = $this->product(['is_active' => false]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Produk tidak tersedia.');

        $this->cart->add($product->id, 1);
    }

    public function test_memakai_harga_promo_sebagai_harga_efektif(): void
    {
        $product = $this->product(['original_price' => 100000, 'promo_price' => 80000]);

        $this->cart->add($product->id, 2);

        $this->assertSame(160000, $this->cart->subtotal());
    }

    public function test_total_berat_dihitung(): void
    {
        $product = $this->product(['weight' => 1500]);

        $this->cart->add($product->id, 3);

        $this->assertSame(4500, $this->cart->totalWeight());
    }

    public function test_hapus_dan_kosongkan(): void
    {
        $product = $this->product();
        $this->cart->add($product->id, 1);
        $key = $this->cart->lineKey($product->id, null);

        $this->cart->remove($key);
        $this->assertTrue($this->cart->isEmpty());

        $this->cart->add($product->id, 1);
        $this->cart->clear();
        $this->assertTrue($this->cart->isEmpty());
    }

    public function test_payload_checkout_terbentuk(): void
    {
        $product = $this->product();
        $this->cart->add($product->id, 2);

        $items = $this->cart->toCheckoutItems();

        $this->assertSame([[
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 2,
        ]], $items);
    }

    public function test_produk_bervarian_wajib_pilih_varian(): void
    {
        $product = $this->product();
        Variant::factory()->create(['product_id' => $product->id]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('pilih varian');

        $this->cart->add($product->id, 1, null);
    }

    public function test_harga_stok_berat_mengikuti_varian(): void
    {
        // Harga/stok/berat produk sengaja berbeda agar terbukti varian yang dipakai.
        $product = $this->product(['original_price' => 999999, 'stock' => 0, 'weight' => 9999]);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 150000,
            'promo_price' => null,
            'stock' => 10,
            'weight' => 500,
        ]);

        $this->cart->add($product->id, 2, $variant->id);

        $this->assertSame(300000, $this->cart->subtotal());
        $this->assertSame(1000, $this->cart->totalWeight());
    }
}
