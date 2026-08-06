<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Services\OrderActivityService;
use Illuminate\Support\Facades\DB;
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
        private readonly OrderActivityService $activities,
        private readonly MidtransWebhookService $webhook,
    ) {}

    public static function supportedMethods(): array
    {
        return array_keys(self::METHOD_MAP);
    }

    public function createOrReuseActiveAttempt(Order $order, string $paymentMethod): PaymentAttempt
    {
        if (! array_key_exists($paymentMethod, self::METHOD_MAP)) {
            throw new BusinessRuleException('Metode pembayaran tidak didukung.');
        }

        return DB::transaction(function () use ($order, $paymentMethod): PaymentAttempt {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === OrderStatus::PAID) {
                throw new BusinessRuleException('Pesanan sudah lunas. Tidak bisa membuat pembayaran baru.');
            }

            if ($lockedOrder->status === OrderStatus::CANCELLED) {
                throw new BusinessRuleException('Pesanan sudah dibatalkan.');
            }

            $activeAttempt = $lockedOrder->activePaymentAttempt;

            if (
                $activeAttempt
                && $activeAttempt->payment_method === $paymentMethod
                && $activeAttempt->status instanceof PaymentAttemptStatus
                && $activeAttempt->status->isOpen()
            ) {
                return $activeAttempt;
            }

            if ($activeAttempt && $activeAttempt->status instanceof PaymentAttemptStatus && $activeAttempt->status->isOpen()) {
                $activeAttempt->update(['status' => PaymentAttemptStatus::SUPERSEDED]);
                rescue(fn () => $this->client->cancel($activeAttempt->midtrans_order_id), report: false);
            }

            $nextSequence = (int) PaymentAttempt::query()
                ->where('order_id', $lockedOrder->id)
                ->max('attempt_sequence') + 1;

            $midtransOrderId = $lockedOrder->order_number.'-A'.$nextSequence;

            $attempt = PaymentAttempt::query()->create([
                'order_id' => $lockedOrder->id,
                'attempt_sequence' => $nextSequence,
                'midtrans_order_id' => $midtransOrderId,
                'payment_method' => $paymentMethod,
                'status' => PaymentAttemptStatus::CREATING,
                'gross_amount' => $lockedOrder->grand_total,
                'activated_at' => now(),
                'expired_at' => now()->addMinutes(order_expiry_minutes()),
            ]);

            $payload = $this->buildSnapPayload($lockedOrder, $attempt, $paymentMethod);
            $response = $this->client->createSnapTransaction($payload);

            $attempt->update([
                'status' => PaymentAttemptStatus::PENDING,
                'snap_token' => $response['token'] ?? null,
                'redirect_url' => $response['redirect_url'] ?? null,
                'snap_request_payload' => $payload,
                'snap_response_payload' => $response,
            ]);

            $lockedOrder->update([
                'active_payment_attempt_id' => $attempt->id,
            ]);

            $this->activities->paymentStarted($lockedOrder, $paymentMethod);

            return $attempt->refresh();
        });
    }

    public function syncActiveAttempt(Order $order): void
    {
        $order->loadMissing('activePaymentAttempt');
        $attempt = $order->activePaymentAttempt;

        if (
            ! $attempt instanceof PaymentAttempt
            || ! $attempt->status instanceof PaymentAttemptStatus
            || ! $attempt->status->isOpen()
        ) {
            return;
        }

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

        // Diskon di-clamp seperti OrderService: max(0, subtotal - diskon) + ongkir.
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
