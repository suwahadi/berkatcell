<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Variant;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Form Produk')] #[Layout('layouts::admin')] class extends Component {
    use WithFileUploads;

    public ?Product $product = null;

    public ?int $category_id = null;
    public string $name = '';
    public string $sku = '';
    public ?int $original_price = null;
    public ?int $promo_price = null;
    public string $description = '';
    public ?int $weight = null;
    public int $stock = 0;
    public bool $is_active = true;

    public string $badge = '';

    public array $variants = [];

    public array $existingImages = [];

    public array $photos = [];

    public array $removedImageIds = [];

    public ?string $mainKey = null;

    private array $slotKeyToId = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->product = $product;
            $product->load(['variants', 'images']);
            $this->fill($product->only([
                'category_id', 'name', 'sku', 'original_price', 'promo_price', 'description', 'weight', 'stock', 'is_active',
            ]));
            $this->badge = $product->badge ?? '';
            $this->variants = $product->variants->map(fn (Variant $v): array => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sku,
                'price' => $v->price,
                'promo_price' => $v->promo_price,
                'stock' => $v->stock,
                'weight' => $v->weight,
                'image_key' => $v->image_id ? "id:{$v->image_id}" : null,
            ])->all();

            $this->existingImages = $product->images->map(fn (ProductImage $img): array => [
                'id' => $img->id,
                'url' => $img->url,
                'is_main' => $img->is_main,
                'sort_order' => $img->sort_order,
            ])->all();

            $main = $product->images->firstWhere('is_main', true) ?? $product->images->first();
            $this->mainKey = $main ? "id:{$main->id}" : null;
        } else {
            $this->category_id = Category::query()->value('id');
        }
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get();
    }

    #[Computed]
    public function imageSlots(): array
    {
        $slots = [];

        foreach ($this->existingImages as $img) {
            if (! in_array($img['id'], $this->removedImageIds, true)) {
                $slots[] = ['key' => "id:{$img['id']}", 'url' => $img['url'], 'new' => false, 'filename' => null];
            }
        }

        foreach ($this->photos as $photo) {
            $slots[] = ['key' => "tmp:{$photo->getFilename()}", 'url' => $photo->temporaryUrl(), 'new' => true, 'filename' => $photo->getFilename()];
        }

        return $slots;
    }

    public function removeExisting(int $id): void
    {
        $this->removedImageIds[] = $id;
        $this->reconcileImageKeys();
        unset($this->imageSlots);
    }

    public function removeNewPhoto(string $filename): void
    {
        $this->photos = array_values(array_filter(
            $this->photos,
            fn ($photo): bool => $photo->getFilename() !== $filename,
        ));
        $this->reconcileImageKeys();
        unset($this->imageSlots);
    }

    public function updatedPhotos(): void
    {
        unset($this->imageSlots);
    }

    private function reconcileImageKeys(): void
    {
        $keys = array_column($this->imageSlots(), 'key');

        if ($this->mainKey !== null && ! in_array($this->mainKey, $keys, true)) {
            $this->mainKey = null;
        }

        foreach ($this->variants as $i => $variant) {
            if (($variant['image_key'] ?? null) !== null && ! in_array($variant['image_key'], $keys, true)) {
                $this->variants[$i]['image_key'] = null;
            }
        }
    }

    public function addVariant(): void
    {
        $this->variants[] = ['id' => null, 'name' => '', 'sku' => '', 'price' => null, 'promo_price' => null, 'stock' => 0, 'weight' => null, 'image_key' => null];
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function save(): void
    {
        $productId = $this->product?->id;

        $validated = $this->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'original_price' => ['required', 'integer', 'min:0'],
            'promo_price' => ['nullable', 'integer', 'min:0', 'lt:original_price'],
            'description' => ['required', 'string'],
            'weight' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'photos' => ['array'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variants' => ['array'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.price' => ['required', 'integer', 'min:0'],
            'variants.*.promo_price' => ['nullable', 'integer', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.weight' => ['required', 'integer', 'min:1'],
            'variants.*.image_key' => ['nullable', 'string'],
        ], attributes: [
            'category_id' => 'kategori',
            'original_price' => 'harga asli',
            'promo_price' => 'harga promo',
            'weight' => 'berat',
            'photos.*' => 'gambar',
            'variants.*.name' => 'nama varian',
            'variants.*.sku' => 'SKU varian',
            'variants.*.price' => 'harga varian',
            'variants.*.stock' => 'stok varian',
            'variants.*.weight' => 'berat varian',
        ]);

        if (count($this->imageSlots()) > 6) {
            $this->addError('photos', 'Maksimal 6 gambar per produk. Hapus sebagian sebelum menambah.');

            return;
        }

        if (! $this->validateVariantBusinessRules()) {
            return;
        }

        $data = collect($validated)->except(['variants', 'photos'])->all();
        $data['slug'] = $this->uniqueSlug($this->name, $productId);
        $data['badge'] = in_array($this->badge, ['new', 'hot'], true) ? $this->badge : null;

        if ($this->product?->exists) {
            $this->product->update($data);
        } else {
            $this->product = Product::query()->create($data);
        }

        $this->persistImages();
        $this->syncVariants();

        Flux::toast(variant: 'success', text: 'Produk berhasil disimpan.');
        $this->redirectRoute('admin.products.index', navigate: true);
    }

    private function persistImages(): void
    {
        foreach ($this->removedImageIds as $id) {
            $this->product->images()->whereKey($id)->get()->each->delete();
        }

        $this->slotKeyToId = [];
        foreach ($this->product->images()->get() as $img) {
            $this->slotKeyToId["id:{$img->id}"] = $img->id;
        }

        foreach ($this->photos as $photo) {
            $row = $this->product->images()->create([
                'path' => store_webp($photo, 'products'),
                'is_main' => false,
                'sort_order' => 0,
            ]);
            $this->slotKeyToId["tmp:{$photo->getFilename()}"] = $row->id;
        }

        $this->product->images()->update(['is_main' => false]);
        $mainId = $this->slotKeyToId[$this->mainKey] ?? null;
        $mainId ??= $this->product->images()->orderBy('sort_order')->orderBy('id')->value('id');
        if ($mainId) {
            $this->product->images()->whereKey($mainId)->update(['is_main' => true]);
        }

        $order = 0;
        foreach ($this->imageSlots() as $slot) {
            $id = $this->slotKeyToId[$slot['key']] ?? null;
            if ($id) {
                $this->product->images()->whereKey($id)->update(['sort_order' => $order++]);
            }
        }
    }

    private function validateVariantBusinessRules(): bool
    {
        $valid = true;

        foreach ($this->variants as $i => $variant) {
            $skuTaken = Variant::query()
                ->where('sku', $variant['sku'])
                ->when($variant['id'], fn ($q) => $q->whereKeyNot($variant['id']))
                ->exists();

            if ($skuTaken) {
                $this->addError("variants.{$i}.sku", 'SKU varian ini sudah dipakai.');
                $valid = false;
            }

            if ($variant['promo_price'] !== null && $variant['promo_price'] !== '' && (int) $variant['promo_price'] >= (int) $variant['price']) {
                $this->addError("variants.{$i}.promo_price", 'Harga promo harus lebih kecil dari harga varian.');
                $valid = false;
            }
        }

        return $valid;
    }

    private function syncVariants(): void
    {
        $keepIds = collect($this->variants)->pluck('id')->filter()->all();
        $this->product->variants()->whereNotIn('id', $keepIds ?: [0])->delete();

        foreach ($this->variants as $variant) {
            $imageKey = $variant['image_key'] ?? null;

            $payload = [
                'name' => $variant['name'],
                'sku' => $variant['sku'],
                'price' => (int) $variant['price'],
                'promo_price' => filled($variant['promo_price']) ? (int) $variant['promo_price'] : null,
                'stock' => (int) $variant['stock'],
                'weight' => (int) $variant['weight'],
                'image_id' => $imageKey !== null ? ($this->slotKeyToId[$imageKey] ?? null) : null,
            ];

            if ($variant['id']) {
                $this->product->variants()->whereKey($variant['id'])->update($payload);
            } else {
                $this->product->variants()->create($payload);
            }
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Product::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}; ?>

<div class="mx-auto max-w-6xl">
    <div class="flex items-center gap-3">
        <flux:button :href="route('admin.products.index')" wire:navigate size="sm" variant="ghost" icon="arrow-left" />
        <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $product?->exists ? 'Ubah Produk' : 'Produk Baru' }}</h1>
    </div>

    <form wire:submit="save" class="mt-6 space-y-6">
        <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
            <div class="space-y-6 lg:col-span-7">
                <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Informasi Dasar</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="name" label="Nama Produk" class="sm:col-span-2" />
                        <flux:input wire:model="sku" label="SKU Produk (induk)" />
                        <flux:select wire:model="category_id" label="Kategori">
                            @foreach ($this->categories as $cat)
                                <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="badge" label="Label Kartu (opsional)" class="sm:col-span-2">
                            <flux:select.option value="">— Tanpa label —</flux:select.option>
                            <flux:select.option value="new">Baru</flux:select.option>
                            <flux:select.option value="hot">Terlaris</flux:select.option>
                        </flux:select>
                    </div>
                    <x-wysiwyg model="description" label="Deskripsi" placeholder="Tulis deskripsi produk — spesifikasi, material, dimensi…" />
                    <flux:checkbox wire:model="is_active" label="Aktif (tampil di toko)" />
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Harga & Stok Produk</h2>
                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">Dipakai saat produk dijual langsung. Diabaikan jika produk memiliki varian di bawah.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <flux:input wire:model="original_price" type="number" label="Harga Asli (Rp)" />
                        <flux:input wire:model="promo_price" type="number" label="Harga Promo (Rp)" />
                        <flux:input wire:model="weight" type="number" label="Berat (gram)" />
                        <flux:input wire:model="stock" type="number" label="Stok" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-5">
                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Gambar Produk</h2>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">Maksimal 6 gambar. JPG/PNG/WebP ≤5MB.</p>

                @if (count($this->imageSlots()))
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        @foreach ($this->imageSlots() as $slot)
                            <div wire:key="slot-{{ $slot['key'] }}"
                                 class="relative overflow-hidden rounded-lg border {{ $mainKey === $slot['key'] ? 'border-amber-500 ring-2 ring-amber-500' : 'border-zinc-200 dark:border-zinc-700' }}">
                                <img src="{{ $slot['url'] }}" alt="" class="aspect-square w-full object-cover" />
                                <span class="absolute left-1 top-1 rounded-sm bg-slate-900/70 px-1.5 py-0.5 font-mono text-[10px] font-bold text-white">#{{ $loop->iteration }}</span>
                                @if ($mainKey === $slot['key'])
                                    <span class="absolute right-1 top-1 rounded-sm bg-amber-600 px-1.5 py-0.5 font-mono text-[10px] font-bold text-white">UTAMA</span>
                                @endif
                                <div class="flex items-center justify-between gap-1 border-t border-zinc-100 p-1.5 dark:border-zinc-700">
                                    @if ($mainKey === $slot['key'])
                                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500">Gambar utama</span>
                                    @else
                                        <button type="button" wire:click="$set('mainKey', '{{ $slot['key'] }}')" class="text-[10px] font-medium text-amber-700 hover:underline dark:text-amber-400">Jadikan utama</button>
                                    @endif
                                    @if ($slot['new'])
                                        <flux:button type="button" size="xs" variant="subtle" icon="trash" wire:click="removeNewPhoto('{{ $slot['filename'] }}')" />
                                    @else
                                        <flux:button type="button" size="xs" variant="subtle" icon="trash" wire:click="removeExisting({{ (int) \Illuminate\Support\Str::after($slot['key'], 'id:') }})" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4">
                    <flux:input type="file" wire:model="photos" label="Tambah Gambar" multiple accept="image/jpeg,image/png,image/webp" />
                    <div wire:loading wire:target="photos" class="mt-1 text-xs text-amber-600">Mengunggah & memproses gambar…</div>
                    <flux:error name="photos" />
                    <flux:error name="photos.*" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Varian (opsional)</h2>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">Jika diisi, pembeli wajib memilih varian dan harga/stok mengikuti varian.</p>
                </div>
                <flux:button type="button" wire:click="addVariant" size="xs" variant="filled" icon="plus" class="cursor-pointer">Tambah Varian</flux:button>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($variants as $i => $variant)
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40" wire:key="var-{{ $i }}">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            <flux:input wire:model="variants.{{ $i }}.name" label="Nama" placeholder="100mm" class="col-span-2 sm:col-span-1" />
                            <flux:input wire:model="variants.{{ $i }}.sku" label="SKU" placeholder="A001-01" class="col-span-2 sm:col-span-1" />
                            <flux:input wire:model="variants.{{ $i }}.price" type="number" label="Harga" />
                            <flux:input wire:model="variants.{{ $i }}.promo_price" type="number" label="Promo" />
                            <flux:input wire:model="variants.{{ $i }}.stock" type="number" label="Stok" />
                            <flux:input wire:model="variants.{{ $i }}.weight" type="number" label="Berat" />
                        </div>
                        @if (count($this->imageSlots()))
                            <div class="mt-3">
                                <flux:select wire:model="variants.{{ $i }}.image_key" label="Gambar varian (opsional)" class="sm:max-w-xs">
                                    <flux:select.option value="">— Ikuti gambar utama —</flux:select.option>
                                    @foreach ($this->imageSlots() as $slot)
                                        <flux:select.option value="{{ $slot['key'] }}">Gambar #{{ $loop->iteration }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif
                        <div class="mt-2 flex justify-end">
                            <flux:button type="button" wire:click="removeVariant({{ $i }})" size="xs" variant="subtle" icon="trash">Hapus varian</flux:button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-400 dark:text-zinc-500">Belum ada varian. Produk akan dijual dengan harga & stok produk di atas.</p>
                @endforelse
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('admin.products.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary">Simpan Produk</flux:button>
        </div>
    </form>
</div>
