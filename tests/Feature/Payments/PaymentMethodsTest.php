<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Services\Payments\Midtrans\MidtransPaymentAttemptService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

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
}
