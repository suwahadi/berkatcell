<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

@if (filled(config('services.midtrans.client_key')))
    <script
        src="{{ config('services.midtrans.is_production') ? config('services.midtrans.snap_js_production') : config('services.midtrans.snap_js_sandbox') }}"
        data-client-key="{{ config('services.midtrans.client_key') }}"
    ></script>
@endif

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('favicon.ico') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
