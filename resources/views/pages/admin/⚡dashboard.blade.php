<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard Admin')] #[Layout('layouts::admin')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        return [
            'orders' => Order::query()->count(),
            'pending' => Order::query()->where('status', OrderStatus::PENDING)->count(),
            'revenue' => (int) Order::query()->whereIn('status', [OrderStatus::PAID, OrderStatus::SHIPPED])->sum('grand_total'),
            'products' => Product::query()->where('is_active', true)->count(),
            'low_stock' => $this->lowStockQuery()->count(),
        ];
    }

    #[Computed]
    public function recentOrders()
    {
        return Order::query()->latest()->take(8)->get();
    }

    #[Computed]
    public function lowStockProducts()
    {
        return $this->lowStockQuery()->with('variants')->take(8)->get();
    }

    private function lowStockQuery()
    {
        return Product::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where(function ($simple): void {
                    $simple->whereDoesntHave('variants')->where('stock', '<=', 5);
                })->orWhereHas('variants', fn ($v) => $v->where('stock', '<=', 5));
            });
    }
}; ?>

<div>
    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Dashboard</h1>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Ringkasan operasional {{ setting('site_name', 'CV. Jajar Wayang') }}.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Total Pesanan" :value="number_format($this->stats['orders'], 0, ',', '.')" icon="shopping-bag" tone="sky" />
        <x-admin.stat-card label="Menunggu Bayar" :value="number_format($this->stats['pending'], 0, ',', '.')" icon="clock" tone="amber" />
        <x-admin.stat-card label="Pendapatan" :value="rupiah($this->stats['revenue'])" icon="banknotes" tone="emerald" />
        <x-admin.stat-card label="Stok Menipis" :value="number_format($this->stats['low_stock'], 0, ',', '.')" icon="exclamation-triangle" tone="rose" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Pesanan Terbaru</h2>
                <a href="{{ route('admin.orders.index') }}" wire:navigate class="text-xs font-bold text-amber-600 hover:text-amber-700 hover:underline dark:text-amber-400">Semua</a>
            </div>
            @if ($this->recentOrders->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-zinc-400 dark:text-zinc-500">Belum ada pesanan.</p>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->recentOrders as $order)
                        <a href="{{ route('admin.orders.show', $order->order_number) }}" wire:navigate class="flex items-center justify-between px-5 py-3.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <div class="min-w-0">
                                <p class="truncate font-mono text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $order->order_number }}</p>
                                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $order->customer_name }} &middot; {{ tanggal_id($order->created_at) }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-mono text-sm font-bold text-zinc-900 dark:text-white">{{ rupiah($order->grand_total) }}</p>
                                <x-order-status-badge :status="$order->status" class="mt-1" />
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Stok Menipis</h2>
                <a href="{{ route('admin.products.index') }}" wire:navigate class="text-xs font-bold text-amber-600 hover:text-amber-700 hover:underline dark:text-amber-400">Kelola</a>
            </div>
            @if ($this->lowStockProducts->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-zinc-400 dark:text-zinc-500">Semua stok aman.</p>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->lowStockProducts as $product)
                        <div class="flex items-center justify-between px-5 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $product->name }}</p>
                                <p class="font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ $product->sku }}</p>
                            </div>
                            <span class="shrink-0 rounded-md border-l-4 border-amber-500 bg-amber-50 px-3 py-1 font-mono text-sm font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">{{ $product->hasVariants() ? $product->variants->min('stock') : $product->stock }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
