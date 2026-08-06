<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Riwayat Pesanan')] #[Layout('layouts::app')] class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $status = '';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public function updating($property): void
    {
        if (in_array($property, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['status', 'search']);
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        return Order::ownedBy(auth()->user())
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->search !== '', fn ($q) => $q->where('order_number', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);
    }

    public function statusOptions(): array
    {
        return OrderStatus::cases();
    }
}; ?>

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <nav class="flex items-center gap-2 text-xs text-zinc-400 dark:text-zinc-500">
                <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">Dashboard</a>
                <span>/</span><span class="text-zinc-600 dark:text-zinc-400">Transaksi</span>
            </nav>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Riwayat Pesanan</h1>
        </div>
        <p class="font-mono text-sm text-zinc-400 dark:text-zinc-500">{{ $this->orders->total() }} pesanan</p>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari nomor pesanan..." icon="magnifying-glass" size="sm" clearable />
        </div>
        <flux:select wire:model.live="status" size="sm" class="w-full cursor-pointer sm:w-52" placeholder="Semua status">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach ($this->statusOptions() as $opt)
                <flux:select.option :value="$opt->value">{{ $opt->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($status !== '' || $search !== '')
            <flux:button wire:click="resetFilters" size="sm" variant="subtle" icon="x-mark" class="cursor-pointer">Reset</flux:button>
        @endif
    </div>

    <div class="hidden overflow-hidden rounded-xl border border-zinc-200 bg-white md:block dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Nomor</th>
                    <th class="px-5 py-3.5">Tanggal</th>
                    <th class="px-5 py-3.5 text-right">Total</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($this->orders as $order)
                    <tr class="group transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-5 py-3.5 font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $order->order_number }}</td>
                        <td class="px-5 py-3.5 text-zinc-500 dark:text-zinc-400">{{ tanggal_id($order->created_at) }}</td>
                        <td class="px-5 py-3.5 text-right font-mono font-bold text-zinc-900 dark:text-white">{{ rupiah($order->grand_total) }}</td>
                        <td class="px-5 py-3.5"><x-order-status-badge :order="$order" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('dashboard.orders.show', $order->order_number) }}" wire:navigate
                               class="inline-flex items-center gap-1 rounded-full border border-zinc-200 px-3.5 py-1.5 text-xs font-bold text-zinc-700 transition hover:border-amber-600 hover:text-amber-600 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-amber-500 dark:hover:text-amber-400">
                                <flux:icon.eye class="size-3.5" /> Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-14 text-center text-sm text-zinc-400 dark:text-zinc-500">Tidak ada pesanan yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-3 md:hidden">
        @forelse ($this->orders as $order)
            <a href="{{ route('dashboard.orders.show', $order->order_number) }}" wire:navigate
               class="block rounded-xl border border-zinc-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="flex items-center justify-between gap-2">
                    <span class="truncate font-mono text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $order->order_number }}</span>
                    <x-order-status-badge :order="$order" />
                </div>
                <div class="mt-2.5 flex items-center justify-between gap-2 border-t border-zinc-100 pt-2.5 dark:border-zinc-800">
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ tanggal_id($order->created_at) }}</span>
                    <span class="font-mono text-base font-extrabold text-zinc-900 dark:text-white">{{ rupiah($order->grand_total) }}</span>
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 bg-white px-4 py-14 text-center text-sm text-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-500">
                Tidak ada pesanan yang cocok.
            </div>
        @endforelse
    </div>

    <div>{{ $this->orders->links() }}</div>
</div>
