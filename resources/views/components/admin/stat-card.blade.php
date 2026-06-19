@props(['label', 'value', 'icon' => 'chart-bar', 'tone' => 'amber'])

@php
    $tones = [
        'amber' => ['icon' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400', 'bar' => 'bg-amber-500'],
        'emerald' => ['icon' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400', 'bar' => 'bg-emerald-500'],
        'sky' => ['icon' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400', 'bar' => 'bg-sky-500'],
        'rose' => ['icon' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400', 'bar' => 'bg-rose-500'],
        'slate' => ['icon' => 'bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300', 'bar' => 'bg-zinc-400'],
    ];
    $t = $tones[$tone] ?? $tones['amber'];
@endphp

<div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
    <span class="absolute inset-y-0 left-0 w-1 {{ $t['bar'] }} opacity-0 transition group-hover:opacity-100"></span>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $label }}</p>
            <p class="mt-2 font-mono text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $value }}</p>
        </div>
        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $t['icon'] }}">
            <flux:icon :icon="$icon" class="size-6" />
        </span>
    </div>
</div>
