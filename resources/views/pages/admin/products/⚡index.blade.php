<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Produk')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function products()
    {
        return Product::query()
            ->with('category', 'variants')
            ->withCount(['orderItems', 'variants'])
            ->when($this->search !== '', fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(15);
    }

    public function toggleActive(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);
        Flux::toast(variant: 'success', text: $product->is_active ? 'Produk diaktifkan.' : 'Produk dinonaktifkan.');
    }

    public function confirmDelete(int $id): void
    {
        $product = Product::query()->withCount('orderItems')->findOrFail($id);

        if ($product->order_items_count > 0) {
            Flux::toast(variant: 'warning', text: 'Produk sudah memiliki riwayat transaksi — nonaktifkan saja, tidak bisa dihapus.');

            return;
        }

        $this->deletingId = $id;
        Flux::modal('product-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            try {
                Product::query()->whereKey($this->deletingId)->delete();
                Flux::toast(variant: 'success', text: 'Produk dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Produk tidak dapat dihapus karena masih terkait data lain.');
            }
        }

        Flux::modal('product-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Produk</h1>
        <flux:button :href="route('admin.products.create')" wire:navigate variant="primary" size="sm" icon="plus" class="cursor-pointer">Produk Baru</flux:button>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari nama / SKU..." icon="magnifying-glass" size="sm" clearable />
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">SKU</th>
                    <th class="px-5 py-3.5">Nama</th>
                    <th class="px-5 py-3.5">Kategori</th>
                    <th class="px-5 py-3.5 text-right">Harga</th>
                    <th class="px-5 py-3.5 text-right">Stok</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->products as $product)
                    <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="prod-{{ $product->id }}">
                        <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $product->sku }}</td>
                        <td class="px-5 py-3.5 font-semibold text-zinc-800 dark:text-zinc-100">{{ $product->name }}</td>
                        <td class="px-5 py-3.5 text-zinc-500 dark:text-zinc-400">{{ $product->category->name }}</td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono">
                            @if ($product->variants_count > 0)
                                <span class="text-[10px] text-zinc-400 dark:text-zinc-500">mulai </span><span class="{{ $product->isOnPromo() ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-white' }}">{{ rupiah($product->fromPrice()) }}</span>
                            @elseif ($product->isOnPromo())
                                <span class="text-amber-600 dark:text-amber-400">{{ rupiah($product->promo_price) }}</span>
                            @else
                                <span class="text-zinc-900 dark:text-white">{{ rupiah($product->original_price) }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono {{ $product->totalStock() <= 5 ? 'font-bold text-amber-600 dark:text-amber-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                            {{ $product->totalStock() }}@if ($product->variants_count > 0)<span class="text-[10px] text-zinc-400 dark:text-zinc-500"> ({{ $product->variants_count }} var)</span>@endif
                        </td>
                        <td class="px-5 py-3.5">
                            <button wire:click="toggleActive({{ $product->id }})" class="cursor-pointer">
                                @if ($product->is_active)
                                    <span class="inline-flex items-center rounded-sm bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30">Aktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-sm bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 ring-1 ring-inset ring-zinc-400/20 dark:bg-zinc-700 dark:text-zinc-300 dark:ring-zinc-500/30">Nonaktif</span>
                                @endif
                            </button>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <flux:button :href="route('admin.products.edit', $product)" wire:navigate size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                            <flux:button wire:click="confirmDelete({{ $product->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-zinc-400 dark:text-zinc-500">Tidak ada produk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->products->links() }}</div>

    <flux:modal name="product-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Produk</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
