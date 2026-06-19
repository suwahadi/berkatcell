@props([
    'model',
    'label' => null,
    'description' => null,
    'placeholder' => 'Tulis di sini…',
])

@php
    $btn ='grid size-8 shrink-0 place-items-center rounded-md text-[13px] font-bold transition select-none cursor-pointer';
    $idle = 'text-zinc-600 hover:bg-zinc-200/70 dark:text-zinc-300 dark:hover:bg-zinc-700';
    $on = 'bg-amber-600 text-white';
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}</label>
    @endif

    <div
        wire:ignore
        x-data="wysiwyg(@js($model), @js($placeholder))"
        class="overflow-hidden rounded-xl border border-zinc-300 bg-white transition focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20 dark:border-zinc-700 dark:bg-zinc-900"
    >
        <div class="flex flex-wrap items-center gap-0.5 border-b border-zinc-200 bg-zinc-50 p-1.5 dark:border-zinc-800 dark:bg-zinc-800/40">
            <button type="button" title="Judul 1" @mousedown.prevent="block('h1')" class="{{ $btn }}" :class="isBlock('h1') ? @js($on) : @js($idle)">H1</button>
            <button type="button" title="Judul 2" @mousedown.prevent="block('h2')" class="{{ $btn }}" :class="isBlock('h2') ? @js($on) : @js($idle)">H2</button>
            <button type="button" title="Judul 3" @mousedown.prevent="block('h3')" class="{{ $btn }}" :class="isBlock('h3') ? @js($on) : @js($idle)">H3</button>

            <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-zinc-700"></span>

            <button type="button" title="Tebal" @mousedown.prevent="cmd('bold')" class="{{ $btn }}" :class="state('bold') ? @js($on) : @js($idle)">B</button>
            <button type="button" title="Miring" @mousedown.prevent="cmd('italic')" class="{{ $btn }} italic" :class="state('italic') ? @js($on) : @js($idle)">I</button>
            <button type="button" title="Garis bawah" @mousedown.prevent="cmd('underline')" class="{{ $btn }} underline" :class="state('underline') ? @js($on) : @js($idle)">U</button>
            <button type="button" title="Coret" @mousedown.prevent="cmd('strikeThrough')" class="{{ $btn }} line-through" :class="state('strikeThrough') ? @js($on) : @js($idle)">S</button>

            <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-zinc-700"></span>

            <button type="button" title="Daftar berpoin" @mousedown.prevent="cmd('insertUnorderedList')" class="{{ $btn }}" :class="state('insertUnorderedList') ? @js($on) : @js($idle)">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="4" cy="6" r="1.4" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.4" fill="currentColor" stroke="none"/><path d="M9 6h11M9 12h11M9 18h11"/></svg>
            </button>
            <button type="button" title="Daftar bernomor" @mousedown.prevent="cmd('insertOrderedList')" class="{{ $btn }}" :class="state('insertOrderedList') ? @js($on) : @js($idle)">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6h10M10 12h10M10 18h10"/><path d="M4 4v4M3 8h2" stroke-width="1.6"/><path d="M3 16h2.2a.9.9 0 0 1 .3 1.7L3 20h3" stroke-width="1.6"/></svg>
            </button>

            <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-zinc-700"></span>

            <button type="button" title="Kutipan" @mousedown.prevent="block('blockquote')" class="{{ $btn }}" :class="isBlock('blockquote') ? @js($on) : @js($idle)">
                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M7 7H4a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h2v1a2 2 0 0 1-2 2 1 1 0 1 0 0 2 4 4 0 0 0 4-4V8a1 1 0 0 0-1-1Zm13 0h-3a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h2v1a2 2 0 0 1-2 2 1 1 0 1 0 0 2 4 4 0 0 0 4-4V8a1 1 0 0 0-1-1Z"/></svg>
            </button>
            <button type="button" title="Blok kode" @mousedown.prevent="block('pre')" class="{{ $btn }}" :class="isBlock('pre') ? @js($on) : @js($idle)">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 9-3 3 3 3M16 9l3 3-3 3"/></svg>
            </button>

            <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-zinc-700"></span>

            <button type="button" title="Tautan" @mousedown.prevent="setLink()" class="{{ $btn }}" :class="state('createLink') ? @js($on) : @js($idle)">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/></svg>
            </button>
            <button type="button" title="Gambar (URL)" @mousedown.prevent="setImage()" class="{{ $btn }} {{ $idle }}">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5L5 21"/></svg>
            </button>

            <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-zinc-700"></span>

            <button type="button" title="Rata kiri" @mousedown.prevent="cmd('justifyLeft')" class="{{ $btn }}" :class="state('justifyLeft') ? @js($on) : @js($idle)">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h10M4 18h13"/></svg>
            </button>
            <button type="button" title="Rata tengah" @mousedown.prevent="cmd('justifyCenter')" class="{{ $btn }}" :class="state('justifyCenter') ? @js($on) : @js($idle)">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M5 18h14"/></svg>
            </button>
            <button type="button" title="Hapus format" @mousedown.prevent="cmd('removeFormat')" class="{{ $btn }} {{ $idle }}">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h12M9 4 7 20M13 4l-1 6"/><path d="m15 15 5 5m0-5-5 5"/></svg>
            </button>

            <span class="ml-auto"></span>

            <button type="button" title="Urungkan" @mousedown.prevent="cmd('undo')" class="{{ $btn }} {{ $idle }}">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7 4 12l5 5"/><path d="M4 12h11a5 5 0 0 1 0 10h-1"/></svg>
            </button>
            <button type="button" title="Ulangi" @mousedown.prevent="cmd('redo')" class="{{ $btn }} {{ $idle }}">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 7 5 5-5 5"/><path d="M20 12H9a5 5 0 0 0 0 10h1"/></svg>
            </button>
        </div>

        <div
            x-ref="editor"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            data-placeholder="{{ $placeholder }}"
            class="wysiwyg-content"
        ></div>
    </div>

    @if ($description)
        <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif

    @error($model)
        <p class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
