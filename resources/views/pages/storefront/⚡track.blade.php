<?php

use App\Models\Order;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lacak Pesanan')] #[Layout('layouts::storefront')] class extends Component
{
    public string $order_number = '';

    public function track(): void
    {
        $number = strtoupper(trim($this->order_number));

        $this->order_number = $number;

        $validated = $this->validate([
            'order_number' => ['required', 'string', 'max:100'],
        ], [
            'order_number.required' => 'Masukkan nomor pesanan terlebih dahulu.',
        ]);

        $order = Order::query()
            ->where('order_number', $validated['order_number'])
            ->first();

        if ($order === null) {
            Flux::toast(variant: 'danger', text: 'Pesanan dengan nomor tersebut tidak ditemukan. Periksa kembali nomor pesanan Anda.');

            return;
        }

        $this->redirectRoute('checkout.success', ['order' => $order->uuid], navigate: true);
    }
}; ?>

<div class="mx-auto max-w-lg">
    <div class="text-center">
        <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-ink">Lacak Pesanan</h1>
        <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink/55">
            Masukkan nomor pesanan Anda untuk melihat status pembayaran dan detail pengiriman.
        </p>
    </div>

    <form wire:submit="track" class="mt-8 rounded-2xl border border-black/[.06] bg-white p-6 shadow-card sm:p-8">
        <flux:input
            wire:model="order_number"
            label="Nomor Pesanan"
            placeholder=""
            icon="hashtag"
            autofocus
            class="font-mono uppercase tracking-wider [&_input]:uppercase" />

        <flux:button type="submit" variant="primary" class="mt-5 w-full cursor-pointer"
                     wire:loading.attr="disabled" wire:target="track">
            <span wire:loading.remove wire:target="track">Lacak Pesanan</span>
            <span wire:loading wire:target="track">Mencari…</span>
        </flux:button>
    </form>

    <div class="mt-6 text-center">
        <flux:button :href="route('products.index')" wire:navigate variant="ghost" icon="squares-2x2" class="cursor-pointer">
            Lanjut Belanja
        </flux:button>
    </div>
</div>
