<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class NicepayInvoicePageTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::storefront.order-success';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'test-server-key',
            'services.midtrans.client_key' => 'test-client-key',
            'services.midtrans.is_production' => false,
            'services.nicepay.enabled' => true,
            'services.nicepay.admin_only' => false,
            'services.nicepay.is_production' => false,
            'services.nicepay.imid' => 'TESTIMID01',
            'services.nicepay.merchant_key' => 'test-merchant-key',
            'services.nicepay.store_city' => 'Jakarta Utara',
            'services.nicepay.store_state' => 'DKI Jakarta',
            'services.nicepay.store_postcode' => '14440',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*/nicepay/direct/v2/registration' => Http::response([
                'resultCd' => '0000',
                'resultMsg' => 'SUCCESS',
                'tXid' => 'TESTIMID0106202610021015009999',
            ], 200),
            '*/nicepay/direct/v2/cancel' => Http::response(['resultCd' => '0000'], 200),
            '*/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-abc',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/abc',
            ], 201),
            '*/v2/*/cancel' => Http::response(['status_code' => '200'], 200),
        ]);
        Queue::fake();
    }

    private function order(): Order
    {
        return Order::factory()->create([
            'customer_email' => 'budi@example.com',
            'grand_total' => 1500000,
        ]);
    }

    private function activeNicepayAttempt(Order $order): PaymentAttempt
    {
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'midtrans_order_id' => $order->order_number.'-A1',
            'midtrans_transaction_id' => 'TESTIMID0106202610021015009999',
            'payment_method' => PaymentMethods::INDODANA,
            'status' => PaymentAttemptStatus::PENDING,
            'gross_amount' => $order->grand_total,
            'snap_token' => null,
            'redirect_url' => null,
            'expired_at' => now()->addDay(),
        ]);
        $order->update(['active_payment_attempt_id' => $attempt->id]);

        return $attempt;
    }

    public function test_indodana_tidak_tampil_saat_flag_mati(): void
    {
        config(['services.nicepay.enabled' => false]);

        Livewire::test(self::COMPONENT, ['order' => $this->order()])
            ->assertDontSee('Indodana');
    }

    public function test_mode_khusus_admin_hanya_menampilkan_indodana_ke_admin(): void
    {
        config(['services.nicepay.admin_only' => true]);
        $order = $this->order();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertDontSee('Indodana');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(self::COMPONENT, ['order' => $order])
            ->assertSee('Indodana');
    }

    public function test_tamu_melihat_indodana_saat_mode_admin_mati(): void
    {
        Livewire::test(self::COMPONENT, ['order' => $this->order()])
            ->assertSee('Indodana')
            ->assertSet('payment_method', 'gopay');
    }

    public function test_bayar_indodana_mengarahkan_ke_halaman_nicepay(): void
    {
        $order = $this->order();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->set('payment_method', PaymentMethods::INDODANA)
            ->call('pay')
            ->assertHasNoErrors()
            ->assertNotDispatched('snap-pay')
            ->assertRedirect(route('payments.nicepay.pay', ['order' => $order->uuid]));

        $this->assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'provider' => PaymentMethods::NICEPAY,
            'payment_method' => PaymentMethods::INDODANA,
            'status' => PaymentAttemptStatus::PENDING->value,
        ]);
    }

    public function test_non_admin_tidak_bisa_memaksa_indodana_dalam_mode_admin(): void
    {
        config(['services.nicepay.admin_only' => true]);
        $order = $this->order();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->set('payment_method', PaymentMethods::INDODANA)
            ->call('pay')
            ->assertHasErrors(['payment_method']);

        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_lanjutkan_pembayaran_indodana_mengarahkan_ulang_tanpa_registration_baru(): void
    {
        $order = $this->order();
        $this->activeNicepayAttempt($order);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->call('continuePayment')
            ->assertRedirect(route('payments.nicepay.pay', ['order' => $order->uuid]));

        $this->assertSame(1, PaymentAttempt::query()->where('order_id', $order->id)->count());
        Http::assertNothingSent();
    }

    public function test_kartu_attempt_aktif_menampilkan_nama_indodana(): void
    {
        config(['services.nicepay.admin_only' => true]);
        $order = $this->order();
        $this->activeNicepayAttempt($order);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertSee('Indodana PayLater')
            ->assertSee('Tenor cicilan dipilih di halaman Indodana');
    }

    public function test_alur_midtrans_tidak_berubah_saat_nicepay_aktif(): void
    {
        $order = $this->order();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->set('payment_method', 'bni_va')
            ->call('pay')
            ->assertDispatched('snap-pay', token: 'snap-token-abc')
            ->assertNoRedirect();
    }

    public function test_bayar_saat_pembayaran_lain_sedang_diproses_tidak_error(): void
    {
        $order = $this->order();
        Cache::lock('order-pay:'.$order->id, 60)->get();

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->set('payment_method', 'bni_va')
            ->call('pay')
            ->assertNotDispatched('snap-pay')
            ->assertNoRedirect();

        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_polling_lebih_jarang_untuk_tagihan_indodana(): void
    {
        $order = $this->order();
        $this->activeNicepayAttempt($order);

        Livewire::test(self::COMPONENT, ['order' => $order])
            ->assertSeeHtml('wire:poll.120s="refreshStatus"');
    }

    public function test_polling_tetap_30_detik_tanpa_tagihan_indodana(): void
    {
        Livewire::test(self::COMPONENT, ['order' => $this->order()])
            ->assertSeeHtml('wire:poll.30s="refreshStatus"');
    }
}
