<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Services\WishlistService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Favorit')] #[Layout('layouts::app')] class extends Component {
    use InteractsWithCart;
    use InteractsWithWishlist;

    #[Computed]
    public function products(): Collection
    {
        return app(WishlistService::class)->items();
    }
}; ?>

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <nav class="flex items-center gap-2 text-xs text-zinc-400 dark:text-zinc-500">
                <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">Dashboard</a>
                <span>/</span><span class="text-zinc-600 dark:text-zinc-400">Favorit</span>
            </nav>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Produk Favorit</h1>
        </div>
        <p class="font-mono text-sm text-zinc-400 dark:text-zinc-500">{{ $this->products->count() }} item</p>
    </div>

    @if ($this->products->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-300 bg-white py-20 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <span class="grid size-16 place-items-center rounded-2xl bg-zinc-100 text-zinc-300 dark:bg-zinc-800 dark:text-zinc-600">
                <flux:icon.heart class="size-8" />
            </span>
            <h3 class="mt-4 text-lg font-bold text-zinc-900 dark:text-white">Belum ada favorit</h3>
            <p class="mt-1 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">Tekan ikon hati pada kartu produk untuk menyimpannya ke daftar favorit Anda.</p>
            <a href="{{ route('products.index') }}" wire:navigate
               class="mt-5 inline-flex items-center gap-2 rounded-full bg-amber-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-amber-700">
                <flux:icon.squares-2x2 class="size-4" /> Jelajahi Katalog
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($this->products as $product)
                <x-product-card :product="$product" :wire:key="'wish-'.$product->id" />
            @endforeach
        </div>
    @endif
</div>
