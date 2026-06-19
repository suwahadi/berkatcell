<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Kategori')] #[Layout('layouts::admin')] class extends Component {
    use WithFileUploads;

    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';
    public bool $is_active = true;

    public $thumbnail = null;
    public ?string $existingThumbnail = null;
    public ?string $existingThumbnailUrl = null;
    public bool $removeThumbnail = false;

    public bool $slugManuallyEdited = false;

    public ?int $deletingId = null;

    #[Computed]
    public function categories()
    {
        return Category::query()->withCount('products')->orderBy('name')->get();
    }

    public function updatedName(string $value): void
    {
        if ($this->editingId === null && ! $this->slugManuallyEdited) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'is_active', 'thumbnail', 'existingThumbnail', 'existingThumbnailUrl', 'removeThumbnail', 'slugManuallyEdited']);
        $this->is_active = true;
        Flux::modal('category-form')->show();
    }

    public function edit(int $id): void
    {
        $category = Category::query()->findOrFail($id);
        $this->reset(['thumbnail', 'removeThumbnail']);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->is_active = $category->is_active;
        $this->existingThumbnail = $category->thumbnail;
        $this->existingThumbnailUrl = $category->thumbnailUrl();
        $this->slugManuallyEdited = true;
        Flux::modal('category-form')->show();
    }

    public function clearThumbnail(): void
    {
        $this->reset('thumbnail');
        $this->removeThumbnail = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($this->editingId)],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($this->editingId)],
            'is_active' => ['boolean'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], attributes: [
            'name' => 'nama kategori',
            'slug' => 'slug',
            'thumbnail' => 'gambar',
        ]);

        $category = Category::query()->find($this->editingId);

        $data = [
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug']),
            'is_active' => $validated['is_active'],
        ];

        if ($this->thumbnail !== null) {
            delete_webp($category?->thumbnail);
            $data['thumbnail'] = store_webp($this->thumbnail, 'categories');
        } elseif ($this->removeThumbnail) {
            delete_webp($category?->thumbnail);
            $data['thumbnail'] = null;
        }

        Category::query()->updateOrCreate(['id' => $this->editingId], $data);

        Flux::modal('category-form')->close();
        Flux::toast(variant: 'success', text: 'Kategori disimpan.');
        $this->reset(['editingId', 'name', 'slug', 'is_active', 'thumbnail', 'existingThumbnail', 'existingThumbnailUrl', 'removeThumbnail']);
    }

    public function confirmDelete(int $id): void
    {
        $category = Category::query()->withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            Flux::toast(variant: 'warning', text: 'Kategori masih memiliki produk — pindahkan/hapus produknya dahulu.');

            return;
        }

        $this->deletingId = $id;
        Flux::modal('category-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            try {
                Category::query()->whereKey($this->deletingId)->delete();
                Flux::toast(variant: 'success', text: 'Kategori dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Kategori tidak dapat dihapus.');
            }
        }

        Flux::modal('category-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Kategori</h1>
        <flux:button wire:click="create" variant="primary" size="sm" icon="plus" class="cursor-pointer">Kategori Baru</flux:button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Gambar</th>
                    <th class="px-5 py-3.5">Nama</th>
                    <th class="px-5 py-3.5">Slug</th>
                    <th class="px-5 py-3.5 text-right">Produk</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->categories as $category)
                    <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="cat-{{ $category->id }}">
                        <td class="px-5 py-3.5">
                            @if ($category->thumbnailUrl())
                                <img src="{{ $category->thumbnailUrl() }}" alt="{{ $category->name }}" class="size-11 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                            @else
                                <div class="grid size-11 place-items-center rounded-lg border border-dashed border-zinc-200 text-zinc-300 dark:border-zinc-700 dark:text-zinc-600">
                                    <flux:icon.photo class="size-5" />
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 font-semibold text-zinc-800 dark:text-zinc-100">{{ $category->name }}</td>
                        <td class="px-5 py-3.5 font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ $category->slug }}</td>
                        <td class="px-5 py-3.5 text-right font-mono text-zinc-600 dark:text-zinc-300">{{ $category->products_count }}</td>
                        <td class="px-5 py-3.5">
                            @if ($category->is_active)
                                <span class="inline-flex items-center rounded-sm bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30">Aktif</span>
                            @else
                                <span class="inline-flex items-center rounded-sm bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 ring-1 ring-inset ring-zinc-400/20 dark:bg-zinc-700 dark:text-zinc-300 dark:ring-zinc-500/30">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <flux:button wire:click="edit({{ $category->id }})" size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                            <flux:button wire:click="confirmDelete({{ $category->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-zinc-400 dark:text-zinc-500">Belum ada kategori.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal name="category-form" class="md:w-96">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah Kategori' : 'Kategori Baru' }}</flux:heading>

            <flux:input wire:model.live.debounce.400ms="name" label="Nama Kategori" />

            <flux:input wire:model.live="slug" label="Slug" />

            <div>
                <flux:label>Gambar Kategori</flux:label>

                @php $previewUrl = $thumbnail ? $thumbnail->temporaryUrl() : (! $removeThumbnail ? $existingThumbnailUrl : null); @endphp

                @if ($previewUrl)
                    <div class="mt-2 flex items-center gap-3">
                        <img src="{{ $previewUrl }}" alt="" class="size-16 shrink-0 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                        <flux:button type="button" size="xs" variant="subtle" icon="trash" wire:click="clearThumbnail" class="cursor-pointer">Hapus gambar</flux:button>
                    </div>
                @endif

                <div class="mt-2">
                    <flux:input type="file" wire:model="thumbnail" accept="image/jpeg,image/png,image/webp" />
                    <div wire:loading wire:target="thumbnail" class="mt-1 text-xs text-amber-600">Mengunggah & memproses gambar…</div>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">JPG/PNG/WebP ≤5MB.</p>
                    <flux:error name="thumbnail" />
                </div>
            </div>

            <flux:checkbox wire:model="is_active" label="Aktif" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="category-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Kategori</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
