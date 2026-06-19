<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\Variant;
use App\Services\CartService;
use App\Services\WishlistService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::storefront')] class extends Component
{
    use InteractsWithCart;
    use InteractsWithWishlist;

    public Product $product;

    public ?int $variantId = null;

    public int $quantity = 1;

    public ?string $activeImage = null;

    public function mount(Product $product): void
    {
        abort_unless($product->is_active, 404);

        $this->product = $product->load('variants.image', 'category', 'images');
        $this->variantId = $product->variants->first()?->id;
        $this->activeImage = $this->resolveVariantImage($this->variantId);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name);
    }

    public function updatedVariantId(): void
    {
        $this->activeImage = $this->resolveVariantImage($this->variantId);
    }

    public function stepImage(int $direction): void
    {
        $urls = $this->product->images->pluck('url')->values();

        if ($urls->count() < 2) {
            return;
        }

        $currentIndex = $urls->search($this->activeImage);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $this->activeImage = $urls[($currentIndex + $direction + $urls->count()) % $urls->count()];
    }

    private function resolveVariantImage(?int $variantId): ?string
    {
        $this->product->loadMissing('images', 'variants.image');
        $main = $this->product->thumbnailUrl();

        if ($variantId === null) {
            return $main;
        }

        return $this->product->variants->firstWhere('id', $variantId)?->image?->url ?? $main;
    }

    #[Computed]
    public function current(): Product|Variant
    {
        if ($this->product->hasVariants()) {
            return $this->product->variants->firstWhere('id', $this->variantId)
                ?? $this->product->variants->first();
        }

        return $this->product;
    }

    #[Computed]
    public function price(): int
    {
        return $this->current->effective_price;
    }

    #[Computed]
    public function compareAt(): ?int
    {
        $c = $this->current;

        if (! $c->isOnPromo()) {
            return null;
        }

        return $c instanceof Variant ? $c->price : $c->original_price;
    }

    #[Computed]
    public function stock(): int
    {
        return $this->current->stock;
    }

    #[Computed]
    public function sku(): string
    {
        return $this->current->sku;
    }

    #[Computed]
    public function wished(): bool
    {
        return app(WishlistService::class)->has($this->product->id);
    }

    public function addCurrent(): void
    {
        $qty = max(1, min($this->quantity, $this->stock));

        try {
            app(CartService::class)->add($this->product->id, $qty, $this->variantId);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        // Perbarui badge keranjang + buka dialog konfirmasi (ganti toast bawaan).
        $this->dispatch('cart-updated');
        $this->dispatch('cart-added');
    }
}; ?>

