<?php

use App\Models\Page;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Halaman')] #[Layout('layouts::admin')] class extends Component {
    public ?int $editingId = null;
    public string $pageTitle = '';
    public string $content = '';
    public bool $is_active = true;

    public ?int $deletingId = null;

    #[Computed]
    public function pages()
    {
        return Page::query()->orderBy('title')->get();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'pageTitle', 'content', 'is_active']);
        $this->is_active = true;
        Flux::modal('page-form')->show();
    }

    public function edit(int $id): void
    {
        $page = Page::query()->findOrFail($id);
        $this->editingId = $page->id;
        $this->pageTitle = $page->title;
        $this->content = $page->content;
        $this->is_active = $page->is_active;
        Flux::modal('page-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'pageTitle' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_active' => ['boolean'],
        ], attributes: ['pageTitle' => 'judul', 'content' => 'konten']);

        $slug = Str::slug($validated['pageTitle']);

        if ($slug === '' || Page::isReservedSlug($slug)) {
            throw ValidationException::withMessages([
                'pageTitle' => 'Judul menghasilkan slug "'.$slug.'" yang dipakai sistem. Gunakan judul lain.',
            ]);
        }

        $slugExists = Page::query()
            ->where('slug', $slug)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($slugExists) {
            throw ValidationException::withMessages([
                'pageTitle' => 'Slug "'.$slug.'" sudah dipakai halaman lain. Gunakan judul yang berbeda.',
            ]);
        }

        $page = $this->editingId
            ? Page::query()->findOrFail($this->editingId)
            : new Page();

        $page->fill([
            'title' => $validated['pageTitle'],
            'slug' => $slug,
            'content' => $validated['content'],
            'is_active' => $validated['is_active'],
        ])->save();

        Flux::modal('page-form')->close();
        Flux::toast(variant: 'success', text: 'Halaman disimpan.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        Flux::modal('page-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            Page::query()->whereKey($this->deletingId)->delete();
            Flux::toast(variant: 'success', text: 'Halaman dihapus.');
        }

        Flux::modal('page-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Halaman Statis</h1>
        <flux:button wire:click="create" variant="primary" size="sm" icon="plus" class="cursor-pointer">Halaman Baru</flux:button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Judul</th>
                    <th class="px-5 py-3.5">Slug</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->pages as $page)
                    <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="pg-{{ $page->id }}">
                        <td class="px-5 py-3.5 font-semibold text-zinc-800 dark:text-zinc-100">{{ $page->title }}</td>
                        <td class="px-5 py-3.5 font-mono text-xs text-zinc-400 dark:text-zinc-500">/{{ $page->slug }}</td>
                        <td class="px-5 py-3.5">
                            @if ($page->is_active)
                                <span class="inline-flex items-center rounded-sm bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30">Aktif</span>
                            @else
                                <span class="inline-flex items-center rounded-sm bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 ring-1 ring-inset ring-zinc-400/20 dark:bg-zinc-700 dark:text-zinc-300 dark:ring-zinc-500/30">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <flux:button wire:click="edit({{ $page->id }})" size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                            <flux:button wire:click="confirmDelete({{ $page->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-zinc-400 dark:text-zinc-500">Belum ada halaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal name="page-form" class="w-full md:w-[60rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah Halaman' : 'Halaman Baru' }}</flux:heading>
            <flux:input wire:model="pageTitle" label="Judul" />
            <x-wysiwyg model="content" label="Konten" placeholder="Tulis konten halaman — gunakan toolbar untuk heading, daftar, tautan, gambar…" />
            <flux:checkbox wire:model="is_active" label="Aktif (tampil di footer toko)" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="page-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Halaman</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
