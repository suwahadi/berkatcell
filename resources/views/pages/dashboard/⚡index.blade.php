<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] #[Layout('layouts::app')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        $user = auth()->user();

        return [
            'sukses' => Order::ownedBy($user)->where('status', OrderStatus::PAID)->count(),
            'total' => Order::ownedBy($user)->count(),
            'selesai' => Order::ownedBy($user)->where('status', OrderStatus::SHIPPED)->count(),
        ];
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return Order::ownedBy(auth()->user())->latest()->take(10)->get();
    }
}; ?>

@php $firstName = explode(' ', trim(auth()->user()->name))[0] ?: auth()->user()->name; @endphp

<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-zinc-900 px-6 py-7 text-white sm:px-8 sm:py-8">
        <div class="pointer-events-none absolute -right-12 -top-16 size-56 rounded-full bg-amber-600/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 right-24 size-48 rounded-full bg-amber-500/10 blur-3xl"></div>

        <div class="relative">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-amber-400">
                <span class="size-1.5 rounded-full bg-amber-400"></span> Dashboard
            </span>
            <h1 class="mt-4 text-2xl font-extrabold tracking-tight sm:text-3xl">Halo, {{ $firstName }} 👋</h1>
            <p class="mt-1.5 max-w-md text-sm leading-relaxed text-zinc-300">Pantau status pesanan dan produk favorit Anda dari satu tempat.</p>

            <div class="mt-6 flex flex-wrap gap-2.5">
                <a href="{{ route('products.index') }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-full bg-amber-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-amber-700">
                    <flux:icon.squares-2x2 class="size-4" /> Belanja Lagi
                </a>
                <a href="{{ route('dashboard.orders.index') }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-full bg-white/10 px-5 py-2.5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/20">
                    <flux:icon.clipboard-document-list class="size-4" /> Lihat Transaksi
                </a>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.stat-card label="Pesanan Sukses" :value="number_format($this->stats['sukses'], 0, ',', '.')" icon="check-badge" tone="emerald" />
        <x-dashboard.stat-card label="Total Pesanan" :value="number_format($this->stats['total'], 0, ',', '.')" icon="shopping-bag" tone="sky" />
        <x-dashboard.stat-card label="Pesanan Selesai" :value="number_format($this->stats['selesai'], 0, ',', '.')" icon="truck" tone="amber" />
    </section>

    <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div>
                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Pesanan Terakhir</h2>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">10 transaksi terbaru Anda</p>
            </div>
            <a href="{{ route('dashboard.orders.index') }}" wire:navigate
               class="inline-flex shrink-0 items-center gap-1 rounded-full border border-zinc-200 px-3.5 py-1.5 text-xs font-bold text-zinc-700 transition hover:border-zinc-900 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:text-white">
                Lihat Semua <flux:icon.arrow-right class="size-3.5" />
            </a>
        </div>

        @forelse ($this->recentOrders as $order)
            <a href="{{ route('dashboard.orders.show', $order->order_number) }}" wire:navigate
               class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3.5 transition last:border-b-0 hover:bg-zinc-50 dark:border-zinc-800/80 dark:hover:bg-zinc-800/50">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <flux:icon.receipt-percent class="size-[18px]" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-mono text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $order->order_number }}</p>
                        <p class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ tanggal_id($order->created_at) }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="hidden font-mono text-sm font-bold text-zinc-900 sm:inline dark:text-white">{{ rupiah($order->grand_total) }}</span>
                    <x-order-status-badge :status="$order->status" />
                </div>
            </a>
        @empty
            <div class="flex flex-col items-center gap-3 px-5 py-14 text-center">
                <span class="grid size-14 place-items-center rounded-2xl bg-zinc-100 text-zinc-300 dark:bg-zinc-800 dark:text-zinc-600">
                    <flux:icon.shopping-bag class="size-7" />
                </span>
                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Belum ada pesanan</p>
                <p class="-mt-1 text-xs text-zinc-400 dark:text-zinc-500">Yuk mulai belanja produk pilihan Anda.</p>
                <a href="{{ route('products.index') }}" wire:navigate
                   class="mt-1 inline-flex items-center gap-2 rounded-full bg-amber-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-amber-700">
                    <flux:icon.squares-2x2 class="size-4" /> Jelajahi Katalog
                </a>
            </div>
        @endforelse
    </section>
</div>