<div>
    <nav class="flex items-center gap-2 text-xs text-ink/40">
        <a href="{{ route('home') }}" wire:navigate class="hover:text-gold-deep">Beranda</a>
        <span>/</span>
        <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" wire:navigate class="hover:text-gold-deep">{{ $product->category->name }}</a>
        <span>/</span>
        <span class="text-ink/60">{{ $product->name }}</span>
    </nav>

    <div class="mt-6 grid gap-10 lg:grid-cols-2">
        @php
            $product->loadMissing('images');
            $galleryUrls = $product->images->pluck('url')->values();
            $hasGallery = $galleryUrls->isNotEmpty();
        @endphp
        <div x-data="productLightbox(@js($galleryUrls))">
            {{-- Gambar utama (klik untuk memperbesar) --}}
            <div class="group relative flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-black/[.06] bg-white shadow-card">
                @if ($activeImage)
                    <img x-ref="mainImg" src="{{ $activeImage }}" alt="{{ $product->name }}"
                         @if ($hasGallery)
                             @click="openBySrc($refs.mainImg.currentSrc || $refs.mainImg.src)"
                             class="size-full cursor-zoom-in object-cover"
                         @else
                             class="size-full object-cover"
                         @endif />
                    @if ($hasGallery)
                        <span class="pointer-events-none absolute bottom-3 right-3 grid size-9 place-items-center rounded-full bg-ink/70 text-white opacity-0 transition group-hover:opacity-100">
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M11 8v6M8 11h6"/></svg>
                        </span>
                    @endif
                @else
                    <svg viewBox="0 0 64 64" class="size-32 text-ink/15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="19" y="6" width="26" height="52" rx="5"/><line x1="27" y1="11" x2="37" y2="11"/><circle cx="32" cy="51" r="1.6"/></svg>
                @endif

                @if ($hasGallery && $galleryUrls->count() > 1)
                    <button type="button" wire:click="stepImage(-1)" aria-label="Gambar sebelumnya"
                            class="absolute left-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-ink shadow-card ring-1 ring-black/5 backdrop-blur transition hover:bg-white hover:text-gold-deep">
                        <flux:icon.chevron-left class="size-5" />
                    </button>
                    <button type="button" wire:click="stepImage(1)" aria-label="Gambar berikutnya"
                            class="absolute right-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-ink shadow-card ring-1 ring-black/5 backdrop-blur transition hover:bg-white hover:text-gold-deep">
                        <flux:icon.chevron-right class="size-5" />
                    </button>
                @endif
            </div>

            @if ($hasGallery)
                {{-- Thumbnails --}}
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($product->images as $img)
                        <button type="button" wire:click="$set('activeImage', '{{ $img->url }}')"
                                class="size-16 overflow-hidden rounded-lg border transition {{ $activeImage === $img->url ? 'border-gold ring-2 ring-gold' : 'border-black/[.06] hover:border-ink/30' }}">
                            <img src="{{ $img->url }}" alt="" class="size-full object-cover" />
                        </button>
                    @endforeach
                </div>

                {{-- ============ LIGHTBOX ============ --}}
                <template x-teleport="body">
                    <div x-show="isOpen" x-cloak x-transition.opacity.duration.200ms
                         class="fixed inset-0 z-[120] flex flex-col bg-ink/95 backdrop-blur-sm"
                         role="dialog" aria-modal="true" aria-label="Galeri gambar produk"
                         @keydown.window.escape="close()"
                         @keydown.window.arrow-right="next()"
                         @keydown.window.arrow-left="prev()">

                        {{-- Bar atas: indikator urutan + tutup --}}
                        <div class="flex items-center justify-between p-4 text-white">
                            <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-medium">
                                <span x-text="index + 1"></span> / <span x-text="images.length"></span>
                            </span>
                            <button type="button" @click="close()" aria-label="Tutup"
                                    class="grid size-11 place-items-center rounded-full bg-white/10 transition hover:bg-white/20">
                                <flux:icon.x-mark class="size-6" />
                            </button>
                        </div>

                        {{-- Panggung gambar (area swipe) --}}
                        <div class="relative flex flex-1 items-center justify-center overflow-hidden px-4 pb-4"
                             @touchstart.passive="onTouchStart($event)" @touchend.passive="onTouchEnd($event)">
                            {{-- Klik area kosong untuk menutup --}}
                            <div class="absolute inset-0" @click="close()"></div>

                            <button type="button" @click="prev()" aria-label="Sebelumnya" x-show="images.length > 1"
                                    class="absolute left-3 top-1/2 z-10 grid size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:left-6">
                                <flux:icon.chevron-left class="size-7" />
                            </button>

                            <img :src="images[index]" alt="" draggable="false"
                                 class="relative max-h-full max-w-full select-none rounded-lg object-contain shadow-2xl" />

                            <button type="button" @click="next()" aria-label="Berikutnya" x-show="images.length > 1"
                                    class="absolute right-3 top-1/2 z-10 grid size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:right-6">
                                <flux:icon.chevron-right class="size-7" />
                            </button>
                        </div>

                        {{-- Indikator slide (dots) --}}
                        <div class="flex items-center justify-center gap-2 p-5" x-show="images.length > 1">
                            <template x-for="(src, i) in images" :key="i">
                                <button type="button" @click="go(i)" :aria-label="'Ke gambar ' + (i + 1)"
                                        class="h-2.5 rounded-full transition-all duration-300"
                                        :class="index === i ? 'w-7 bg-gold' : 'w-2.5 bg-white/50 hover:bg-white/80'"></button>
                            </template>
                        </div>
                    </div>
                </template>
            @endif
        </div>

        <div>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">SKU: {{ $this->sku }}</p>
                    <h1 class="mt-1 text-lg font-extrabold leading-snug tracking-tight text-ink sm:text-2xl">{{ $product->name }}</h1>
                </div>
                <button type="button" wire:click="toggleWishlist({{ $product->id }})" aria-label="Favorit"
                        @class([
                            'grid size-11 shrink-0 place-items-center rounded-full border transition',
                            'border-ink bg-ink text-gold' => $this->wished,
                            'border-black/[.06] text-ink/50 hover:border-ink hover:text-ink' => ! $this->wished,
                        ])>
                    <flux:icon.heart :variant="$this->wished ? 'solid' : 'outline'" class="size-5" />
                </button>
            </div>

            <div class="mt-4">
                @if ($this->price <= 0)
                    <span class="text-2xl font-extrabold text-gold-deep sm:text-3xl">Hubungi kami</span>
                    <p class="mt-1 text-sm text-ink/55">Harga produk ini tersedia melalui penawaran. Silakan hubungi tim kami untuk ketersediaan dan harga.</p>
                @elseif ($this->compareAt)
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span class="text-2xl font-extrabold text-leaf sm:text-3xl">{{ rupiah($this->price) }}</span>
                        <span class="text-base text-ink/40 line-through sm:text-lg">{{ rupiah($this->compareAt) }}</span>
                    </div>
                @else
                    <span class="text-2xl font-extrabold text-leaf sm:text-3xl">{{ rupiah($this->price) }}</span>
                @endif
            </div>

            @if ($this->price <= 0)
                @php
                    $waLink = wa_href('Halo, saya tertarik dengan produk: '.$product->name.' (SKU: '.$this->sku.')');
                @endphp
                <div class="mt-6">
                    @if ($waLink)
                        <flux:button variant="primary" icon="whatsapp" :href="$waLink" target="_blank">
                            Hubungi kami via WhatsApp
                        </flux:button>
                    @else
                        <p class="text-sm text-ink/55">Silakan hubungi tim kami untuk penawaran harga produk ini.</p>
                    @endif
                </div>
            @elseif ($this->stock <= 0)
                <div class="mt-4 rounded-r-md border-l-4 border-rose-500 bg-rose-50 px-4 py-3 text-sm text-rose-700">Stok varian ini sedang habis.</div>
            @elseif ($this->stock <= 5)
                <div class="mt-4 rounded-r-md border-l-4 border-gold bg-gold/10 px-4 py-3 text-sm text-gold-deep">
                    Ketersediaan terbatas — tersisa <span class="font-bold">{{ $this->stock }}</span> unit.
                </div>
            @else
                <p class="mt-4 text-sm text-ink/55">Stok tersedia: {{ $this->stock }} unit</p>
            @endif

            @if ($product->variants->isNotEmpty())
                <div class="mt-6">
                    <p class="text-sm font-semibold text-ink">Pilih Varian</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($product->variants as $variant)
                            <label wire:key="var-{{ $variant->id }}"
                                   class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2.5 transition {{ $variantId === $variant->id ? 'border-gold bg-gold/10' : 'border-black/[.06] hover:border-ink/30' }}">
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="variantId" value="{{ $variant->id }}" class="accent-leaf" />
                                    <div>
                                        <p class="text-sm font-medium text-ink">{{ $variant->name }}</p>
                                        <p class="text-xs {{ $variant->stock <= 0 ? 'text-rose-500' : 'text-ink/45' }}">
                                            {{ $variant->stock <= 0 ? 'Habis' : 'Stok '.$variant->stock }}
                                        </p>
                                    </div>
                                </div>
                                <span class="text-sm font-bold {{ $variant->isOnPromo() ? 'text-leaf' : 'text-ink/70' }}">{{ rupiah($variant->effective_price) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->price > 0 && $this->stock > 0)
                <div class="mt-6 flex items-end gap-4">
                    <div class="w-28">
                        <flux:input type="number" wire:model="quantity" label="Jumlah" min="1" :max="$this->stock" />
                    </div>
                    <flux:button variant="primary" icon="shopping-cart" wire:click="addCurrent" wire:loading.attr="disabled">
                        Tambah ke Keranjang
                    </flux:button>
                </div>
            @endif

            @php
                $shareUrl = route('products.show', $product->slug);
                $waShare = 'https://wa.me/?text='.rawurlencode($product->name.' - '.$shareUrl);
                $tgShare = 'https://t.me/share/url?url='.rawurlencode($shareUrl).'&text='.rawurlencode($product->name);
            @endphp
            <div class="mt-6 flex items-center gap-2.5" x-data="{ copied: false, t: null }">
                <span class="text-xs font-medium text-ink/45">Bagikan:</span>

                <a href="{{ $waShare }}" target="_blank" rel="noopener" aria-label="Bagikan ke WhatsApp" title="WhatsApp"
                   class="grid size-9 place-items-center rounded-full border border-black/[.06] text-ink/55 transition hover:border-leaf/40 hover:bg-leaf/10 hover:text-leaf">
                    <flux:icon.whatsapp class="size-[18px]" />
                </a>

                <a href="{{ $tgShare }}" target="_blank" rel="noopener" aria-label="Bagikan ke Telegram" title="Telegram"
                   class="grid size-9 place-items-center rounded-full border border-black/[.06] text-ink/55 transition hover:border-sky-400/50 hover:bg-sky-50 hover:text-sky-500">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="size-[18px]"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.21l15.97-6.16c.73-.27 1.37.17 1.13 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71l-4.07-3-1.96 1.9c-.22.22-.4.4-.83.4z"/></svg>
                </a>

                <button type="button" aria-label="Salin tautan" :title="copied ? 'Tersalin' : 'Salin tautan'"
                        @click="navigator.clipboard?.writeText(@js($shareUrl)); copied = true; clearTimeout(t); t = setTimeout(() => copied = false, 2000)"
                        class="grid size-9 place-items-center rounded-full border transition"
                        :class="copied ? 'border-leaf/40 bg-leaf/10 text-leaf' : 'border-black/[.06] text-ink/55 hover:border-ink/30 hover:text-ink'">
                    <span x-show="!copied"><flux:icon.link class="size-[18px]" /></span>
                    <span x-show="copied" x-cloak><flux:icon.check class="size-[18px]" /></span>
                </button>
            </div>

            <div class="mt-8 border-t border-black/[.06] pt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Deskripsi Produk</h2>
                <div class="rich-text mt-3 max-w-none">{!! $product->description !!}</div>
            </div>
        </div>
    </div>

    {{-- ============ DIALOG: BERHASIL DITAMBAHKAN ============ --}}
    {{-- Dibuka oleh event `cart-added` dari addCurrent(); murni Alpine (tanpa state server). --}}
    <div x-data="{ open: false }" x-cloak @cart-added.window="open = true">
        <div x-show="open" class="fixed inset-0 z-[120] flex items-center justify-center p-4"
             x-transition.opacity.duration.200ms
             @keydown.window.escape="open = false"
             role="dialog" aria-modal="true" aria-label="Produk ditambahkan ke keranjang">
            <div class="absolute inset-0 bg-ink/60 backdrop-blur-sm" @click="open = false"></div>

            <div class="relative w-full max-w-[21rem] rounded-2xl border border-black/[.06] bg-white p-5 text-center shadow-card-hover"
                 x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                {{-- Ikon centang berlapis (halo + lingkaran solid) --}}
                <div class="mx-auto grid size-12 place-items-center rounded-full bg-leaf/15">
                    <span class="grid size-9 place-items-center rounded-full bg-leaf text-white shadow-sm shadow-leaf/30">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7" /></svg>
                    </span>
                </div>
                <p class="mt-3 text-[13px] font-semibold text-ink">Produk berhasil dimasukkan ke keranjang.</p>

                <div class="mt-4 flex items-center justify-center gap-2">
                    <a href="{{ route('cart.index') }}" wire:navigate
                       class="whitespace-nowrap rounded-lg bg-leaf px-4 py-2 text-[13px] font-semibold text-white transition hover:bg-leaf-dark">
                        Lihat Keranjang
                    </a>
                    <button type="button" @click="open = false"
                            class="whitespace-nowrap rounded-lg bg-zinc-100 px-4 py-2 text-[13px] font-semibold text-ink/70 transition hover:bg-zinc-200 hover:text-ink">
                        Lanjutkan Belanja
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
