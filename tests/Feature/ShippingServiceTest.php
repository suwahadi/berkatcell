<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Services\ShippingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.rajaongkir.base_url', 'https://api.test/v1');
        config()->set('services.rajaongkir.key', 'dummy-key');
        config()->set('services.rajaongkir.mock', false);

        // Origin gudang kini dibaca dari setting `origin_district_id`;
        // pre-warm cache (prefix SettingService) agar tidak menyentuh DB.
        Cache::forever('setting.origin_district_id', '17673');
    }

    public function test_pencarian_destinasi_dipetakan(): void
    {
        Http::fake([
            'api.test/v1/destination/*' => Http::response([
                'meta' => ['code' => 200, 'status' => 'success'],
                'data' => [
                    ['id' => 17673, 'label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910', 'province_name' => 'DKI JAKARTA', 'city_name' => 'JAKARTA TIMUR', 'district_name' => 'CAKUNG', 'subdistrict_name' => 'CAKUNG BARAT', 'zip_code' => '13910'],
                ],
            ], 200),
        ]);

        $result = (new ShippingService)->searchDestinations('Cakung Barat');

        $this->assertCount(1, $result);
        $this->assertSame(17673, $result[0]['id']);
        $this->assertSame('JAKARTA TIMUR', $result[0]['city']);
        $this->assertSame('13910', $result[0]['zip']);
    }

    public function test_keyword_terlalu_pendek_tidak_memanggil_api(): void
    {
        Http::fake();

        $result = (new ShippingService)->searchDestinations('ab');

        $this->assertSame([], $result);
        Http::assertNothingSent();
    }

    public function test_404_destinasi_dikembalikan_sebagai_kosong(): void
    {
        Http::fake([
            'api.test/v1/destination/*' => Http::response([
                'meta' => ['message' => 'Domestic Destinations Data not found', 'code' => 404, 'status' => 'error'],
                'data' => null,
            ], 404),
        ]);

        $result = (new ShippingService)->searchDestinations('xyztidakada');

        $this->assertSame([], $result);
    }

    public function test_hasil_destinasi_di_cache(): void
    {
        Http::fake([
            'api.test/v1/destination/*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [['id' => 1, 'label' => 'A', 'city_name' => 'C', 'district_name' => 'D', 'subdistrict_name' => 'S', 'zip_code' => '1']],
            ], 200),
        ]);

        $service = new ShippingService;
        $service->searchDestinations('Cakung Barat');
        $service->searchDestinations('Cakung Barat'); // harus dari cache

        Http::assertSentCount(1);
    }

    public function test_kalkulasi_ongkir_dipetakan_dan_di_cache(): void
    {
        Http::fake([
            'api.test/v1/calculate/*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 35000, 'etd' => '4 day'],
                ],
            ], 200),
        ]);

        $service = new ShippingService;
        $first = $service->cost(54102, 1000, 'jne');
        $second = $service->cost(54102, 1000, 'jne'); // dari cache

        $this->assertSame(35000, $first[0]['cost']);
        $this->assertSame('REG', $first[0]['service']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['origin'] === '17673');
    }

    public function test_layanan_diurutkan_dari_tarif_termurah(): void
    {
        Http::fake([
            'api.test/v1/calculate/*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'YES', 'description' => 'Yakin Esok Sampai', 'cost' => 74000, 'etd' => '1 day'],
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 35000, 'etd' => '3 day'],
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'JTR', 'description' => 'JNE Trucking', 'cost' => 21000, 'etd' => '5 day'],
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'OKE', 'description' => 'Ongkos Kirim Ekonomis', 'cost' => 30000, 'etd' => '4 day'],
                ],
            ], 200),
        ]);

        $options = (new ShippingService)->cost(54102, 1000, 'jne');

        $this->assertSame(['JTR', 'OKE', 'REG', 'YES'], array_column($options, 'service'));
        $this->assertSame([21000, 30000, 35000, 74000], array_column($options, 'cost'));
    }

    public function test_tarif_sama_mempertahankan_urutan_asli_api(): void
    {
        Http::fake([
            'api.test/v1/calculate/*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 30000, 'etd' => '3 day'],
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'OKE', 'description' => 'Ongkos Kirim Ekonomis', 'cost' => 30000, 'etd' => '4 day'],
                ],
            ], 200),
        ]);

        // Indeks pilihan di UI dipakai selectShipping(), jadi urutan harus deterministik.
        $options = (new ShippingService)->cost(54102, 1000, 'jne');

        $this->assertSame(['REG', 'OKE'], array_column($options, 'service'));
    }

    public function test_mode_mock_juga_diurutkan_termurah_dulu(): void
    {
        config()->set('services.rajaongkir.mock', true);
        Http::fake();

        // Mock J&T sengaja tidak berurutan: EZ (1.0x) sebelum ECO (0.8x).
        $options = (new ShippingService)->cost(17673, 1000, 'jnt');

        $this->assertSame(['ECO', 'EZ'], array_column($options, 'service'));
        $this->assertLessThan($options[1]['cost'], $options[0]['cost']);
    }

    public function test_origin_belum_dikonfigurasi_melempar_exception(): void
    {
        // String kosong (bukan null) agar tetap terbaca sebagai cache hit tanpa DB.
        Cache::forever('setting.origin_district_id', '');
        Http::fake();

        $this->expectException(BusinessRuleException::class);

        (new ShippingService)->cost(54102, 1000, 'jne');
    }

    public function test_mode_mock_tidak_memanggil_api(): void
    {
        config()->set('services.rajaongkir.mock', true);
        Http::fake();

        $dest = (new ShippingService)->searchDestinations('Jakarta');
        $cost = (new ShippingService)->cost(17673, 5200, 'jne');

        $this->assertNotEmpty($dest);
        $this->assertNotEmpty($cost);
        Http::assertNothingSent();
    }
}
