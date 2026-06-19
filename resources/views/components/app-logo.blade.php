@props([
    'sidebar' => false,
])

@php
    $siteName = setting('site_name', 'CV. Jajar Wayang');
@endphp

@if ($sidebar)
    <flux:sidebar.brand name="" :aria-label="$siteName" {{ $attributes }}>
        <x-slot name="logo">
            <x-brand-logo class="h-8" :alt="$siteName" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="" :aria-label="$siteName" {{ $attributes }}>
        <x-slot name="logo">
            <x-brand-logo class="h-8" :alt="$siteName" />
        </x-slot>
    </flux:brand>
@endif
