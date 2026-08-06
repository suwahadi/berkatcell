<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    public const COURIERS = [
        'jne' => 'JNE',
        'jnt' => 'J&T Express',
        'sicepat' => 'SiCepat',
        'anteraja' => 'AnterAja',
        'ninja' => 'Ninja Xpress',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
        'lion' => 'Lion Parcel',
        'ide' => 'ID Express',
        'sap' => 'SAP Express',
        'ncs' => 'NCS',
        'rex' => 'Royal Express (REX)',
        'rpx' => 'RPX',
        'sentral' => 'Sentral Cargo',
        'star' => 'Star Cargo',
        'wahana' => 'Wahana',
        'dse' => '21 Express',
    ];

    public const ENABLED_COURIERS = [
        'jne', 'jnt', 'sicepat',
    ];

    private const DESTINATION_TTL = 86400;

    private const COST_TTL = 43200;

    private string $baseUrl;

    private ?string $apiKey;

    private ?string $origin;

    private bool $mock;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.rajaongkir.base_url'), '/');
        $this->apiKey = config('services.rajaongkir.key');
        $origin = setting('origin_district_id');
        $this->origin = blank($origin) ? null : (string) $origin;
        $this->mock = (bool) config('services.rajaongkir.mock') || blank($this->apiKey);
    }

    public function searchDestinations(string $keyword, int $limit = 10): array
    {
        $keyword = trim($keyword);

        if (mb_strlen($keyword) < 3) {
            return [];
        }

        if ($this->mock) {
            return $this->mockDestinations($keyword);
        }

        $cacheKey = 'shipping:dest:'.md5(mb_strtolower($keyword).'|'.$limit);

        return Cache::remember($cacheKey, self::DESTINATION_TTL, function () use ($keyword, $limit): array {
            $response = Http::withHeaders(['key' => $this->apiKey])
                ->get($this->baseUrl.'/destination/domestic-destination', [
                    'search' => $keyword,
                    'limit' => $limit,
                    'offset' => 0,
                ]);

            if ($response->status() === 404) {
                return [];
            }

            if ($response->failed()) {
                Log::warning('RajaOngkir destinasi gagal', ['status' => $response->status(), 'body' => $response->body()]);

                throw new BusinessRuleException('Gagal memuat data wilayah pengiriman.');
            }

            return array_map(static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'city' => (string) ($row['city_name'] ?? ''),
                'district' => (string) ($row['district_name'] ?? ''),
                'subdistrict' => (string) ($row['subdistrict_name'] ?? ''),
                'zip' => (string) ($row['zip_code'] ?? ''),
            ], $response->json('data') ?? []);
        });
    }

    public function cost(int $destinationId, int $weight, string $courier): array
    {
        if ($this->mock) {
            return $this->cheapestFirst($this->mockCost($weight, $courier));
        }

        if (blank($this->origin)) {
            throw new BusinessRuleException('Titik asal pengiriman belum dikonfigurasi.');
        }

        $cacheKey = "shipping:cost:{$this->origin}:{$destinationId}:".max(1, $weight).":{$courier}";

        // Urutkan di luar Cache::remember agar entri cache lama ikut terurut
        // tanpa menunggu TTL habis.
        return $this->cheapestFirst(Cache::remember($cacheKey, self::COST_TTL, function () use ($destinationId, $weight, $courier): array {
            $response = Http::withHeaders(['key' => $this->apiKey])
                ->asForm()
                ->post($this->baseUrl.'/calculate/domestic-cost', [
                    'origin' => $this->origin,
                    'destination' => $destinationId,
                    'weight' => max(1, $weight),
                    'courier' => $courier,
                ]);

            if ($response->status() === 404) {
                return [];
            }

            if ($response->failed()) {
                Log::warning('RajaOngkir cost gagal', ['status' => $response->status(), 'body' => $response->body()]);

                throw new BusinessRuleException('Gagal menghitung ongkos kirim. Silakan coba lagi.');
            }

            return array_map(static fn (array $c): array => [
                'name' => (string) ($c['name'] ?? ''),
                'code' => (string) ($c['code'] ?? ''),
                'service' => (string) ($c['service'] ?? ''),
                'description' => (string) ($c['description'] ?? ''),
                'cost' => (int) ($c['cost'] ?? 0),
                'etd' => (string) ($c['etd'] ?? ''),
            ], $response->json('data') ?? []);
        }));
    }

    /**
     * Termurah lebih dulu. Sort PHP 8 stabil, jadi layanan dengan tarif sama
     * tetap memakai urutan asli dari API - indeks pilihan di UI deterministik.
     *
     * @param  list<array{name: string, code: string, service: string, description: string, cost: int, etd: string}>  $options
     * @return list<array{name: string, code: string, service: string, description: string, cost: int, etd: string}>
     */
    private function cheapestFirst(array $options): array
    {
        usort($options, static fn (array $a, array $b): int => $a['cost'] <=> $b['cost']);

        return $options;
    }

    /**
     * Tiruan respons /calculate/domestic-cost: tiap kurir punya beberapa sub-layanan.
     *
     * @return list<array{name: string, code: string, service: string, description: string, cost: int, etd: string}>
     */
    private function mockCost(int $weight, string $courier): array
    {
        $base = 9000 + (int) ceil(max(1, $weight) / 1000) * 5000;
        $name = self::COURIERS[$courier] ?? strtoupper($courier);

        $services = match ($courier) {
            'jne' => [
                ['service' => 'OKE', 'description' => 'Ongkos Kirim Ekonomis', 'mult' => 0.85, 'etd' => '3-4 hari'],
                ['service' => 'REG', 'description' => 'Layanan Reguler', 'mult' => 1.0, 'etd' => '2-3 hari'],
                ['service' => 'YES', 'description' => 'Yakin Esok Sampai', 'mult' => 2.1, 'etd' => '1 hari'],
            ],
            'jnt' => [
                ['service' => 'EZ', 'description' => 'Reguler', 'mult' => 1.0, 'etd' => '2-3 hari'],
                ['service' => 'ECO', 'description' => 'Hemat', 'mult' => 0.8, 'etd' => '3-5 hari'],
            ],
            'sicepat' => [
                ['service' => 'REG', 'description' => 'Layanan Reguler', 'mult' => 1.0, 'etd' => '2-3 hari'],
                ['service' => 'BEST', 'description' => 'Besok Sampai Tujuan', 'mult' => 1.9, 'etd' => '1 hari'],
            ],
            default => [
                ['service' => 'REG', 'description' => 'Layanan Reguler', 'mult' => 1.0, 'etd' => '2-3 hari'],
            ],
        };

        return array_map(static fn (array $s): array => [
            'name' => $name,
            'code' => $courier,
            'service' => (string) $s['service'],
            'description' => (string) $s['description'],
            'cost' => (int) (round($base * $s['mult'] / 500) * 500),
            'etd' => (string) $s['etd'],
        ], $services);
    }

    private function mockDestinations(string $keyword): array
    {
        return [
            ['id' => 17673, 'label' => 'CAKUNG BARAT, CAKUNG, JAKARTA TIMUR, DKI JAKARTA, 13910', 'city' => 'JAKARTA TIMUR', 'district' => 'CAKUNG', 'subdistrict' => 'CAKUNG BARAT', 'zip' => '13910'],
            ['id' => 12526, 'label' => 'GAMBIR, GAMBIR, JAKARTA PUSAT, DKI JAKARTA, 10110', 'city' => 'JAKARTA PUSAT', 'district' => 'GAMBIR', 'subdistrict' => 'GAMBIR', 'zip' => '10110'],
            ['id' => 13234, 'label' => 'KEBAYORAN BARU, KEBAYORAN BARU, JAKARTA SELATAN, DKI JAKARTA, 12110', 'city' => 'JAKARTA SELATAN', 'district' => 'KEBAYORAN BARU', 'subdistrict' => 'KEBAYORAN BARU', 'zip' => '12110'],
        ];
    }
}
