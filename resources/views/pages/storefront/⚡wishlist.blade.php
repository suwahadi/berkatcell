<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Services\WishlistService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Favorit Saya')] #[Layout('layouts::storefront')] class extends Component {
    use InteractsWithCart;
    use InteractsWithWishlist;

    #[Computed]
    public function products()
    {
        return app(WishlistService::class)->items();
    }
}; ?>

<div>
    <div class="flex items-center justify-between">
        <div>
            <nav class="flex items-center gap-2 text-xs text-ink/40">
                <a href="{{ route('home') }}" wire:navigate class="hover:text-gold-deep">Beranda</a>
                <span>/</span><span class="text-ink/60">Favorit</span>
            </nav>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-ink">Favorit Saya</h1>
        </div>
        <p class="text-sm text-ink/45">{{ $this->products->count() }} item</p>
    </div>

    @if ($this->products->isEmpty())
        <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 bg-white py-20 text-center">
            <flux:icon.heart class="size-12 text-ink/20" />
            <h3 class="mt-3 text-lg font-bold text-ink">Belum ada favorit.</h3>
            <p class="mt-1 text-sm text-ink/55">Simpan produk yang menarik dengan menekan ikon hati pada kartu produk.</p>
            <flux:button :href="route('products.index')" wire:navigate variant="primary" class="mt-5" icon="squares-2x2">Jelajahi Katalog</flux:button>
        </div>
    @else
        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
            @foreach ($this->products as $product)
                <x-product-card :product="$product" :wire:key="'wish-'.$product->id" />
            @endforeach
        </div>
    @endif
</div>
