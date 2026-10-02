<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_tujuh_kanal_midtrans_tersedia_dengan_urutan_tetap(): void
    {
        $order = Order::factory()->create();

        $this->assertSame(
            MidtransPaymentAttemptService::supportedMethods(),
            array_keys(PaymentMethods::available($order, null)),
        );
    }

    public function test_setiap_metode_punya_data_tampilan(): void
    {
        $order = Order::factory()->create();

        foreach (PaymentMethods::available($order, null) as $key => $method) {
            $this->assertSame(['provider', 'name', 'label', 'type', 'brand'], array_keys($method), $key);
        }
    }

    public function test_provider_dan_nama_metode(): void
    {
        $this->assertSame(PaymentMethods::MIDTRANS, PaymentMethods::providerFor('bni_va'));
        $this->assertNull(PaymentMethods::providerFor('bca_va'));
        $this->assertSame('Virtual Account BNI', PaymentMethods::name('bni_va'));
        $this->assertSame('bca_va', PaymentMethods::name('bca_va'));
    }

    public function test_aktor_per_penyedia(): void
    {
        $this->assertSame('Midtrans (otomatis)', PaymentMethods::actorFor(PaymentMethods::MIDTRANS));
        $this->assertSame('Indodana via Nicepay (otomatis)', PaymentMethods::actorFor(PaymentMethods::NICEPAY));
    }

    private function enableNicepay(bool $adminOnly): void
    {
        config([
            'services.nicepay.enabled' => true,
            'services.nicepay.admin_only' => $adminOnly,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
        ]);
    }

    private function eligibleOrder(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_email' => 'budi@example.com',
            'grand_total' => 1500000,
        ], $overrides));
    }

    public function test_indodana_tersembunyi_saat_flag_mati_atau_kredensial_kosong(): void
    {
        $order = $this->eligibleOrder();
        $admin = User::factory()->admin()->create();

        $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, $admin));

        $this->enableNicepay(false);
        config(['services.nicepay.merchant_key' => '']);

        $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, $admin));
    }

    public function test_mode_khusus_admin_hanya_menampilkan_indodana_ke_admin(): void
    {
        $this->enableNicepay(true);
        $order = $this->eligibleOrder();

        $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, null));
        $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, User::factory()->create()));

        $forAdmin = PaymentMethods::available($order, User::factory()->admin()->create());

        $this->assertSame(PaymentMethods::INDODANA, array_key_last($forAdmin));
        $this->assertSame(PaymentMethods::NICEPAY, $forAdmin[PaymentMethods::INDODANA]['provider']);
        $this->assertSame('gopay', array_key_first($forAdmin));
    }

    public function test_tanpa_mode_admin_indodana_tampil_untuk_semua(): void
    {
        $this->enableNicepay(false);

        $this->assertArrayHasKey(PaymentMethods::INDODANA, PaymentMethods::available($this->eligibleOrder(), null));
    }

    public function test_indodana_tersembunyi_di_luar_batas_nominal_atau_email_terlalu_panjang(): void
    {
        $this->enableNicepay(false);

        $tidakMemenuhi = [
            $this->eligibleOrder(['grand_total' => 9999]),
            $this->eligibleOrder(['grand_total' => 50000001]),
            $this->eligibleOrder(['customer_email' => str_repeat('a', 30).'@example.com']),
        ];

        foreach ($tidakMemenuhi as $order) {
            $this->assertArrayNotHasKey(PaymentMethods::INDODANA, PaymentMethods::available($order, null));
        }

        foreach ([10000, 50000000] as $batas) {
            $this->assertArrayHasKey(
                PaymentMethods::INDODANA,
                PaymentMethods::available($this->eligibleOrder(['grand_total' => $batas]), null),
            );
        }
    }
}
