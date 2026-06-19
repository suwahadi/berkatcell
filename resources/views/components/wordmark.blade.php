@props([
    'tone' => 'light',
])

<span {{ $attributes->class('inline-flex select-none items-baseline gap-0.5 font-extrabold tracking-tight leading-none') }}>
    <span class="{{ $tone === 'dark' ? 'text-white' : 'text-ink' }}">Berkat</span>
    <span class="{{ $tone === 'dark' ? 'text-gold' : 'text-gold-deep' }}">Cell</span>
</span>
