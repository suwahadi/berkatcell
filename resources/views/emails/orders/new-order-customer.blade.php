@extends('emails.layout')

@section('eyebrow', 'Pesanan Diterima')
@section('preheader', 'Pesanan '.$order->order_number.' telah kami terima — selesaikan pembayaran '.rupiah($order->grand_total).'.')
@section('heading', 'Terima kasih, '.$order->customer_name.'!')

@section('content')
    <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:{{ $muted }};">
        Pesanan Anda telah kami terima dan saat ini <strong style="color:{{ $ink }};">menunggu pembayaran</strong>.
        Segera selesaikan pembayaran agar produk Anda dapat kami proses untuk pengiriman.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:#fffbeb; border:1px solid #fde68a; border-radius:6px;">
        <tr>
            <td style="padding:14px 16px;">
                <span style="font-size:12px; color:#92400e;">Nomor Pesanan</span><br>
                <span style="font-size:18px; font-weight:700; letter-spacing:0.05em; color:#0f172a; font-family:'Courier New',monospace;">{{ $order->order_number }}</span>
            </td>
            <td align="right" style="padding:14px 16px;">
                <span style="display:inline-block; background-color:#f59e0b; color:#ffffff; font-size:11px; font-weight:700; padding:5px 12px; border-radius:999px;">Menunggu Pembayaran</span>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:{{ $accent }}; border-radius:6px;">
                <a href="{{ route('checkout.success', $order) }}" target="_blank"
                   style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
                    Selesaikan Pembayaran
                </a>
            </td>
        </tr>
    </table>

    @include('emails.orders.partials.summary')

    <div style="height:16px;"></div>

    @include('emails.orders.partials.shipping', ['heading' => 'Dikirim Ke'])

    <p style="margin:20px 0 0; font-size:13px; line-height:1.7; color:{{ $muted }};">
        Jika tombol di atas tidak berfungsi, salin tautan berikut ke peramban Anda:<br>
        <a href="{{ route('checkout.success', $order) }}" style="color:{{ $accent }}; word-break:break-all;">{{ route('checkout.success', $order) }}</a>
    </p>
@endsection
