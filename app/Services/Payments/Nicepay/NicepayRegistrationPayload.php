<?php

declare(strict_types=1);

namespace App\Services\Payments\Nicepay;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Log;

class NicepayRegistrationPayload
{
    private const MITRA_INDODANA = 'IDNA';

    private const MAX_CART_LENGTH = 4000;

    private const ONE_MONTH_TENOR_LIMIT = 2_000_000;

    public function __construct(
        private readonly NicepayClient $client,
    ) {}

    public function build(Order $order, PaymentAttempt $attempt): array
    {
        $order->loadMissing('items.product', 'items.variant');

        $timeStamp = $this->client->timestamp();
        $amount = (int) $order->grand_total;
        $address = $this->address($order);
        $phone = mb_substr((string) preg_replace('/\D+/', '', (string) $order->customer_phone), 0, 15);
        $street = mb_substr((string) $order->shipping_address, 0, 100);
        $oneMonth = $amount <= self::ONE_MONTH_TENOR_LIMIT;

        return [
            'timeStamp' => $timeStamp,
            'iMid' => $this->client->merchantId(),
            'payMethod' => NicepayClient::PAY_METHOD_PAYLATER,
            'currency' => 'IDR',
            'amt' => (string) $amount,
            'referenceNo' => $attempt->midtrans_order_id,
            'goodsNm' => 'Pesanan '.$order->order_number,
            'billingNm' => mb_substr((string) $order->customer_name, 0, 100),
            'billingPhone' => $phone,
            'billingEmail' => (string) $order->customer_email,
            'billingAddr' => $street,
            'billingCity' => $address['city'],
            'billingState' => $address['state'],
            'billingPostCd' => $address['postcode'],
            'billingCountry' => 'Indonesia',
            'deliveryNm' => mb_substr((string) $order->customer_name, 0, 30),
            'deliveryPhone' => $phone,
            'deliveryAddr' => $street,
            'deliveryCity' => $address['city'],
            'deliveryState' => $address['state'],
            'deliveryPostCd' => $address['postcode'],
            'deliveryCountry' => 'Indonesia',
            'dbProcessUrl' => route('payments.nicepay.notification'),
            'userIP' => $this->userIp(),
            'userAgent' => mb_substr((string) request()->userAgent(), 0, 255),
            'cartData' => json_encode($this->cart($order), JSON_UNESCAPED_SLASHES),
            'sellers' => json_encode($this->sellers(), JSON_UNESCAPED_SLASHES),
            'instmntType' => $oneMonth ? '1' : '2',
            'instmntMon' => $oneMonth ? '1' : '3',
            'mitraCd' => self::MITRA_INDODANA,
            'merchantToken' => $this->client->transactionToken($timeStamp, $attempt->midtrans_order_id, $amount),
        ];
    }

    /**
     * Label RajaOngkir berformat "KELURAHAN, KECAMATAN, KOTA, PROVINSI, KODEPOS".
     * Pesanan pickup dan label yang tidak cocok dengan format itu memakai alamat toko.
     */
    private function address(Order $order): array
    {
        $parts = array_map('trim', explode(',', (string) $order->shipping_destination_label));

        if (! $order->isPickup() && count($parts) === 5 && ctype_digit($parts[4])) {
            return [
                'city' => mb_substr($parts[2], 0, 50),
                'state' => mb_substr($parts[3], 0, 50),
                'postcode' => $parts[4],
            ];
        }

        if (! $order->isPickup()) {
            Log::warning('Nicepay: label tujuan tidak bisa diurai; memakai alamat toko.', ['order_id' => $order->id]);
        }

        return [
            'city' => (string) config('services.nicepay.store_city'),
            'state' => (string) config('services.nicepay.store_state'),
            'postcode' => (string) config('services.nicepay.store_postcode'),
        ];
    }

    /**
     * Nicepay menjumlahkan semua baris (harga satuan x jumlah) dan menolak registrasi
     * bila hasilnya berbeda dari amt. Baris ongkir diterima, tetapi untuk Indodana baris
     * diskon ditolak sandbox (nilai positif: 9907, nilai negatif: 1002), jadi pesanan
     * berdiskon dikirim sebagai satu baris senilai total. Hal yang sama berlaku bila
     * rincian tidak cocok dengan grand_total atau melebihi 4.000 karakter.
     */
    private function cart(Order $order): array
    {
        $items = [];
        $sum = 0;

        foreach ($order->items as $item) {
            $items[] = $this->cartLine(
                (string) ($item->variant_id ?? $item->product_id ?? $item->id),
                trim(($item->product?->name ?? 'Produk').' '.($item->variant?->name ?? '')),
                (int) $item->price,
                (int) $item->quantity,
                $this->productUrl($item),
            );
            $sum += (int) $item->price * (int) $item->quantity;
        }

        $shipping = (int) $order->shipping_cost;

        if ($shipping > 0) {
            $items[] = $this->cartLine('shippingfee', 'Ongkos kirim', $shipping, 1, route('home'));
            $sum += $shipping;
        }

        $hasDiscount = (int) $order->discount_amount > 0;
        $cart = ['count' => (string) count($items), 'item' => $items];

        $itemised = ! $hasDiscount
            && $order->items->isNotEmpty()
            && $sum === (int) $order->grand_total
            && strlen((string) json_encode($cart, JSON_UNESCAPED_SLASHES)) <= self::MAX_CART_LENGTH;

        if ($itemised) {
            return $cart;
        }

        if (! $hasDiscount) {
            Log::warning('Nicepay: cartData tidak cocok dengan total atau terlalu panjang; dikirim satu baris.', [
                'order_id' => $order->id,
                'cart_sum' => $sum,
                'grand_total' => $order->grand_total,
            ]);
        }

        return [
            'count' => '1',
            'item' => [$this->cartLine(
                (string) $order->order_number,
                'Pesanan '.$order->order_number,
                (int) $order->grand_total,
                1,
                route('home'),
            )],
        ];
    }

    private function cartLine(string $id, string $name, int $amount, int $quantity, string $url): array
    {
        return [
            'goods_id' => $id,
            'goods_name' => mb_substr($name, 0, 100),
            'goods_amt' => (string) $amount,
            'goods_type' => 'others',
            'goods_quantity' => (string) $quantity,
            'goods_url' => $url,
            'goods_sellers_id' => $this->client->merchantId(),
            'goods_sellers_name' => $this->storeName(),
        ];
    }

    private function productUrl(OrderItem $item): string
    {
        return $item->product ? route('products.show', $item->product) : route('home');
    }

    private function sellers(): array
    {
        return [[
            'sellersId' => $this->client->merchantId(),
            'sellersNm' => $this->storeName(),
            'sellersEmail' => (string) setting('site_email', ''),
            'sellersUrl' => url('/'),
            'sellersAddress' => [
                'sellerNm' => $this->storeName(),
                'sellerLastNm' => $this->storeName(),
                'sellerAddr' => mb_substr((string) setting('site_address', ''), 0, 100),
                'sellerCity' => (string) config('services.nicepay.store_city'),
                'sellerPostCd' => (string) config('services.nicepay.store_postcode'),
                'sellerPhone' => (string) preg_replace('/\D+/', '', (string) setting('site_phone', '')),
                'sellerCountry' => 'ID',
            ],
        ]];
    }

    private function storeName(): string
    {
        return (string) setting('site_name', config('app.name'));
    }

    private function userIp(): string
    {
        $ip = (string) request()->ip();

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1';
    }
}
