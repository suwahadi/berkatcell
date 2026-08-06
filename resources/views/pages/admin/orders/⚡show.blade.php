<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Pesanan')] #[Layout('layouts::admin')] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load('items.product', 'items.variant', 'voucher', 'activities');
    }

    public function markPaid(OrderService $service): void
    {
        $service->markAsPaid($this->order, auth()->user()?->name);
        $this->order->refresh();
        Flux::modal('confirm-paid')->close();
        Flux::toast(variant: 'success', text: 'Pesanan ditandai lunas. Notifikasi pembayaran dikirim.');
    }

    public function markShipped(OrderService $service): void
    {
        try {
            $service->markAsShipped($this->order, auth()->user()?->name);
            $this->order->refresh();
            Flux::toast(variant: 'success', text: 'Pesanan ditandai telah dikirim.');
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        Flux::modal('confirm-shipped')->close();
    }

    public function cancelOrder(OrderService $service): void
    {
        try {
            $service->cancel($this->order, auth()->user()?->name);
            $this->order->refresh();
            Flux::toast(variant: 'success', text: 'Pesanan dibatalkan. Stok & kuota voucher dikembalikan.');
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        Flux::modal('confirm-cancel')->close();
    }
}; ?>

<div>
    @php $order->loadMissing('items.product', 'items.variant', 'voucher', 'activities'); @endphp

    <div class="flex items-center gap-3">
        <flux:button :href="route('admin.orders.index')" wire:navigate size="sm" variant="ghost" icon="arrow-left" />
        <h1 class="font-mono text-xl font-extrabold text-zinc-900 dark:text-white">{{ $order->order_number }}</h1>
        <x-order-status-badge :order="$order" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Item Pesanan</h2></div>
                <div class="divide-y divide-zinc-100 px-5 dark:divide-zinc-800">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between gap-3 py-3.5 text-sm">
                            <div class="min-w-0">
                                <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $item->product->name ?? 'Produk dihapus' }}</p>
                                @if ($item->variant)<p class="text-xs text-zinc-500 dark:text-zinc-400">Varian: {{ $item->variant->name }}</p>@endif
                                <p class="mt-0.5 font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ rupiah($item->price) }} × {{ $item->quantity }}</p>
                            </div>
                            <span class="shrink-0 font-mono font-bold text-zinc-900 dark:text-white">{{ rupiah($item->total) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-1.5 border-t border-zinc-200 bg-zinc-50/60 px-5 py-4 text-sm dark:border-zinc-800 dark:bg-zinc-800/30">
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Subtotal</span><span class="font-mono text-zinc-900 dark:text-white">{{ rupiah($order->subtotal) }}</span></div>
                    @if ($order->discount_amount > 0)
                        <div class="flex justify-between text-emerald-600 dark:text-emerald-400"><span>Diskon{{ $order->voucher ? ' ('.$order->voucher->code.')' : '' }}</span><span class="font-mono">-{{ rupiah($order->discount_amount) }}</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Ongkos Kirim ({{ $order->shippingCourierName() }})</span><span class="font-mono text-zinc-900 dark:text-white">{{ rupiah($order->shipping_cost) }}</span></div>
                    <div class="flex justify-between border-t border-zinc-200 pt-2.5 text-base font-extrabold dark:border-zinc-700"><span class="text-zinc-900 dark:text-white">Total</span><span class="font-mono text-amber-600 dark:text-amber-400">{{ rupiah($order->grand_total) }}</span></div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Pelanggan & Pengiriman</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Nama</dt><dd class="mt-0.5 text-zinc-800 dark:text-zinc-100">{{ $order->customer_name }}</dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Email</dt><dd class="mt-0.5 text-zinc-800 dark:text-zinc-100">{{ $order->customer_email }}</dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Telepon</dt><dd class="mt-0.5 font-mono text-zinc-800 dark:text-zinc-100">{{ $order->customer_phone }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Ekspedisi &amp; Layanan</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2.5 py-1 text-[12px] text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.truck class="size-4" /> {{ $order->shippingCourierName() }}
                            </span>
                            <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ rupiah($order->shipping_cost) }}</span>
                        </dd>
                        @if ($detail = $order->shippingServiceDetail())
                            <dd class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $detail }}</dd>
                        @endif
                    </div>
                    @if ($order->shipping_destination_label)
                        <div class="sm:col-span-2"><dt class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Wilayah</dt><dd class="mt-0.5 text-zinc-800 dark:text-zinc-100">{{ $order->shipping_destination_label }}</dd></div>
                    @endif
                    <div class="sm:col-span-2"><dt class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Alamat</dt><dd class="mt-0.5 text-zinc-800 dark:text-zinc-100">{{ $order->shipping_address }}</dd></div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Riwayat Pesanan</h2></div>
                <ol class="px-5 py-4">
                    @forelse ($order->activities->reverse() as $activity)
                        @php
                            $dot = match ($activity->type->color()) {
                                'emerald' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
                                'sky' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400',
                                'amber' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
                                'rose' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
                                default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
                            };
                        @endphp
                        <li class="relative flex gap-3 pb-5 last:pb-0" wire:key="act-{{ $activity->id }}">
                            @unless ($loop->last)
                                <span class="absolute left-3.5 top-8 -bottom-1 w-px bg-zinc-200 dark:bg-zinc-700"></span>
                            @endunless
                            <span class="relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full {{ $dot }}">
                                <flux:icon :icon="$activity->type->icon()" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1 pt-0.5">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $activity->type->label() }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $activity->description }}</p>
                                <p class="mt-0.5 font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ $activity->actor ?? 'Sistem' }} • {{ tanggal_id($activity->created_at) }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-zinc-400 dark:text-zinc-500">Belum ada riwayat aktivitas.</li>
                    @endforelse
                </ol>
            </div>
        </div>

        <div class="h-fit space-y-4 rounded-xl border border-zinc-200 bg-white p-5 lg:sticky lg:top-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Aksi Pesanan</h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Tanggal dibuat: {{ tanggal_id($order->created_at) }}</p>

            @if ($order->status === \App\Enums\OrderStatus::PENDING)
                <flux:modal.trigger name="confirm-paid">
                    <flux:button variant="primary" class="w-full cursor-pointer m-1" icon="check-circle">Tandai Lunas</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="confirm-cancel">
                    <flux:button variant="danger" class="w-full cursor-pointer m-1" icon="x-circle">Batalkan Pesanan</flux:button>
                </flux:modal.trigger>
            @elseif ($order->status === \App\Enums\OrderStatus::PAID)
                <flux:modal.trigger name="confirm-shipped">
                    <flux:button variant="primary" class="w-full cursor-pointer m-1" icon="truck">Tandai Dikirim</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="confirm-cancel">
                    <flux:button variant="danger" class="w-full cursor-pointer m-1" icon="x-circle">Batalkan Pesanan</flux:button>
                </flux:modal.trigger>
            @elseif ($order->status === \App\Enums\OrderStatus::SHIPPED)
                <div class="rounded-lg border-l-4 border-sky-500 bg-sky-50 px-4 py-3 text-sm text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Pesanan telah dikirim ke pelanggan.</div>
            @else
                <div class="rounded-lg border-l-4 border-rose-500 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">Pesanan ini telah dibatalkan.</div>
            @endif
        </div>
    </div>

    <flux:modal name="confirm-paid" class="md:w-[26rem]">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <flux:icon.check-circle class="size-5" />
                </span>
                <div>
                    <flux:heading size="lg">Tandai pesanan lunas?</flux:heading>
                    <flux:text class="mt-1">Status pesanan akan menjadi <span class="font-semibold">Lunas</span> dan notifikasi pembayaran dikirim ke pelanggan.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="markPaid" variant="primary" icon="check-circle" class="cursor-pointer">Ya, Tandai Lunas</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-shipped" class="md:w-[26rem]">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                    <flux:icon.truck class="size-5" />
                </span>
                <div>
                    <flux:heading size="lg">Tandai pesanan dikirim?</flux:heading>
                    <flux:text class="mt-1">Pelanggan akan diberi tahu bahwa pesanannya telah <span class="font-semibold">dikirim</span>.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="markShipped" variant="primary" icon="truck" class="cursor-pointer">Ya, Tandai Dikirim</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-cancel" class="md:w-[26rem]">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <flux:icon.exclamation-triangle class="size-5" />
                </span>
                <div>
                    <flux:heading size="lg">Batalkan pesanan ini?</flux:heading>
                    <flux:text class="mt-1">Stok produk &amp; kuota voucher akan dikembalikan. Tindakan ini tidak dapat diurungkan.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="cancelOrder" variant="danger" icon="x-circle" class="cursor-pointer">Ya, Batalkan Pesanan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
