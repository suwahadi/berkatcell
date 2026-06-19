<?php

use App\Models\Slide;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Slide Hero')] #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public string $title = '';

    public string $content = '';

    public string $button_label = '';

    public string $url = '';

    public bool $is_active = true;

    public ?int $sort_order = null;

    public $photo = null;

    public ?string $existingImage = null;

    public ?int $deletingId = null;

    #[Computed]
    public function slides()
    {
        return Slide::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function activeCount(): int
    {
        return Slide::query()->where('is_active', true)->count();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'content', 'button_label', 'url', 'is_active', 'sort_order', 'photo', 'existingImage']);
        $this->is_active = true;
        Flux::modal('slide-form')->show();
    }

    public function edit(int $id): void
    {
        $slide = Slide::query()->findOrFail($id);
        $this->editingId = $slide->id;
        $this->title = $slide->title;
        $this->content = (string) $slide->content;
        $this->button_label = (string) $slide->button_label;
        $this->url = (string) $slide->url;
        $this->is_active = $slide->is_active;
        $this->sort_order = $slide->sort_order;
        $this->existingImage = $slide->image;
        $this->photo = null;
        Flux::modal('slide-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'button_label' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'photo' => [$this->editingId ? 'nullable' : 'required', 'image', 'max:4096'],
        ], attributes: [
            'title' => 'judul',
            'content' => 'konten',
            'button_label' => 'label tombol',
            'url' => 'URL tautan',
            'sort_order' => 'urutan',
            'photo' => 'gambar',
        ]);

        if ($this->is_active) {
            $activeOthers = Slide::query()
                ->where('is_active', true)
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->count();

            if ($activeOthers >= Slide::MAX_ACTIVE) {
                Flux::toast(variant: 'warning', text: 'Maksimal hanya '.Slide::MAX_ACTIVE.' slide aktif. Nonaktifkan salah satu dahulu.');

                return;
            }
        }

        $imagePath = $this->existingImage;

        if ($this->photo) {
            $imagePath = store_webp($this->photo, 'slides');

            if ($this->existingImage) {
                delete_webp($this->existingImage);
            }
        }

        $slide = $this->editingId
            ? Slide::query()->findOrFail($this->editingId)
            : new Slide;

        $sortOrder = $validated['sort_order']
            ?? (int) (Slide::query()->max('sort_order') ?? 0) + 1;

        $slide->fill([
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'] ?? null,
            'button_label' => $validated['button_label'] ?? null,
            'url' => $validated['url'] ?? null,
            'is_active' => $this->is_active,
            'sort_order' => $sortOrder,
            'image' => $imagePath,
        ])->save();

        Flux::modal('slide-form')->close();
        Flux::toast(variant: 'success', text: 'Slide disimpan.');
        $this->reset(['editingId', 'title', 'content', 'button_label', 'url', 'is_active', 'sort_order', 'photo', 'existingImage']);
    }

    public function toggle(int $id): void
    {
        $slide = Slide::query()->findOrFail($id);

        if (! $slide->is_active && $this->activeCount >= Slide::MAX_ACTIVE) {
            Flux::toast(variant: 'warning', text: 'Maksimal hanya '.Slide::MAX_ACTIVE.' slide aktif. Nonaktifkan salah satu dahulu.');

            return;
        }

        $slide->update(['is_active' => ! $slide->is_active]);
        unset($this->activeCount);
        Flux::toast(variant: 'success', text: $slide->is_active ? 'Slide diaktifkan.' : 'Slide dinonaktifkan.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        Flux::modal('slide-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            try {
                Slide::query()->findOrFail($this->deletingId)->delete();
                Flux::toast(variant: 'success', text: 'Slide dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Slide tidak dapat dihapus.');
            }
        }

        Flux::modal('slide-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Slide Hero</h1>
            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30">
                {{ $this->activeCount }} / {{ \App\Models\Slide::MAX_ACTIVE }} aktif
            </span>
        </div>
        <flux:button wire:click="create" variant="primary" size="sm" icon="plus" class="cursor-pointer">Slide Baru</flux:button>
    </div>

    <p class="text-sm text-zinc-500 dark:text-zinc-400">Maksimal 6 slide aktif tampil pada hero beranda. Slide bergeser otomatis tiap 3 detik.</p>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Gambar</th>
                    <th class="px-5 py-3.5">Judul</th>
                    <th class="px-5 py-3.5 text-right">Urutan</th>
                    <th class="px-5 py-3.5">Tombol</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->slides as $slide)
                    <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="slide-{{ $slide->id }}">
                        <td class="px-5 py-3.5">
                            <img src="{{ $slide->image_url }}" alt="{{ $slide->title }}" class="h-12 w-20 rounded-md object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                        </td>
                        <td class="px-5 py-3.5 font-semibold text-zinc-800 dark:text-zinc-100">{{ $slide->title ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-right font-mono text-zinc-600 dark:text-zinc-300">{{ $slide->sort_order }}</td>
                        <td class="px-5 py-3.5 text-zinc-600 dark:text-zinc-300">{{ $slide->button_label ?: '—' }}</td>
                        <td class="px-5 py-3.5">
                            <button type="button" wire:click="toggle({{ $slide->id }})" class="cursor-pointer">
                                @if ($slide->is_active)
                                    <span class="inline-flex items-center rounded-sm bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30">Aktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-sm bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 ring-1 ring-inset ring-zinc-400/20 dark:bg-zinc-700 dark:text-zinc-300 dark:ring-zinc-500/30">Nonaktif</span>
                                @endif
                            </button>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <flux:button wire:click="edit({{ $slide->id }})" size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                            <flux:button wire:click="confirmDelete({{ $slide->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-zinc-400 dark:text-zinc-500">Belum ada slide.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal name="slide-form" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah Slide' : 'Slide Baru' }}</flux:heading>

            <flux:input wire:model="title" label="Judul (opsional)" placeholder="Mis. Promo Akhir Tahun" />

            @php $preview = $photo?->temporaryUrl() ?? ($existingImage ? Storage::disk('public')->url($existingImage) : null); @endphp
            <div>
                <flux:label>Gambar</flux:label>
                @if ($preview)
                    <div class="mt-2 overflow-hidden rounded-xl ring-1 ring-zinc-200 dark:ring-zinc-700">
                        <img src="{{ $preview }}" alt="Pratinjau" class="aspect-[21/9] w-full object-cover" />
                    </div>
                @endif
                <div class="mt-2">
                    <flux:input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" />
                </div>
                <p wire:loading wire:target="photo" class="mt-1 text-xs text-amber-600">Mengunggah gambar…</p>
                <flux:text size="sm" class="mt-1 text-zinc-400">Rasio lebar disarankan (mis. 21:9). Otomatis dikompres ke WebP.</flux:text>
            </div>

            <flux:textarea wire:model="content" label="Konten (opsional)" rows="3" placeholder="Teks pendukung pada slide." />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="button_label" label="Label Tombol (opsional)" placeholder="Mis. Belanja Sekarang" />
                <flux:input wire:model="url" label="URL Tautan (opsional)" placeholder="/products" />
            </div>

            <div class="grid grid-cols-2 items-center gap-4">
                <flux:input wire:model="sort_order" type="number" label="Urutan (opsional)" min="0" placeholder="Auto" />
                <flux:checkbox wire:model="is_active" label="Aktif" class="mt-6" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="slide-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Slide</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
