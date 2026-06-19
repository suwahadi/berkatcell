<div class="space-y-6">
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-ink/40">Kategori</p>
        <div class="mt-3 space-y-0.5">
            <button type="button" wire:click="$set('category', '')"
                    @class([
                        'block w-full rounded-md px-2.5 py-2 text-left text-sm transition',
                        'bg-ink font-semibold text-white' => $category === '',
                        'text-ink/70 hover:bg-black/5' => $category !== '',
                    ])>Semua Kategori</button>
            @foreach ($this->categories as $cat)
                <button type="button" wire:click="$set('category', '{{ $cat->slug }}')"
                        @class([
                            'block w-full rounded-md px-2.5 py-2 text-left text-sm transition',
                            'bg-ink font-semibold text-white' => $category === $cat->slug,
                            'text-ink/70 hover:bg-black/5' => $category !== $cat->slug,
                        ])>{{ $cat->name }}</button>
            @endforeach
        </div>
    </div>

    <div class="border-t border-black/[.06] pt-5">
        <p class="text-xs font-bold uppercase tracking-wider text-ink/40">Harga</p>
        <div class="mt-3 space-y-2">
            @foreach ([['', 'Semua harga'], ['a', 'Di bawah Rp 250rb'], ['b', 'Rp 250rb – Rp 1jt'], ['c', 'Di atas Rp 1jt']] as [$val, $label])
                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-ink/70">
                    <input type="radio" wire:model.live="priceBand" value="{{ $val }}" class="size-4 accent-leaf" />
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="border-t border-black/[.06] pt-5">
        <p class="text-xs font-bold uppercase tracking-wider text-ink/40">Status</p>
        <div class="mt-3 space-y-2">
            @foreach ([['new', 'Baru'], ['hot', 'Terlaris'], ['sale', 'Sedang Diskon']] as [$val, $label])
                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-ink/70">
                    <input type="checkbox" wire:model.live="badges" value="{{ $val }}" class="size-4 rounded accent-leaf" />
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>
</div>
