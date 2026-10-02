<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MidtransPaymentAttemptService
{
    /**
     * Kunci internal => nilai `enabled_payments` Snap. Urutan menentukan urutan
     * tampil di halaman invoice; elemen pertama jadi metode terpilih default.
     *
     * Catatan kanal:
     * - `gopay`   : QRIS GoPay Dynamic. Snap menampilkan QR bila dibuka dengan
     *               opsi `gopayMode: 'qr'` (lihat ⚡order-success.blade.php).
     * - `echannel`: Bank Mandiri Bill Payment. Notifikasi mengembalikan
     *               biller_code + bill_key, bukan va_numbers.
     */
    public const METHOD_MAP = [
        'gopay' => 'gopay',
        'akulaku' => 'akulaku',
        'bsi_va' => 'bsi_va',
        'bni_va' => 'bni_va',
        'bri_va' => 'bri_va',
        'echannel' => 'echannel',
        'permata_va' => 'permata_va',
    ];

    public function __construct(
        private readonly MidtransClient $client,
        private readonly MidtransWebhookService $webhook,
    ) {}

    public static function supportedMethods(): array
    {
        return array_keys(self::METHOD_MAP);
    }

    public function start(Order $order, PaymentAttempt $attempt, string $paymentMethod): void
    {
        $payload = $this->buildSnapPayload($order, $attempt, $paymentMethod);
        $response = $this->client->createSnapTransaction($payload);

        $attempt->update([
            'status' => PaymentAttemptStatus::PENDING,
            'snap_token' => $response['token'] ?? null,
            'redirect_url' => $response['redirect_url'] ?? null,
            'snap_request_payload' => $payload,
            'snap_response_payload' => $response,
        ]);
    }

    public function sync(PaymentAttempt $attempt): void
    {
        $status = $this->client->status($attempt->midtrans_order_id);

        if (! isset($status['transaction_status'])) {
            return;
        }

        $this->webhook->syncFromStatus($attempt, $status);
    }

    private function buildSnapPayload(Order $order, PaymentAttempt $attempt, string $paymentMethod): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $attempt->midtrans_order_id,
                'gross_amount' => (int) $order->grand_total,
            ],
            'enabled_payments' => [self::METHOD_MAP[$paymentMethod]],
            'customer_details' => [
                'first_name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],

            'expiry' => [
                'unit' => 'minute',
                'duration' => order_expiry_minutes(),
            ],
            'callbacks' => [
                'finish' => route('payments.midtrans.finish'),
            ],
        ];

        $items = $this->buildItemDetails($order);

        if ($items !== []) {
            $payload['item_details'] = $items;
        }

        return $payload;
    }

    /**
     * Rincian item; wajib untuk kanal paylater seperti Akulaku. Midtrans menolak
     * transaksi bila jumlah baris tidak persis sama dengan gross_amount, jadi ongkir
     * dan diskon ikut jadi baris tersendiri. Kembalikan [] bila tidak bisa dicocokkan.
     * Diskon di-clamp seperti OrderService: max(0, subtotal - diskon) + ongkir.
     */
    private function buildItemDetails(Order $order): array
    {
        $order->loadMissing('items.product', 'items.variant');

        if ($order->items->isEmpty()) {
            return [];
        }

        $items = $order->items->map(fn (OrderItem $item): array => [
            'id' => (string) ($item->variant_id ?? $item->product_id ?? $item->id),
            'price' => (int) $item->price,
            'quantity' => (int) $item->quantity,
            'name' => Str::limit(trim(($item->product?->name ?? 'Produk').' '.($item->variant?->name ?? '')), 50, ''),
        ])->all();

        $discount = min((int) $order->discount_amount, (int) $order->subtotal);

        if ($discount > 0) {
            $items[] = ['id' => 'DISCOUNT', 'price' => -$discount, 'quantity' => 1, 'name' => 'Diskon voucher'];
        }

        if ((int) $order->shipping_cost > 0) {
            $items[] = [
                'id' => 'SHIPPING',
                'price' => (int) $order->shipping_cost,
                'quantity' => 1,
                'name' => Str::limit('Ongkir '.$order->shippingCourierName(), 50, ''),
            ];
        }

        $sum = array_sum(array_map(fn (array $item): int => $item['price'] * $item['quantity'], $items));

        if ($sum !== (int) $order->grand_total) {
            Log::warning('Midtrans: item_details tidak cocok dengan grand_total; dikirim tanpa rincian item.', [
                'order_id' => $order->id,
                'item_details_sum' => $sum,
                'grand_total' => $order->grand_total,
            ]);

            return [];
        }

        return $items;
    }
}
