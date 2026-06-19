@php
    use App\Models\Category;
    use App\Models\Page;

    $navCategories = Category::query()->where('is_active', true)->orderBy('name')->get();
    $footerPages = Page::query()->where('is_active', true)->orderBy('created_at', 'asc')->get();

    $aboutPage =Page::query()->where('slug', 'tentang-kami')->where('is_active', true)->first();
    $aboutExcerpt = ($aboutPage && preg_match('/<p[^>]*>(.*?)<\/p>/is', (string) $aboutPage->content, $m) && trim(strip_tags($m[1])) !== '')
        ? trim($m[1])
        : 'Toko terpercaya untuk kebutuhan smartphone dan aksesoris Anda. Produk original, garansi resmi, dan harga terbaik.';

    $siteName = setting('site_name', 'BerkatCell');
    $waLink = wa_href() ?? '#';
    $announcement = setting('site_announcement');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="storefront min-h-screen bg-paper text-ink antialiased" x-data="{ menu: false, search: false }">
        <div class="bg-ink text-[13px] text-white/90">
            <div class="mx-auto flex h-10 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-5 text-white/70">
                    <a href="{{ route('track') }}" wire:navigate class="transition-colors hover:text-gold">Lacak Pesanan</a>
                </div>
            </div>
        </div>

        <header class="sticky top-0 z-50 border-b border-black/[.06] bg-paper/85 backdrop-blur-md">
            <div class="mx-auto flex h-[68px] max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" wire:navigate class="mr-1 shrink-0">
                    <x-wordmark class="text-[22px]" />
                </a>

                <nav class="ml-2 hidden items-center gap-8 text-[15px] font-medium text-ink/70 lg:flex">
                    <a href="{{ route('home') }}" wire:navigate @class(['nav-link transition-colors', 'active text-ink' => request()->routeIs('home'), 'hover:text-ink' => ! request()->routeIs('home')])>Beranda</a>
                    <a href="{{ route('products.index') }}" wire:navigate @class(['nav-link transition-colors', 'active text-ink' => request()->routeIs('products.index') && ! request('sort'), 'hover:text-ink' => ! (request()->routeIs('products.index') && ! request('sort'))])>Produk</a>
                    <a href="{{ route('products.index', ['sort' => 'diskon']) }}" wire:navigate class="nav-link transition-colors hover:text-ink">Promo</a>
                    <a href="{{ route('wishlist.index') }}" wire:navigate @class(['nav-link transition-colors', 'active text-ink' => request()->routeIs('wishlist.index'), 'hover:text-ink' => ! request()->routeIs('wishlist.index')])>Favorit</a>
                    <a href="#kontak" class="nav-link transition-colors hover:text-ink">Kontak</a>
                </nav>

                <div class="ml-auto flex items-center gap-1.5">
                    <button type="button" @click="search = !search" aria-label="Cari"
                            class="grid size-10 place-items-center rounded-full text-ink/70 transition-colors hover:bg-black/5 hover:text-ink">
                        <flux:icon.magnifying-glass class="size-[21px]" />
                    </button>
                    <a href="{{ route('wishlist.index') }}" wire:navigate aria-label="Favorit"
                       class="relative grid size-10 place-items-center rounded-full text-ink/70 transition-colors hover:bg-black/5 hover:text-ink">
                        <flux:icon.heart variant="outline" class="size-[21px]" />
                        @livewire('pages::storefront.wishlist-counter')
                    </a>
                    <a href="{{ route('cart.index') }}" wire:navigate aria-label="Keranjang"
                       class="relative grid size-10 place-items-center rounded-full text-ink/70 transition-colors hover:bg-black/5 hover:text-ink">
                        <flux:icon.shopping-cart variant="outline" class="size-[21px]" />
                        @livewire('pages::storefront.cart-counter')
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate
                           class="ml-1.5 hidden items-center rounded-full bg-gold px-4 py-2 text-sm font-bold text-ink transition hover:bg-gold-deep lg:inline-flex">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate
                           class="ml-1.5 hidden items-center rounded-full bg-gold px-4 py-2 text-sm font-bold text-ink transition hover:bg-gold-deep lg:inline-flex">Masuk / Daftar</a>
                    @endauth
                    <button type="button" @click="menu = true" aria-label="Menu"
                            class="ml-0.5 grid size-10 place-items-center rounded-full text-ink/70 transition-colors hover:bg-black/5 lg:hidden">
                        <flux:icon.bars-3 class="size-[22px]" />
                    </button>
                </div>
            </div>

            <div x-cloak x-show="search" x-transition.opacity.duration.200ms class="border-t border-black/[.06] bg-paper">
                <form action="{{ route('products.index') }}" method="GET" role="search"
                      class="mx-auto flex h-12 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <flux:icon.magnifying-glass class="size-5 shrink-0 text-ink/40" />
                    <input type="text" name="q" value="{{ request('q') }}" aria-label="Cari produk"
                           x-ref="searchInput" @keydown.escape="search = false"
                           x-effect="if (search) $nextTick(() => $refs.searchInput.focus())"
                           placeholder="Cari nama atau SKU produk…"
                           class="h-full flex-1 border-0 bg-transparent text-sm text-ink placeholder:text-ink/40 focus:outline-none focus:ring-0" />
                    <button type="submit" class="rounded-full bg-ink px-5 py-1.5 text-sm font-semibold text-white transition hover:bg-ink-soft">Cari</button>
                </form>
            </div>
        </header>

        <main class="mx-auto min-h-[60vh] w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        @if (request()->routeIs('home') || request()->routeIs('products.index'))
        <section class="border-y border-black/[.06] bg-white">
            <div class="mx-auto grid max-w-7xl grid-cols-2 lg:grid-cols-4">
                @php
                    $trust = [
                        ['shield-check', 'Asli 100%', 'Bergaransi resmi & original'],
                        ['banknotes', 'Harga Kompetitif', 'Penawaran terbaik tiap hari'],
                        ['cube', 'Stok Ready', 'Produk siap kirim'],
                        ['truck', 'Kirim se-Indonesia', 'Dari Sabang sampai Merauke'],
                    ];
                @endphp
                @foreach ($trust as [$icon, $title, $desc])
                    <div class="flex items-center gap-3 border-b border-r border-black/[.06] p-5 lg:border-b-0 [&:nth-child(even)]:border-r-0 lg:[&:nth-child(even)]:border-r">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gold/10 text-gold-deep">
                            <flux:icon :icon="$icon" class="size-6" />
                        </span>
                        <div>
                            <h4 class="text-sm font-bold text-ink">{{ $title }}</h4>
                            <p class="text-xs text-ink/50">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-paper">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl bg-ink px-8 py-12 text-white md:px-14">
                    <div class="absolute inset-0 hero-glow"></div>
                    <div class="relative max-w-xl">
                        <h2 class="text-2xl font-extrabold leading-tight tracking-tight md:text-3xl">
                            Anda Butuh <span class="text-gold">Penawaran Khusus?</span>
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-white/60 md:text-base">
                            Hubungi tim kami sekarang, dan dapatkan penawaran khusus untuk Anda.
                        </p>
                        <a href="{{ $waLink }}" target="_blank" rel="noopener"
                           class="mt-6 inline-flex items-center gap-2 rounded-full bg-gold px-6 py-3 text-sm font-bold text-ink transition hover:bg-gold-deep">
                            <flux:icon.whatsapp class="size-5" /> Konsultasi via WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </section>
        @endif

        <footer id="kontak" class="bg-ink text-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 pb-10 pt-16 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr] lg:px-8">
                <div>
                    <a href="{{ route('home') }}" wire:navigate>
                        <x-wordmark tone="dark" class="text-xl" />
                    </a>
                    <p class="mt-4 max-w-xs text-sm leading-relaxed text-white/55">{!! $aboutExcerpt !!}</p>
                    <div class="mt-6 flex items-center gap-2.5">
                        <a href="{{ setting('site_facebook', '#') }}" target="_blank" rel="noopener" aria-label="Facebook" class="grid size-9 place-items-center rounded-full bg-white/[.06] text-white/70 transition-colors hover:bg-gold hover:text-ink"><svg viewBox="0 0 24 24" class="size-[18px]" fill="currentColor"><path d="M14 9h2.5V6.2H14c-2 0-3.3 1.3-3.3 3.3v1.6H8.5V14h2.2v6h2.8v-6h2.2l.4-2.9h-2.6V9.8c0-.6.3-.8.9-.8Z"/></svg></a>
                        <a href="{{ setting('site_instagram', '#') }}" target="_blank" rel="noopener" aria-label="Instagram" class="grid size-9 place-items-center rounded-full bg-white/[.06] text-white/70 transition-colors hover:bg-gold hover:text-ink"><svg viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4.5" y="4.5" width="15" height="15" rx="4.5"/><circle cx="12" cy="12" r="3.4"/><circle cx="16.4" cy="7.6" r="1" fill="currentColor" stroke="none"/></svg></a>
                        <a href="{{ setting('site_tiktok', '#') }}" target="_blank" rel="noopener" aria-label="TikTok" class="grid size-9 place-items-center rounded-full bg-white/[.06] text-white/70 transition-colors hover:bg-gold hover:text-ink"><svg viewBox="0 0 24 24" class="size-[18px]" fill="currentColor"><path d="M16 4c.4 2 1.6 3.4 3.6 3.7v2.6c-1.4.1-2.6-.3-3.7-1v5.2c0 3-2.1 5-4.9 5C8.4 19.5 6.4 17.6 6.4 15c0-2.4 1.9-4.3 4.3-4.3.3 0 .6 0 .9.1v2.7c-.3-.1-.6-.2-.9-.2-1 0-1.7.8-1.7 1.7 0 1 .7 1.7 1.7 1.7s1.8-.7 1.8-2V4H16Z"/></svg></a>
                        <a href="{{ setting('site_youtube', '#') }}" target="_blank" rel="noopener" aria-label="YouTube" class="grid size-9 place-items-center rounded-full bg-white/[.06] text-white/70 transition-colors hover:bg-gold hover:text-ink"><svg viewBox="0 0 24 24" class="size-[18px]" fill="currentColor"><path d="M21 8.4c-.2-1-.9-1.7-1.8-1.9C17.5 6 12 6 12 6s-5.5 0-7.2.5C3.9 6.7 3.2 7.4 3 8.4 2.6 10 2.6 12 2.6 12s0 2 .4 3.6c.2 1 .9 1.7 1.8 1.9C6.5 18 12 18 12 18s5.5 0 7.2-.5c.9-.2 1.6-.9 1.8-1.9.4-1.6.4-3.6.4-3.6s0-2-.4-3.6ZM10.4 14.6V9.4l4.4 2.6-4.4 2.6Z"/></svg></a>
                    </div>
                </div>

                <div>
                    <h4 class="text-[15px] font-bold">Produk</h4>
                    <ul class="mt-4 space-y-3 text-sm text-white/55">
                        @foreach ($navCategories as $category)
                            <li><a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate class="transition-colors hover:text-gold">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h4 class="text-[15px] font-bold">Informasi</h4>
                    <ul class="mt-4 space-y-3 text-sm text-white/55">
                        <li><a href="{{ route('products.index') }}" wire:navigate class="transition-colors hover:text-gold">Katalog Produk</a></li>
                        <li><a href="{{ route('wishlist.index') }}" wire:navigate class="transition-colors hover:text-gold">Favorit Saya</a></li>
                        @foreach ($footerPages as $page)
                            <li><a href="{{ route('pages.show', $page->slug) }}" wire:navigate class="transition-colors hover:text-gold">{{ $page->title }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h4 class="text-[15px] font-bold">Kontak Kami</h4>
                    <ul class="mt-4 space-y-4 text-sm text-white/60">
                        <li class="flex items-start gap-3">
                            <flux:icon.building-office-2 class="mt-0.5 size-[18px] shrink-0 text-gold" />
                            <span class="font-semibold text-white">{{ setting('site_name', $siteName) }}</span>
                        </li>
                        @if (setting('site_address'))
                            <li class="flex items-start gap-3">
                                <flux:icon.map-pin class="mt-0.5 size-[18px] shrink-0 text-gold" />
                                <span>{{ setting('site_address') }}</span>
                            </li>
                        @endif
                        @if (setting('site_email'))
                            <li class="flex items-start gap-3">
                                <flux:icon.envelope class="mt-0.5 size-[18px] shrink-0 text-gold" />
                                <a href="mailto:{{ setting('site_email') }}" class="transition-colors hover:text-gold">{{ setting('site_email') }}</a>
                            </li>
                        @endif
                        @if (setting('site_phone'))
                            <li class="flex items-start gap-3">
                                <flux:icon.phone class="mt-0.5 size-[18px] shrink-0 text-gold" />
                                <a href="{{ tel_href(setting('site_phone')) }}" target="_blank" class="transition-colors hover:text-gold">{{ setting('site_phone') }}</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="mx-auto max-w-7xl border-t border-white/10 px-4 py-6 text-center text-[13px] text-white/45 sm:px-6 lg:px-8">
                Copyright &copy; {{ now()->year }} {{ $siteName }}. All Rights Reserved.
            </div>
        </footer>

        <div x-cloak x-show="menu" class="fixed inset-0 z-[110] lg:hidden" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="menu = false"></div>
            <div class="absolute right-0 top-0 flex h-full w-80 max-w-[85vw] flex-col bg-ink p-6 text-white"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
                <div class="mb-8 flex items-center justify-between">
                    <x-wordmark tone="dark" class="text-lg" />
                    <button type="button" @click="menu = false" aria-label="Tutup" class="grid size-10 place-items-center rounded-xl bg-white/10">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>
                <nav class="flex flex-col">
                    <a href="{{ route('home') }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-3 text-sm font-medium text-white/85 hover:text-gold">Beranda</a>
                    <a href="{{ route('products.index') }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-3 text-sm font-medium text-white/85 hover:text-gold">Produk</a>
                    <a href="{{ route('products.index', ['sort' => 'diskon']) }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-3 text-sm font-medium text-white/85 hover:text-gold">Promo</a>
                    <a href="{{ route('wishlist.index') }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-3 text-sm font-medium text-white/85 hover:text-gold">Favorit</a>
                    <a href="{{ route('cart.index') }}" wire:navigate @click="menu = false" class="border-b border-white/10 py-3 text-sm font-medium text-white/85 hover:text-gold">Keranjang</a>
                </nav>
                <div class="mt-auto">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate @click="menu = false" class="flex w-full items-center justify-center rounded-full bg-gold px-6 py-3 text-sm font-bold text-ink">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate @click="menu = false" class="flex w-full items-center justify-center rounded-full bg-gold px-6 py-3 text-sm font-bold text-ink">Masuk / Daftar</a>
                    @endauth
                </div>
            </div>
        </div>

        <a href="{{ $waLink }}" target="_blank" rel="noopener" aria-label="Chat WhatsApp"
           class="fixed bottom-5 right-5 z-[90] grid size-14 place-items-center rounded-full bg-[#25D366] text-white shadow-lg shadow-[#25D366]/40 transition hover:scale-105">
            <flux:icon.whatsapp class="size-8" />
        </a>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <script>
            (function () {
                var enable = function () { document.documentElement.classList.add('anim-ready'); };
                if (document.visibilityState === 'visible') {
                    enable();
                } else {
                    document.addEventListener('visibilitychange', function onVis() {
                        if (document.visibilityState === 'visible') {
                            document.removeEventListener('visibilitychange', onVis);
                            enable();
                        }
                    });
                }

                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) {
                        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
                    });
                }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

                function initReveal() {
                    document.querySelectorAll('.reveal:not(.in)').forEach(function (el) {
                        if (el.getBoundingClientRect().top < window.innerHeight) {
                            el.classList.add('in');
                        } else {
                            io.observe(el);
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', initReveal);
                document.addEventListener('livewire:navigated', initReveal);
                document.addEventListener('livewire:init', function () {
                    Livewire.hook('morphed', initReveal);
                });
            })();
        </script>

        @fluxScripts
    </body>
</html>
