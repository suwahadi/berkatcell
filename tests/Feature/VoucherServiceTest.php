<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VoucherType;
use App\Exceptions\BusinessRuleException;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherServiceTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VoucherService;
    }

    public function test_kode_tidak_ditemukan_dilempar(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Kode voucher tidak ditemukan.');

        $this->service->validate('TIDAKADA', 100000);
    }

    public function test_voucher_kadaluarsa_ditolak(): void
    {
        Voucher::factory()->create([
            'code' => 'EXPIRED',
            'valid_until' => now()->subDay(),
            'max_usage' => 0,
            'min_purchase' => 0,
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Voucher sudah tidak berlaku.');

        $this->service->validate('EXPIRED', 100000);
    }

    public function test_voucher_melampaui_kuota_ditolak(): void
    {
        Voucher::factory()->create([
            'code' => 'HABIS',
            'valid_until' => now()->addDay(),
            'max_usage' => 5,
            'used_count' => 5,
            'min_purchase' => 0,
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('kuota');

        $this->service->validate('HABIS', 100000);
    }

    public function test_max_usage_nol_berarti_tak_terbatas(): void
    {
        Voucher::factory()->create([
            'code' => 'UNLIMITED',
            'valid_until' => now()->addDay(),
            'max_usage' => 0,
            'used_count' => 9999,
            'min_purchase' => 0,
        ]);

        $voucher = $this->service->validate('UNLIMITED', 100000);

        $this->assertSame('UNLIMITED', $voucher->code);
    }

    public function test_subtotal_di_bawah_minimal_ditolak(): void
    {
        Voucher::factory()->create([
            'code' => 'MIN500',
            'valid_until' => now()->addDay(),
            'max_usage' => 0,
            'min_purchase' => 500000,
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Minimal belanja');

        $this->service->validate('MIN500', 499999);
    }

    public function test_diskon_persentase_dihitung_benar(): void
    {
        $voucher = Voucher::factory()->make([
            'discount_type' => VoucherType::PERCENTAGE,
            'discount_value' => 10,
        ]);

        $this->assertSame(60000, $this->service->calculateDiscount($voucher, 600000));
    }

    public function test_diskon_nominal_tetap_dihitung_benar(): void
    {
        $voucher = Voucher::factory()->make([
            'discount_type' => VoucherType::FIXED,
            'discount_value' => 50000,
        ]);

        $this->assertSame(50000, $this->service->calculateDiscount($voucher, 600000));
    }

    public function test_diskon_tidak_melebihi_subtotal(): void
    {
        $voucher = Voucher::factory()->make([
            'discount_type' => VoucherType::FIXED,
            'discount_value' => 1000000,
        ]);

        $this->assertSame(200000, $this->service->calculateDiscount($voucher, 200000));
    }
}
