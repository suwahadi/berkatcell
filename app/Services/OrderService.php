<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Voucher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly VoucherService $voucherService,
        private readonly OrderActivityService $activities,
    ) {}

    public function checkout(array $payload, string $idempotencyKey): Order
    {
        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $lock = Cache::lock('checkout:'.$idempotencyKey, 15);

        return $lock->block(10, function () use ($payload, $idempotencyKey): Order {
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            return DB::transaction(function () use ($payload, $idempotencyKey): Order {
                return $this->persistOrder($payload, $idempotencyKey);
            });
        });
    }

    private function persistOrder(array $payload, string $idempotencyKey): Order
    {
        $items = $payload['items'] ?? [];

        if ($items === []) {
            throw new BusinessRuleException('Keranjang belanja kosong.');
        }

        $subtotal = 0;
        $lineRows = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new BusinessRuleException('Jumlah barang tidak valid.');
            }

            $product = Product::query()->find($productId);

            if ($product === null || ! $product->is_active) {
                throw new BusinessRuleException('Produk tidak tersedia.');
            }

            if ($variantId !== null) {
                $sellable = Variant::query()
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->find($variantId);

                if ($sellable === null) {
                    throw new BusinessRuleException('Varian tidak tersedia.');
                }

                $label = $product->name.' - '.$sellable->name;
            } else {
                if ($product->hasVariants()) {
                    throw new BusinessRuleException('Silakan pilih varian untuk '.$product->name.'.');
                }

                $sellable = Product::query()->lockForUpdate()->find($productId);
                $label = $product->name;
            }

            if ($sellable->stock < $quantity) {
                throw new BusinessRuleException('Stok barang tidak mencukupi untuk '.$label.'.');
            }

            $price = $sellable->effective_price;
            $lineTotal = $price * $quantity;
            $subtotal += $lineTotal;

            $sellable->decrement('stock', $quantity);

            $lineRows[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'price' => $price,
                'quantity' => $quantity,
                'total' => $lineTotal,
            ];
        }

        $voucher = null;
        $discount = 0;
        $voucherCode = $payload['voucher_code'] ?? null;

        if (filled($voucherCode)) {
            $this->voucherService->validate((string) $voucherCode, $subtotal);

            $voucher = Voucher::query()->lockForUpdate()->where('code', $voucherCode)->first();

            if ($voucher === null || ! $voucher->hasQuotaLeft()) {
                throw new BusinessRuleException('Voucher telah melampaui batas kuota penggunaan.');
            }

            $discount = $this->voucherService->calculateDiscount($voucher, $subtotal);
            $voucher->increment('used_count');
        }

        $shipping = $payload['shipping'] ?? [];
        $shippingCost = (int) ($shipping['cost'] ?? 0);
        $grandTotal = max(0, $subtotal - $discount) + $shippingCost;

        $customer = $payload['customer'] ?? [];

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => $this->generateOrderNumber(),
            'idempotency_key' => $idempotencyKey,
            'customer_name' => (string) ($customer['name'] ?? ''),
            'customer_email' => (string) ($customer['email'] ?? ''),
            'customer_phone' => (string) ($customer['phone'] ?? ''),
            'shipping_province_id' => isset($shipping['province_id']) ? (int) $shipping['province_id'] : null,
            'shipping_city_id' => isset($shipping['city_id']) ? (int) $shipping['city_id'] : null,
            'shipping_district_id' => (int) ($shipping['destination_id'] ?? $shipping['district_id'] ?? 0),
            'shipping_destination_label' => $shipping['destination_label'] ?? null,
            'shipping_address' => (string) ($shipping['address'] ?? ''),
            'shipping_courier' => (string) ($shipping['courier'] ?? ''),
            'shipping_courier_name' => $shipping['courier_name'] ?? null,
            'shipping_service' => $shipping['service'] ?? null,
            'shipping_service_label' => $shipping['service_label'] ?? null,
            'shipping_etd' => $shipping['etd'] ?? null,
            'shipping_cost' => $shippingCost,
            'voucher_id' => $voucher?->id,
            'discount_amount' => $discount,
            'subtotal' => $subtotal,
            'grand_total' => $grandTotal,
            'status' => OrderStatus::PENDING,
        ]);

        $order->items()->createMany($lineRows);

        $this->activities->created($order);

        return $order;
    }

    public function markAsPaid(Order $order, ?string $actor = null): Order
    {
        if ($order->status === OrderStatus::PAID) {
            return $order;
        }

        $order->update(['status' => OrderStatus::PAID]);

        $this->activities->paid($order, $actor);

        return $order;
    }

    public function markAsShipped(Order $order, ?string $actor = null): Order
    {
        if ($order->status === OrderStatus::SHIPPED) {
            return $order;
        }

        if ($order->status !== OrderStatus::PAID) {
            throw new BusinessRuleException('Hanya pesanan yang sudah lunas yang dapat dikirim.');
        }

        $order->update(['status' => OrderStatus::SHIPPED]);

        $this->activities->shipped($order, $actor);

        return $order;
    }

    public function cancel(Order $order, ?string $actor = null): Order
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return $order;
        }

        if ($order->status === OrderStatus::SHIPPED) {
            throw new BusinessRuleException('Pesanan yang sudah dikirim tidak dapat dibatalkan.');
        }

        DB::transaction(function () use ($order, $actor): void {
            foreach ($order->items()->get() as $item) {
                if ($item->variant_id !== null) {
                    $variant = Variant::query()->lockForUpdate()->find($item->variant_id);
                    $variant?->increment('stock', $item->quantity);
                } else {
                    $product = Product::query()->lockForUpdate()->find($item->product_id);
                    $product?->increment('stock', $item->quantity);
                }
            }

            if ($order->voucher_id !== null) {
                $voucher = Voucher::query()->lockForUpdate()->find($order->voucher_id);
                if ($voucher !== null && $voucher->used_count > 0) {
                    $voucher->decrement('used_count');
                }
            }

            $order->update(['status' => OrderStatus::CANCELLED]);

            $this->activities->cancelled($order, $actor);
        });

        return $order;
    }

    private function generateOrderNumber(): string
    {
        return 'JW-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
