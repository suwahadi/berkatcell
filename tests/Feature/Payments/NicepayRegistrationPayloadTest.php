<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Services\Payments\Nicepay\NicepayRegistrationPayload;
use App\Services\Payments\PaymentMethods;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NicepayRegistrationPayloadTest extends TestCase
{
    use RefreshDatabase;

    private NicepayRegistrationPayload $builder;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
            'services.nicepay.store_city' => 'Jakarta Utara',
            'services.nicepay.store_state' => 'DKI Jakarta',
            'services.nicepay.store_postcode' => '14440',
        ]);

        SettingService::set('site_name', 'Berkat Cell');
        SettingService::set('site_email', 'toko@example.com');
        SettingService::set('site_phone', '0877-7600-6060');
        SettingService::set('site_address', 'Jl. Contoh Toko No. 8');

        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-10-02 10:15:00', 'Asia/Jakarta'));

        $this->builder = app(NicepayRegistrationPayload::class);
    }

    private function orderWithItem(array $overrides = []): Order
    {
        $product = Product::factory()->create(['name' => 'Samsung Galaxy A16 5G']);
        $order = Order::factory()->create(array_merge([
            'customer_name' => 'Budi Santoso',
            'customer_email' => 'budi@example.com',
            'customer_phone' => '+62 812-3456-7890',
            'shipping_destination_label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910',
            'shipping_address' => 'Jl. Contoh No. 1',
            'subtotal' => 200000,
            'discount_amount' => 20000,
            'shipping_cost' => 15000,
            'grand_total' => 195000,
        ], $overrides));
        $order->items()->create([
            'product_id' => $product->id,
            'price' => 100000,
            'quantity' => 2,
            'total' => 200000,
        ]);

        return $order;
    }

    private function attemptFor(Order $order): PaymentAttempt
    {
        return PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $order->order_number.'-A1',
            'payment_method' => 'indodana',
            'status' => PaymentAttemptStatus::CREATING,
            'gross_amount' => $order->grand_total,
        ]);
    }

    private function cartSum(array $cart): int
    {
        $sum = 0;

        foreach ($cart['item'] as $item) {
            $line = (int) $item['goods_amt'] * (int) $item['goods_quantity'];
            $sum += $item['goods_id'] === 'discount' ? -$line : $line;
        }

        return $sum;
    }

    public function test_field_tetap_dan_token(): void
    {
        $order = $this->orderWithItem();
        $attempt = $this->attemptFor($order);

        $payload = $this->builder->build($order, $attempt);

        $this->assertSame('20261002101500', $payload['timeStamp']);
        $this->assertSame('TESTIMID01', $payload['iMid']);
        $this->assertSame('06', $payload['payMethod']);
        $this->assertSame('IDR', $payload['currency']);
        $this->assertSame('IDNA', $payload['mitraCd']);
        $this->assertSame('195000', $payload['amt']);
        $this->assertSame($attempt->midtrans_order_id, $payload['referenceNo']);
        $this->assertSame('Pesanan '.$order->order_number, $payload['goodsNm']);
        $this->assertSame('budi@example.com', $payload['billingEmail']);
        $this->assertSame('Indonesia', $payload['billingCountry']);
        $this->assertSame('Indonesia', $payload['deliveryCountry']);
        $this->assertSame(route('payments.nicepay.notification'), $payload['dbProcessUrl']);
        $this->assertSame(route('payments.nicepay.callback'), $payload['callBackUrl']);
        $this->assertSame(
            hash('sha256', '20261002101500'.'TESTIMID01'.$attempt->midtrans_order_id.'195000'.'test-merchant-key'),
            $payload['merchantToken'],
        );
    }

    public function test_alamat_diurai_dari_label_rajaongkir(): void
    {
        $order = $this->orderWithItem();

        $payload = $this->builder->build($order, $this->attemptFor($order));

        foreach (['billing', 'delivery'] as $prefix) {
            $this->assertSame('JAKARTA TIMUR', $payload[$prefix.'City']);
            $this->assertSame('DKI JAKARTA', $payload[$prefix.'State']);
            $this->assertSame('13910', $payload[$prefix.'PostCd']);
            $this->assertSame('Jl. Contoh No. 1', $payload[$prefix.'Addr']);
        }
    }

    public function test_label_tak_terurai_dan_pickup_memakai_alamat_toko(): void
    {
        $takTerurai = $this->orderWithItem(['shipping_destination_label' => 'Jakarta']);
        $pickup = $this->orderWithItem([
            'shipping_courier' => Order::PICKUP_COURIER,
            'shipping_destination_label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910',
            'shipping_cost' => 0,
            'grand_total' => 180000,
        ]);

        foreach ([$takTerurai, $pickup] as $order) {
            $payload = $this->builder->build($order, $this->attemptFor($order));

            $this->assertSame('Jakarta Utara', $payload['billingCity']);
            $this->assertSame('DKI Jakarta', $payload['billingState']);
            $this->assertSame('14440', $payload['billingPostCd']);
            $this->assertSame('14440', $payload['deliveryPostCd']);
        }
    }

    public function test_telepon_angka_saja_dan_field_panjang_dipotong(): void
    {
        $order = $this->orderWithItem([
            'customer_name' => str_repeat('Nama ', 30),
            'customer_phone' => '+62 812-3456-7890 ext 12345',
            'shipping_address' => str_repeat('Jalan Panjang ', 20),
        ]);

        $payload = $this->builder->build($order, $this->attemptFor($order));

        $this->assertSame('628123456789012', $payload['billingPhone']);
        $this->assertSame('628123456789012', $payload['deliveryPhone']);
        $this->assertSame(100, mb_strlen($payload['billingNm']));
        $this->assertSame(30, mb_strlen($payload['deliveryNm']));
        $this->assertSame(100, mb_strlen($payload['billingAddr']));
        $this->assertSame(100, mb_strlen($payload['deliveryAddr']));
    }

    public function test_keranjang_memuat_ongkir_dan_diskon_dan_jumlahnya_sama_dengan_total(): void
    {
        $order = $this->orderWithItem();

        $payload = $this->builder->build($order, $this->attemptFor($order));
        $cart = json_decode($payload['cartData'], true);

        $this->assertSame('3', $cart['count']);
        $this->assertSame(['shippingfee', 'discount'], [$cart['item'][1]['goods_id'], $cart['item'][2]['goods_id']]);
        $this->assertSame('Samsung Galaxy A16 5G', $cart['item'][0]['goods_name']);
        $this->assertSame('100000', $cart['item'][0]['goods_amt']);
        $this->assertSame('2', $cart['item'][0]['goods_quantity']);
        $this->assertSame('others', $cart['item'][0]['goods_type']);
        $this->assertSame('TESTIMID01', $cart['item'][0]['goods_sellers_id']);
        $this->assertSame('Berkat Cell', $cart['item'][0]['goods_sellers_name']);
        $this->assertSame('20000', $cart['item'][2]['goods_amt']);
        $this->assertSame(195000, $this->cartSum($cart));
    }

    public function test_keranjang_tak_cocok_dikirim_satu_baris(): void
    {
        $order = $this->orderWithItem(['grand_total' => 199999]);

        $payload = $this->builder->build($order, $this->attemptFor($order));
        $cart = json_decode($payload['cartData'], true);

        $this->assertSame('1', $cart['count']);
        $this->assertSame('Pesanan '.$order->order_number, $cart['item'][0]['goods_name']);
        $this->assertSame('199999', $cart['item'][0]['goods_amt']);
        $this->assertSame('1', $cart['item'][0]['goods_quantity']);
    }

    public function test_keranjang_terlalu_panjang_dikirim_satu_baris(): void
    {
        $product = Product::factory()->create(['name' => str_repeat('Produk dengan nama panjang ', 4)]);
        $order = Order::factory()->create([
            'shipping_destination_label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910',
            'subtotal' => 40000,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 40000,
        ]);

        foreach (range(1, 40) as $ignored) {
            $order->items()->create(['product_id' => $product->id, 'price' => 1000, 'quantity' => 1, 'total' => 1000]);
        }

        $payload = $this->builder->build($order, $this->attemptFor($order));
        $cart = json_decode($payload['cartData'], true);

        $this->assertLessThanOrEqual(4000, strlen($payload['cartData']));
        $this->assertSame('1', $cart['count']);
        $this->assertSame('40000', $cart['item'][0]['goods_amt']);
    }

    public function test_penjual_diambil_dari_setting_toko(): void
    {
        $order = $this->orderWithItem();

        $payload = $this->builder->build($order, $this->attemptFor($order));
        $seller = json_decode($payload['sellers'], true)[0];

        $this->assertSame('TESTIMID01', $seller['sellersId']);
        $this->assertSame('Berkat Cell', $seller['sellersNm']);
        $this->assertSame('toko@example.com', $seller['sellersEmail']);
        $this->assertSame('Jl. Contoh Toko No. 8', $seller['sellersAddress']['sellerAddr']);
        $this->assertSame('Jakarta Utara', $seller['sellersAddress']['sellerCity']);
        $this->assertSame('14440', $seller['sellersAddress']['sellerPostCd']);
        $this->assertSame('087776006060', $seller['sellersAddress']['sellerPhone']);
        $this->assertSame('ID', $seller['sellersAddress']['sellerCountry']);
    }

    public function test_tenor_default_mengikuti_nominal(): void
    {
        $kecil = $this->orderWithItem();
        $besar = $this->orderWithItem([
            'subtotal' => 3000000,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 3000000,
        ]);

        $payloadKecil = $this->builder->build($kecil, $this->attemptFor($kecil));
        $payloadBesar = $this->builder->build($besar, $this->attemptFor($besar));

        $this->assertSame(['1', '1'], [$payloadKecil['instmntType'], $payloadKecil['instmntMon']]);
        $this->assertSame(['2', '3'], [$payloadBesar['instmntType'], $payloadBesar['instmntMon']]);
    }

    public function test_user_ip_hanya_ipv4(): void
    {
        $order = $this->orderWithItem();
        $attempt = $this->attemptFor($order);

        request()->server->set('REMOTE_ADDR', '203.0.113.7');
        $this->assertSame('203.0.113.7', $this->builder->build($order, $attempt)['userIP']);

        request()->server->set('REMOTE_ADDR', '2001:db8::1');
        $this->assertSame('127.0.0.1', $this->builder->build($order, $attempt)['userIP']);
    }
}
