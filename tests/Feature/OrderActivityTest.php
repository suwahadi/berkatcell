<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderActivityType;
use App\Jobs\SendBrevoEmail;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderActivityTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake(); // notifikasi in-web bersifat sinkron; jaga test ini tetap fokus
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

    private function payload(Product $product, int $qty = 2): array
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
            'voucher_code' => null,
        ];
    }

    public function test_checkout_mencatat_aktivitas_dibuat_dan_kirim_email(): void
    {
        $order = $this->service->checkout($this->payload($this->product()), 'key-act-1');

        $activity = $order->activities()->first();
        $this->assertSame(OrderActivityType::CREATED, $activity->type);
        $this->assertSame('Budi', $activity->actor);

        Queue::assertPushed(SendBrevoEmail::class, fn (SendBrevoEmail $job): bool => $job->event === 'new_order');
    }

    public function test_mark_paid_mencatat_aktivitas_lunas_dengan_aktor(): void
    {
        $order = $this->service->checkout($this->payload($this->product()), 'key-act-2');

        $this->service->markAsPaid($order, 'Admin Gudang');

        $paid = $order->activities()->where('type', OrderActivityType::PAID->value)->first();
        $this->assertNotNull($paid);
        $this->assertSame('Admin Gudang', $paid->actor);

        Queue::assertPushed(SendBrevoEmail::class, fn (SendBrevoEmail $job): bool => $job->event === 'payment_paid');
    }

    public function test_mark_shipped_dan_cancel_tercatat(): void
    {
        $shippedOrder = $this->service->checkout($this->payload($this->product()), 'key-act-ship');
        $this->service->markAsPaid($shippedOrder, 'Admin');
        $this->service->markAsShipped($shippedOrder, 'Admin');
        $this->assertTrue($shippedOrder->activities()->where('type', OrderActivityType::SHIPPED->value)->exists());

        $cancelOrder = $this->service->checkout($this->payload($this->product()), 'key-act-cancel');
        $this->service->cancel($cancelOrder, 'Admin');
        $this->assertTrue($cancelOrder->activities()->where('type', OrderActivityType::CANCELLED->value)->exists());
    }

    public function test_mark_paid_idempoten_tidak_menduplikasi_aktivitas(): void
    {
        $order = $this->service->checkout($this->payload($this->product()), 'key-act-idem');

        $this->service->markAsPaid($order, 'Admin');
        $this->service->markAsPaid($order, 'Admin');

        $this->assertSame(1, $order->activities()->where('type', OrderActivityType::PAID->value)->count());
    }
}
