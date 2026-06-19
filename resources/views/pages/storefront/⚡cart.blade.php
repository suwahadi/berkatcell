<?php

use App\Exceptions\BusinessRuleException;
use App\Services\CartService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Keranjang Belanja')] #[Layout('layouts::storefront')] class extends Component {
    #[Computed]
    public function items()
    {
        return app(CartService::class)->items();
    }

    #[Computed]
    public function subtotal(): int
    {
        return app(CartService::class)->subtotal();
    }

    public function increment(string $key): void
    {
        $this->changeQty($key, 1);
    }

    public function decrement(string $key): void
    {
        $this->changeQty($key, -1);
    }

    private function changeQty(string $key, int $delta): void
    {
        $cart = app(CartService::class);
        $current = $cart->items()->firstWhere('key', $key);

        if ($current === null) {
            return;
        }

        try {
            $cart->update($key, $current['quantity'] + $delta);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        unset($this->items, $this->subtotal);
        $this->dispatch('cart-updated');
    }

    public function remove(string $key): void
    {
        app(CartService::class)->remove($key);
        unset($this->items, $this->subtotal);
        $this->dispatch('cart-updated');
        Flux::toast(variant: 'success', text: 'Barang dihapus dari keranjang.');
    }
}; ?>

<div>
    <h1 class="text-2xl font-extrabold tracking-tight text-ink">Keranjang Belanja</h1>

    @if ($this->items->isEmpty())
        <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 bg-white py-20 text-center">
            <flux:icon.shopping-cart class="size-12 text-ink/20" />
            <p class="mt-3 text-ink/55">Keranjang Anda masih kosong.</p>
            <flux:button :href="route('products.index')" wire:navigate variant="primary" class="mt-4" icon="squares-2x2">Mulai Belanja</flux:button>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="divide-y divide-black/[.06] rounded-2xl border border-black/[.06] bg-white shadow-card">
                @foreach ($this->items as $item)
                    <div class="flex gap-3 p-4 sm:gap-4" wire:key="cart-{{ $item['key'] }}">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-black/[.06] bg-paper">
                            @php $thumb = $item['variant']?->thumbnailUrl() ?? $item['product']->thumbnailUrl(); @endphp
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $item['product']->name }}" class="size-full object-cover" loading="lazy" />
                            @else
                                <svg viewBox="0 0 64 64" class="size-8 text-ink/20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="21" y="8" width="22" height="48" rx="5"/><line x1="28" y1="13" x2="36" y2="13"/></svg>
                            @endif
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('products.show', $item['product']->slug) }}" wire:navigate class="text-sm font-semibold text-ink hover:text-gold-deep">
                                    {{ $item['product']->name }}
                                </a>
                                @if ($item['variant'])
                                    <p class="text-xs text-ink/55">Varian: {{ $item['variant']->name }}</p>
                                @endif
                                <p class="mt-1 text-sm font-bold text-leaf">{{ rupiah($item['price']) }}</p>
                            </div>
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="flex items-center gap-1">
                                    <flux:button size="xs" variant="subtle" icon="minus" wire:click="decrement('{{ $item['key'] }}')" />
                                    <span class="w-8 text-center text-sm font-bold text-ink">{{ $item['quantity'] }}</span>
                                    <flux:button size="xs" variant="subtle" icon="plus" wire:click="increment('{{ $item['key'] }}')" />
                                </div>
                                <div class="ml-auto text-right text-sm font-bold text-ink sm:w-28">{{ rupiah($item['line_total']) }}</div>
                                <flux:button size="xs" variant="subtle" icon="trash" wire:click="remove('{{ $item['key'] }}')" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="h-fit rounded-2xl border border-black/[.06] bg-white p-5 shadow-card">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Ringkasan</h2>
                <div class="mt-4 flex justify-between text-sm">
                    <span class="text-ink/55">Subtotal</span>
                    <span class="font-bold text-ink">{{ rupiah($this->subtotal) }}</span>
                </div>
                <p class="mt-1 text-xs text-ink/45">Ongkos kirim dihitung saat checkout.</p>
                <flux:button :href="route('checkout')" wire:navigate variant="primary" class="mt-5 w-full" icon="arrow-right">
                    Lanjut ke Checkout
                </flux:button>
                <flux:button :href="route('products.index')" wire:navigate variant="ghost" class="mt-2 w-full">Lanjut Belanja</flux:button>
            </div>
        </div>
    @endif
</div>
