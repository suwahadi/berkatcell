<?php

use App\Concerns\InteractsWithCart;
use App\Concerns\InteractsWithWishlist;
use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Katalog Produk')] #[Layout('layouts::storefront')] class extends Component {
    use InteractsWithCart;
    use InteractsWithWishlist;
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'category', history: true)]
    public string $category = '';

    #[Url(history: true)]
    public string $sort = 'relevan';

    #[Url(as: 'price', history: true)]
    public string $priceBand = '';

    #[Url(history: true)]
    public array $badges = [];

    private const BANDS = [
        'a' => [0, 250_000],
        'b' => [250_000, 1_000_000],
        'c' => [1_000_000, null],
    ];

    public function updating($property): void
    {
        if (in_array($property, ['search', 'category', 'sort', 'priceBand', 'badges'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function products()
    {
        $priceSql = Product::effectivePriceSql();
        [$min, $max] = self::BANDS[$this->priceBand] ?? [null, null];

        return Product::query()
            ->active()
            ->with('category', 'variants', 'images')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")))
            ->when($this->category !== '', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $this->category)))
            ->when($min !== null, fn ($q) => $q->whereRaw("{$priceSql} >= ?", [$min]))
            ->when($max !== null, fn ($q) => $q->whereRaw("{$priceSql} <= ?", [$max]))
            ->when($this->badges !== [], fn ($q) => $q->where(function ($w) {
                $manual = array_values(array_intersect($this->badges, ['new', 'hot']));
                if ($manual !== []) {
                    $w->orWhereIn('badge', $manual);
                }
                if (in_array('sale', $this->badges, true)) {
                    $w->orWhere(fn ($s) => $s->whereNotNull('promo_price')
                        ->orWhereHas('variants', fn ($v) => $v->whereNotNull('promo_price')));
                }
            }))
            ->when($this->sort === 'murah', fn ($q) => $q->orderByRaw("{$priceSql} asc"))
            ->when($this->sort === 'mahal', fn ($q) => $q->orderByRaw("{$priceSql} desc"))
            ->when($this->sort === 'baru', fn ($q) => $q->latest())
            ->when($this->sort === 'diskon', fn ($q) => $q->orderByRaw(
                '(products.original_price - COALESCE(products.promo_price, products.original_price)) / products.original_price desc'
            ))
            ->when($this->sort === 'relevan' || ! in_array($this->sort, ['murah', 'mahal', 'baru', 'diskon'], true), fn ($q) => $q
                ->orderByRaw("CASE products.badge WHEN 'hot' THEN 0 WHEN 'new' THEN 1 ELSE 2 END")
                ->latest())
            ->paginate(12);
    }

    #[Computed]
    public function activePills(): array
    {
        $pills = [];

        if ($this->search !== '') {
            $pills[] = ['type' => 'search', 'label' => '“'.$this->search.'”'];
        }
        if ($this->category !== '') {
            $name = $this->categories->firstWhere('slug', $this->category)?->name ?? $this->category;
            $pills[] = ['type' => 'category', 'label' => $name];
        }
        if (isset(self::BANDS[$this->priceBand])) {
            $pills[] = ['type' => 'price', 'label' => ['a' => '< Rp 250rb', 'b' => 'Rp 250rb – 1jt', 'c' => '> Rp 1jt'][$this->priceBand]];
        }
        foreach ($this->badges as $b) {
            $pills[] = ['type' => 'badge', 'value' => $b, 'label' => ['new' => 'Baru', 'hot' => 'Terlaris', 'sale' => 'Diskon'][$b] ?? $b];
        }

        return $pills;
    }

    public function removeBadge(string $badge): void
    {
        $this->badges = array_values(array_diff($this->badges, [$badge]));
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'category', 'priceBand', 'badges']);
        $this->resetPage();
    }
}; ?>

