<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $line }}; border-radius:6px; border-collapse:separate; overflow:hidden;">
    <tr>
        <td style="background-color:#f8fafc; padding:10px 16px; border-bottom:1px solid {{ $line }};">
            <span style="font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:{{ $muted }};">Rincian Pesanan</span>
            <span style="float:right; font-size:11px; color:#94a3b8; font-family:'Courier New',monospace;">{{ tanggal_id($order->created_at) }}</span>
        </td>
    </tr>

    @foreach ($order->items as $item)
        <tr>
            <td style="padding:12px 16px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size:14px; color:{{ $ink }};">
                            <span style="font-weight:600;">{{ $item->product->name ?? 'Produk' }}</span><br>
                            @if ($item->variant)
                                <span style="font-size:12px; color:{{ $muted }};">Varian: {{ $item->variant->name }}</span><br>
                            @endif
                            <span style="font-size:12px; color:#94a3b8; font-family:'Courier New',monospace;">{{ rupiah($item->price) }} &times; {{ $item->quantity }}</span>
                        </td>
                        <td align="right" style="font-size:14px; font-weight:600; color:{{ $ink }}; font-family:'Courier New',monospace; white-space:nowrap; vertical-align:top;">
                            {{ rupiah($item->total) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach

    <tr>
        <td style="padding:14px 16px; background-color:#f8fafc;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; color:{{ $muted }};">
                <tr>
                    <td style="padding:2px 0;">Subtotal</td>
                    <td align="right" style="padding:2px 0; font-family:'Courier New',monospace; color:{{ $ink }};">{{ rupiah($order->subtotal) }}</td>
                </tr>
                @if ($order->discount_amount > 0)
                    <tr>
                        <td style="padding:2px 0; color:#059669;">Diskon{{ $order->voucher ? ' ('.$order->voucher->code.')' : '' }}</td>
                        <td align="right" style="padding:2px 0; font-family:'Courier New',monospace; color:#059669;">-{{ rupiah($order->discount_amount) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:2px 0;">Ongkos Kirim ({{ strtoupper($order->shipping_courier) }})</td>
                    <td align="right" style="padding:2px 0; font-family:'Courier New',monospace; color:{{ $ink }};">{{ rupiah($order->shipping_cost) }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0 0; border-top:1px solid {{ $line }}; font-size:15px; font-weight:700; color:{{ $ink }};">Total</td>
                    <td align="right" style="padding:8px 0 0; border-top:1px solid {{ $line }}; font-size:15px; font-weight:700; font-family:'Courier New',monospace; color:{{ $accent }};">{{ rupiah($order->grand_total) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
