<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-200">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header>
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-2.5 px-1 py-2">
                    <x-brand-logo class="h-8" />
                    <span class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Panel Admin</span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Operasional')" class="grid">
                    <flux:sidebar.item icon="squares-2x2" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>Dashboard</flux:sidebar.item>
                    <flux:sidebar.item icon="shopping-bag" :href="route('admin.orders.index')" :current="request()->routeIs('admin.orders.*')" wire:navigate>Pesanan</flux:sidebar.item>
                    <flux:sidebar.item icon="bell" :href="route('admin.notifications.index')" :current="request()->routeIs('admin.notifications.*')" :badge="auth()->user()?->unreadNotifications()->count() ?: null" badge:color="amber" wire:navigate>Notifikasi</flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Katalog')" class="grid">
                    <flux:sidebar.item icon="cube" :href="route('admin.products.index')" :current="request()->routeIs('admin.products.*')" wire:navigate>Produk</flux:sidebar.item>
                    <flux:sidebar.item icon="tag" :href="route('admin.categories.index')" :current="request()->routeIs('admin.categories.*')" wire:navigate>Kategori</flux:sidebar.item>
                    <flux:sidebar.item icon="ticket" :href="route('admin.vouchers.index')" :current="request()->routeIs('admin.vouchers.*')" wire:navigate>Voucher</flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Konten')" class="grid">
                    <flux:sidebar.item icon="document-text" :href="route('admin.pages.index')" :current="request()->routeIs('admin.pages.*')" wire:navigate>Halaman</flux:sidebar.item>
                    <flux:sidebar.item icon="photo" :href="route('admin.slides.index')" :current="request()->routeIs('admin.slides.*')" wire:navigate>Slide Hero</flux:sidebar.item>
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>Pengaturan</flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Manajemen')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>Pengguna</flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="building-storefront" :href="route('home')" target="_blank">Lihat Toko</flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:dropdown position="top" align="start" class="max-lg:hidden">
                <flux:sidebar.profile :name="auth()->user()->name" :initials="auth()->user()->initials()" icon-trailing="chevrons-up-down" />
                <flux:menu>
                    <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>Pengaturan Akun</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">Keluar</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="border-b border-zinc-200 bg-white lg:hidden dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-2">
                <x-brand-logo class="h-8" />
                <span class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Panel Admin</span>
            </a>

            <flux:spacer />

            <livewire:notification-bell position="bottom" align="end" />

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button as="button" type="submit" variant="subtle" size="sm" icon="arrow-right-start-on-rectangle">Keluar</flux:button>
            </form>
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
