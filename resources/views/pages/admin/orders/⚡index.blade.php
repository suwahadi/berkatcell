<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pesanan')] #[Layout('layouts::admin')] class extends Component {
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

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('order_number', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")))
            ->latest()
            ->paginate(15);
    }

    public function statusOptions(): array
    {
        return OrderStatus::cases();
    }
}; ?>

<div class="space-y-5">
    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Pesanan</h1>

    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari nomor / nama / email..." icon="magnifying-glass" size="sm" clearable />
        </div>
        <flux:select wire:model.live="status" size="sm" class="w-full cursor-pointer sm:w-52" placeholder="Semua status">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach ($this->statusOptions() as $opt)
                <flux:select.option :value="$opt->value">{{ $opt->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Nomor</th>
                    <th class="px-5 py-3.5">Pelanggan</th>
                    <th class="px-5 py-3.5">Tanggal</th>
                    <th class="px-5 py-3.5 text-right">Total</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->orders as $order)
                    <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="whitespace-nowrap px-5 py-3.5 font-mono font-bold text-zinc-800 dark:text-zinc-100">{{ $order->order_number }}</td>
                        <td class="px-5 py-3.5">
                            <p class="text-zinc-800 dark:text-zinc-100">{{ $order->customer_name }}</p>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $order->customer_email }}</p>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-zinc-500 dark:text-zinc-400">{{ tanggal_id($order->created_at) }}</td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono font-bold text-zinc-900 dark:text-white">{{ rupiah($order->grand_total) }}</td>
                        <td class="px-5 py-3.5"><x-order-status-badge :status="$order->status" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('admin.orders.show', $order->order_number) }}" wire:navigate
                               class="inline-flex items-center gap-1 rounded-full border border-zinc-200 px-3.5 py-1.5 text-xs font-bold text-zinc-700 transition hover:border-amber-600 hover:text-amber-600 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-amber-500 dark:hover:text-amber-400">
                                <flux:icon.eye class="size-3.5" /> Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-zinc-400 dark:text-zinc-500">Tidak ada pesanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->orders->links() }}</div>
</div>