<div x-data="{ drawer: false }">
    <section class="relative overflow-hidden rounded-2xl bg-ink shadow-card">
        <div class="absolute inset-0 hero-glow"></div>
        <div class="relative grid items-center gap-6 px-8 py-12 md:grid-cols-2 md:px-12">
            <div>
                <span class="inline-flex items-center rounded-full bg-gold px-3 py-1 text-xs font-bold uppercase tracking-wider text-ink">Katalog Produk</span>
                <h1 class="mt-4 text-3xl font-extrabold leading-tight tracking-tight text-white md:text-4xl">
                    Produk <span class="text-gold">Ready Stock!</span>
                </h1>
            </div>
        </div>
    </section>

    <div class="mt-8">
        <nav class="flex items-center gap-2 text-xs text-ink/40">
            <a href="{{ route('home') }}" wire:navigate class="hover:text-gold-deep">Beranda</a>
            <span>/</span><span class="text-ink/60">Katalog Produk</span>
        </nav>
        <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-ink">
            Katalog Produk
        </h2>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink/55"><span class="font-bold text-ink">{{ $this->products->total() }}</span> produk</p>
        <div class="flex items-center gap-2">
            <button type="button" @click="drawer = true"
                    class="inline-flex h-9 items-center gap-1.5 rounded-full border border-ink px-3 text-[13px] font-bold text-ink sm:h-11 sm:gap-2 sm:px-4 sm:text-sm lg:hidden">
                <flux:icon.adjustments-horizontal class="size-4 sm:size-[18px]" /> Filter
                @if (count($this->activePills))
                    <span class="grid size-[18px] place-items-center rounded-full bg-gold text-[10px] font-bold text-ink sm:size-5 sm:text-[11px]">{{ count($this->activePills) }}</span>
                @endif
            </button>
            <flux:select wire:model.live="sort" class="!h-9 !w-auto !rounded-full !py-0 !leading-none !text-[13px] sm:!h-11 sm:!py-2 sm:!leading-[1.375rem] sm:!text-sm">
                <flux:select.option value="relevan">Paling relevan</flux:select.option>
                <flux:select.option value="baru">Terbaru</flux:select.option>
                <flux:select.option value="murah">Harga termurah</flux:select.option>
                <flux:select.option value="mahal">Harga termahal</flux:select.option>
                <flux:select.option value="diskon">Diskon terbesar</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="mt-6 grid gap-7 lg:grid-cols-[256px_1fr]">
        <aside class="hidden lg:block">
            <div class="sticky top-36 rounded-2xl border border-black/[.06] bg-white p-5 shadow-card">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-ink">Filter</h3>
                    <button type="button" wire:click="clearFilters" class="text-xs font-bold text-gold-deep hover:underline">Reset</button>
                </div>
                <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari nama / SKU…" icon="magnifying-glass" size="sm" class="mb-2" />
                @include('partials.storefront.catalog-filters')
            </div>
        </aside>

        <section>
            @if (count($this->activePills))
                <div class="mb-5 flex flex-wrap gap-2">
                    @foreach ($this->activePills as $pill)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/30 bg-gold/10 py-1 pl-3 pr-1.5 text-[13px] font-semibold text-ink">
                            {{ $pill['label'] }}
                            <button type="button"
                                    @if ($pill['type'] === 'search') wire:click="$set('search', '')"
                                    @elseif ($pill['type'] === 'category') wire:click="$set('category', '')"
                                    @elseif ($pill['type'] === 'price') wire:click="$set('priceBand', '')"
                                    @else wire:click="removeBadge('{{ $pill['value'] }}')" @endif
                                    class="grid size-[18px] place-items-center rounded-full bg-ink/10 transition hover:bg-ink hover:text-white" aria-label="Hapus filter">
                                <flux:icon.x-mark variant="micro" class="size-3" />
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($this->products->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 bg-white py-20 text-center">
                    <flux:icon.magnifying-glass class="size-10 text-ink/20" />
                    <h3 class="mt-3 text-lg font-bold text-ink">Belum ada yang cocok.</h3>
                    <p class="mt-1 text-sm text-ink/55">Coba longgarkan filter atau ubah kata kunci pencarian.</p>
                    <flux:button wire:click="clearFilters" variant="primary" class="mt-5">Reset semua filter</flux:button>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 xl:grid-cols-4">
                    @foreach ($this->products as $product)
                        <x-product-card :product="$product" :wire:key="'p-'.$product->id" />
                    @endforeach
                </div>
                <div class="mt-8">
                    {{ $this->products->links() }}
                </div>
            @endif
        </section>
    </div>

    <div x-cloak x-show="drawer" class="fixed inset-0 z-[100] lg:hidden" x-transition.opacity>
        <div class="absolute inset-0 bg-black/50" @click="drawer = false"></div>
        <div class="absolute inset-x-0 bottom-0 flex max-h-[86vh] flex-col rounded-t-3xl bg-white"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            <div class="flex items-center justify-between border-b border-black/[.06] px-5 py-4">
                <h3 class="text-lg font-extrabold text-ink">Filter</h3>
                <button type="button" @click="drawer = false" aria-label="Tutup" class="grid size-10 place-items-center rounded-xl bg-black/5">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>
            <div class="overflow-y-auto px-5 py-5">
                @include('partials.storefront.catalog-filters')
            </div>
            <div class="flex gap-3 border-t border-black/[.06] px-5 py-4">
                <flux:button wire:click="clearFilters" variant="ghost" class="flex-1">Reset</flux:button>
                <flux:button @click="drawer = false" variant="primary" class="flex-1">Lihat hasil</flux:button>
            </div>
        </div>
    </div>
</div>
