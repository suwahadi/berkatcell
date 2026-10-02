<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="robots" content="noindex" />
        <title>Lanjut ke Indodana - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-dvh items-center justify-center bg-paper px-4 py-10 text-ink antialiased">
        <main class="w-full max-w-sm">
            <x-wordmark class="text-[22px]" />

            <h1 class="mt-8 text-xl font-extrabold tracking-tight">Lanjut ke Indodana</h1>
            <p class="mt-2 text-sm leading-relaxed text-ink/70">
                Pesanan {{ $order->order_number }}, total {{ rupiah((int) $order->grand_total) }}. Tenor cicilan dipilih di halaman Indodana.
            </p>

            <form id="nicepay-payment" method="POST" action="{{ $action }}" class="mt-6">
                @foreach ($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}" />
                @endforeach

                <button type="submit" class="w-full rounded-lg bg-ink px-4 py-3 text-sm font-semibold text-white transition hover:bg-ink-soft active:bg-ink-mute focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2 focus-visible:ring-offset-paper">
                    Buka halaman Indodana
                </button>
            </form>

            <a href="{{ route('checkout.success', ['order' => $order->uuid]) }}" class="mt-2 inline-block py-3 text-sm text-ink/70 underline underline-offset-4 hover:text-ink">
                Kembali ke pesanan
            </a>
        </main>

        <script>
            document.getElementById('nicepay-payment').submit();
        </script>
    </body>
</html>
