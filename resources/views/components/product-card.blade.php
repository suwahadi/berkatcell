@props([
    'product',
])

@php
    $hasVariants = $product->hasVariants();
    $onPromo = $product->isOnPromo();
    $badge = $product->badgeMeta();
    $thumb = $product->thumbnailUrl();
    $soldOut = ! $hasVariants && $product->stock <= 0;
    $wished = app(\App\Services\WishlistService::class)->has($product->id);
    $isCall = $product->fromPrice() <= 0;

    $waLink = wa_href('Halo, saya tertarik dengan produk: '.$product->name.' (SKU: '.$product->sku.')');

    $discount = 0;
    if ($onPromo && ! $hasVariants && $product->original_price > 0) {
        $discount = (int) round((1 - $product->promo_price / $product->original_price) * 100);
    }
@endphp

<article {{ $attributes->class('group card-lift flex flex-col overflow-hidden rounded-2xl border border-black/[.06] bg-white shadow-card hover:shadow-card-hover') }}>
    <div class="relative aspect-square overflow-hidden ph-stripe glyph-zoom">
        <a href="{{ route('products.show', $product->slug) }}" wire:navigate class="grid size-full place-items-center">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="{{ $product->name }}"
                     class="size-full object-cover transition duration-500 group-hover:scale-[1.04] {{ $soldOut ? 'opacity-60' : '' }}"
                     loading="lazy" />
            @else
                <svg viewBox="0 0 64 64" class="size-16 text-ink/20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="21" y="8" width="22" height="48" rx="5"/><line x1="28" y1="13" x2="36" y2="13"/></svg>
            @endif
        </a>

        <div class="pointer-events-none absolute left-3 top-3 z-10 flex flex-col gap-1.5">
            @if ($soldOut)
                <span class="inline-flex items-center rounded-full bg-zinc-500 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Stok Habis</span>
            @else
                @if ($badge)
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide',
                        'bg-gold text-ink' => $badge['tone'] === 'new',
                        'bg-red-600 text-white' => $badge['tone'] === 'hot',
                    ])>{{ $badge['label'] }}</span>
                @endif
                @if ($discount > 0)
                    <span class="inline-flex items-center rounded-full bg-red-600 px-2.5 py-1 text-[12px] font-bold text-white">-{{ $discount }}%</span>
                @endif
            @endif
        </div>

        <button type="button" wire:click="toggleWishlist({{ $product->id }})" wire:loading.attr="disabled"
                aria-label="{{ $wished ? 'Hapus dari favorit' : 'Simpan ke favorit' }}"
                @class([
                    'absolute right-3 top-3 z-10 grid size-9 place-items-center rounded-full border transition',
                    'border-ink bg-ink text-gold' => $wished,
                    'border-black/[.06] bg-white/90 text-ink/50 hover:border-ink hover:text-ink' => ! $wished,
                ])>
            <flux:icon.heart :variant="$wished ? 'solid' : 'outline'" class="size-[18px]" />
        </button>
    </div>

    <div class="flex flex-1 flex-col p-4">
        <p class="text-[11px] font-bold uppercase tracking-wide text-gold-deep">{{ $product->category?->name ?? $product->sku }}</p>
        <a href="{{ route('products.show', $product->slug) }}" wire:navigate
           class="mt-1 line-clamp-2 min-h-[2.6em] text-[15px] font-bold leading-snug text-ink transition hover:text-gold-deep">
            {{ $product->name }}
        </a>

        <div class="mt-2.5 flex flex-wrap items-baseline gap-x-2">
            @if ($isCall)
                <span class="text-[17px] font-extrabold text-gold-deep">Hubungi kami</span>
            @elseif ($hasVariants)
                <span class="text-[12px] font-medium text-ink/45">Mulai</span>
                <span class="text-[17px] font-extrabold text-leaf">{{ rupiah($product->fromPrice()) }}</span>
            @elseif ($onPromo)
                <span class="text-[17px] font-extrabold text-leaf">{{ rupiah($product->promo_price) }}</span>
                <span class="text-[12.5px] text-ink/40 line-through">{{ rupiah($product->original_price) }}</span>
            @else
                <span class="text-[17px] font-extrabold text-leaf">{{ rupiah($product->original_price) }}</span>
            @endif
        </div>

        <div class="mt-auto pt-3.5">
            @if ($isCall)
                <a href="{{ $waLink ?: route('products.show', $product->slug) }}"
                   @if ($waLink) target="_blank" rel="noopener" @else wire:navigate @endif
                   class="flex w-full items-center justify-center gap-2 rounded-xl bg-leaf px-3 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-leaf-dark">
                    <flux:icon.whatsapp class="size-[18px]" /> Hubungi kami
                </a>
            @elseif ($hasVariants)
                <a href="{{ route('products.show', $product->slug) }}" wire:navigate
                   class="flex w-full items-center justify-center gap-2 rounded-xl bg-ink px-3 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-ink-soft">
                    <flux:icon.adjustments-horizontal class="size-[18px]" /> Pilih Varian
                </a>
            @elseif ($soldOut)
                <span class="flex w-full items-center justify-center rounded-xl bg-zinc-200 px-3 py-2.5 text-[14px] font-semibold text-ink/40">Stok Habis</span>
            @else
                <button type="button" wire:click="addToCart({{ $product->id }})" wire:loading.attr="disabled"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-leaf px-3 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-leaf-dark disabled:opacity-60">
                    <flux:icon.shopping-cart class="size-[18px]" /> Keranjang
                </button>
            @endif
        </div>
    </div>
</article>
