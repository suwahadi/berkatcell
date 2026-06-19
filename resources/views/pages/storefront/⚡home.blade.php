<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Models\Category;
use App\Models\Product;
use App\Models\Slide;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] #[Layout('layouts::storefront')] class extends Component
{
    use InteractsWithCart;
    use InteractsWithWishlist;

    #[Computed]
    public function promos()
    {
        return Product::query()->active()->with('category', 'variants', 'images')
            ->whereNotNull('promo_price')
            ->latest()->take(4)->get();
    }

    #[Computed]
    public function newest()
    {
        return Product::query()->active()->with('category', 'variants', 'images')->latest()->take(8)->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->where('is_active', true)->withCount('products')->orderBy('name')->get();
    }

    #[Computed]
    public function slides()
    {
        return Slide::query()->active()->orderBy('sort_order')->orderBy('id')->get();
    }
}; ?>

<div class="space-y-16">
    @if ($this->slides->isNotEmpty())
        @include('partials.storefront.hero-slider', ['slides' => $this->slides])
    @else
        <section class="reveal relative overflow-hidden rounded-2xl bg-ink shadow-card">
            <div class="absolute inset-0 hero-glow"></div>
            <div class="relative grid items-center gap-6 px-8 py-14 md:grid-cols-2 md:px-14">
                <div>
                    <span class="inline-flex items-center rounded-full bg-gold px-3 py-1 text-xs font-bold uppercase tracking-wider text-ink">Original &amp; Bergaransi</span>
                    <h1 class="mt-4 text-3xl font-extrabold leading-tight tracking-tight text-white md:text-5xl">
                        Smartphone Idaman, <span class="text-gold">Harga Bersahabat.</span>
                    </h1>
                    <p class="mt-4 max-w-md leading-relaxed text-white/70">
                        {{ setting('site_tagline', 'HP & aksesoris original dengan garansi resmi') }}. Belanja aman, cepat, dan terpercaya bersama kami.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('products.index') }}" wire:navigate
                           class="inline-flex items-center gap-2 rounded-xl bg-leaf px-6 py-3 text-sm font-bold text-white transition hover:bg-leaf-dark">
                            <flux:icon.squares-2x2 class="size-5" /> Jelajahi Katalog
                        </a>
                        <a href="{{ route('wishlist.index') }}" wire:navigate
                           class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/10">
                            Lihat Favorit
                        </a>
                    </div>
                </div>
                <div class="hidden justify-center md:flex">
                    <svg viewBox="0 0 64 64" class="size-52 text-white/10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="19" y="6" width="26" height="52" rx="5"/><line x1="27" y1="11" x2="37" y2="11"/><circle cx="32" cy="51" r="1.6"/></svg>
                </div>
            </div>
        </section>
    @endif

    <section class="reveal">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-[13px] font-semibold uppercase tracking-[.18em] text-gold-deep">Jelajahi</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-ink md:text-4xl">Kategori Pilihan</h2>
            </div>
            <a href="{{ route('products.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink/70 transition-colors hover:text-gold-deep">Lihat Semua <span aria-hidden="true">›</span></a>
        </div>
        <div class="mt-9 grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 lg:grid-cols-5">
            @foreach ($this->categories as $category)
                <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate
                   class="group card-lift rounded-2xl border border-black/[.06] bg-white p-5 text-center shadow-card hover:shadow-card-hover">
                    <div class="glyph-zoom mx-auto grid size-24 place-items-center overflow-hidden rounded-full bg-gold/10">
                        @if ($category->thumbnailUrl())
                            <img src="{{ $category->thumbnailUrl() }}" alt="{{ $category->name }}" loading="lazy" class="size-full object-cover" />
                        @else
                            <svg viewBox="0 0 64 64" class="size-11 text-ink/35" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="21" y="8" width="22" height="48" rx="5"/><line x1="28" y1="13" x2="36" y2="13"/></svg>
                        @endif
                    </div>
                    <h3 class="mt-4 text-[17px] font-bold text-ink">{{ $category->name }}</h3>
                    <span class="mt-1.5 inline-flex items-center gap-1 text-[13px] font-semibold text-gold-deep transition-all group-hover:gap-2">{{ $category->products_count }} produk <span aria-hidden="true">›</span></span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($this->promos->isNotEmpty())
        <section class="reveal">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-[13px] font-semibold uppercase tracking-[.18em] text-gold-deep">Paling Diburu</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-ink md:text-4xl">Produk Unggulan</h2>
                </div>
                <a href="{{ route('products.index', ['sort' => 'diskon']) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink/70 transition-colors hover:text-gold-deep">Lihat Semua <span aria-hidden="true">›</span></a>
            </div>
            <div class="mt-9 grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
                @foreach ($this->promos as $product)
                    <x-product-card :product="$product" :wire:key="'promo-'.$product->id" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="reveal">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-[13px] font-semibold uppercase tracking-[.18em] text-gold-deep">Baru Datang</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-ink md:text-4xl">Produk Terbaru</h2>
            </div>
            <a href="{{ route('products.index', ['sort' => 'baru']) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink/70 transition-colors hover:text-gold-deep">Lihat Semua <span aria-hidden="true">›</span></a>
        </div>
        <div class="mt-9 grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
            @foreach ($this->newest as $product)
                <x-product-card :product="$product" :wire:key="'new-'.$product->id" />
            @endforeach
        </div>
    </section>

</div>
