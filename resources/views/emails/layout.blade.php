@php
    $siteName = setting('site_name', config('services.brevo.sender_name', 'CV. Jajar Wayang'));
    $siteEmail = setting('site_email', config('services.brevo.sender_email'));
    $sitePhone = setting('site_phone');
    $siteAddress = setting('site_address');
@endphp
<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f1f5f9;">
        @yield('preheader')
        &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

                    <tr>
                        <td style="background-color:{{ $ink }}; border-radius:8px 8px 0 0; padding:20px 28px;">
                            <span style="font-size:18px; font-weight:700; letter-spacing:-0.01em; color:#ffffff;">{{ $siteName }}</span>
                            <span style="float:right; font-size:12px; color:{{ $accent }}; font-weight:600;">@yield('eyebrow', 'Notifikasi Pesanan')</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; padding:28px;">
                            <h1 style="margin:0 0 16px; font-size:20px; line-height:1.3; font-weight:700; color:{{ $ink }};">@yield('heading')</h1>
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; border-top:1px solid {{ $line }}; border-radius:0 0 8px 8px; padding:20px 28px;">
                            <p style="margin:0 0 6px; font-size:12px; line-height:1.6; color:{{ $muted }};">
                                @if ($siteAddress){{ $siteAddress }}<br>@endif
                                @if ($sitePhone !== '')Telp: {{ $sitePhone }}@if ($siteEmail) &middot; @endif @endif
                                @if ($siteEmail)<a href="mailto:{{ $siteEmail }}" style="color:{{ $accent }}; text-decoration:none;">{{ $siteEmail }}</a>@endif
                            </p>
                            <p style="margin:0; font-size:11px; color:#94a3b8;">
                                Email ini dikirim otomatis oleh sistem {{ $siteName }}. Mohon tidak membalas email ini.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
