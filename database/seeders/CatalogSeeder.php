<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $categoryName => $products) {
            $category = Category::query()->create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'is_active' => true,
            ]);

            foreach ($products as $p) {
                $name = $this->buildName($p['model'], $p['color'] ?? null, $p['storage']);

                Product::query()->create([
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'sku' => $p['sku'],
                    'original_price' => $p['original'],
                    'promo_price' => $p['promo'],
                    'description' => $this->buildDescription($p['model'], $p['specs'], $p['color'] ?? null, $p['storage']),
                    'weight' => $p['weight'],
                    'stock' => 10,
                    'is_active' => true,
                    'badge' => $p['badge'],
                ]);
            }
        }
    }

    private function buildName(string $model, ?string $color, string $storage): string
    {
        $spec = $color !== null && $color !== ''
            ? $color.', '.$storage
            : $storage;

        return $model.' ('.$spec.')';
    }

    private function buildDescription(string $model, string $specs, ?string $color, string $storage): string
    {
        $html = '<p>'.e($model).' — unit second (bekas) berkualitas, kondisi sesuai foto.</p>';

        $html .= '<ul>';
        foreach (explode(',', $specs) as $point) {
            $point = trim($point);
            if ($point !== '') {
                $html .= '<li>'.e($point).'</li>';
            }
        }
        if ($color !== null && $color !== '') {
            $html .= '<li>Warna: '.e($color).'</li>';
        }
        $html .= '<li>RAM &amp; Penyimpanan: '.e($storage).'</li>';
        $html .= '</ul>';

        return $html;
    }

    private function catalog(): array
    {
        return [
            'iPhone' => [
                [
                    'model' => 'iPhone 11',
                    'sku' => 'APP-IP11-01',
                    'original' => 3500000,
                    'promo' => null,
                    'weight' => 194,
                    'badge' => 'hot',
                    'color' => 'Gold',
                    'storage' => '64GB',
                    'specs' => 'Layar Liquid Retina IPS, Apple A13 Bionic, Dual Kamera 12MP',
                ],
                [
                    'model' => 'iPhone 11',
                    'sku' => 'APP-IP11-02',
                    'original' => 3500000,
                    'promo' => null,
                    'weight' => 194,
                    'badge' => 'hot',
                    'color' => 'White',
                    'storage' => '64GB',
                    'specs' => 'Layar Liquid Retina IPS, Apple A13 Bionic, Dual Kamera 12MP',
                ],
                [
                    'model' => 'iPhone 13',
                    'sku' => 'APP-IP13-01',
                    'original' => 6000000,
                    'promo' => null,
                    'weight' => 174,
                    'badge' => 'hot',
                    'color' => 'Starlight',
                    'storage' => '128GB',
                    'specs' => 'Layar Super Retina XDR OLED, Apple A15 Bionic, Kamera 12MP',
                ],
                [
                    'model' => 'iPhone 15',
                    'sku' => 'APP-IP15-01',
                    'original' => 7000000,
                    'promo' => null,
                    'weight' => 171,
                    'badge' => 'hot',
                    'color' => 'Black',
                    'storage' => '128GB',
                    'specs' => 'Layar Super Retina XDR OLED, Dynamic Island, Apple A16 Bionic',
                ],
            ],

            'Samsung' => [
                [
                    'model' => 'Samsung Galaxy A15',
                    'sku' => 'SAM-A15-01',
                    'original' => 2499000,
                    'promo' => 1600000,
                    'weight' => 200,
                    'badge' => 'hot',
                    'color' => 'Biru',
                    'storage' => '8GB/256GB',
                    'specs' => 'Layar 90Hz Super AMOLED, Kamera 50MP, Helio G99, Baterai 5000mAh',
                ],
                [
                    'model' => 'Samsung Galaxy A56 5G',
                    'sku' => 'SAM-A56-01',
                    'original' => 5000000,
                    'promo' => null,
                    'weight' => 200,
                    'badge' => 'hot',
                    'color' => 'Hijau',
                    'storage' => '12GB/256GB',
                    'specs' => 'Jaringan 5G, Performa premium kelas menengah atas',
                ],
                [
                    'model' => 'Samsung Galaxy A07',
                    'sku' => 'SAM-A07-01',
                    'original' => 1999000,
                    'promo' => 1400000,
                    'weight' => 184,
                    'badge' => 'new',
                    'color' => 'Hijau Tua',
                    'storage' => '4GB/128GB',
                    'specs' => 'Rilis Februari 2026, Layar 90Hz PLS LCD, Kamera 50MP, Dimensity 6300',
                ],
                [
                    'model' => 'Samsung Galaxy A26 5G',
                    'sku' => 'SAM-A26-01',
                    'original' => 3599000,
                    'promo' => 1000000,
                    'weight' => 200,
                    'badge' => null,
                    'color' => null,
                    'storage' => '16GB/256GB',
                    'specs' => 'Layar 120Hz Super AMOLED, Kamera 50MP OIS, Exynos 1380',
                ],
                [
                    'model' => 'Samsung Galaxy A06',
                    'sku' => 'SAM-A06-01',
                    'original' => 1499000,
                    'promo' => 1000000,
                    'weight' => 189,
                    'badge' => null,
                    'color' => 'Hitam',
                    'storage' => '4GB/128GB',
                    'specs' => 'Layar 60Hz PLS LCD, Kamera 50MP, Helio G85, Baterai 5000mAh',
                ],
                [
                    'model' => 'Samsung Galaxy A05s',
                    'sku' => 'SAM-A05S-01',
                    'original' => 1300000,
                    'promo' => null,
                    'weight' => 194,
                    'badge' => null,
                    'color' => null,
                    'storage' => '6GB/128GB',
                    'specs' => 'Layar 90Hz PLS LCD, Kamera 50MP, Snapdragon 680 4G',
                ],
            ],

            'Infinix' => [
                [
                    'model' => 'Infinix HOT 60 Pro',
                    'sku' => 'INF-H60P-01',
                    'original' => 2500000,
                    'promo' => null,
                    'weight' => 170,
                    'badge' => 'hot',
                    'color' => 'Silver',
                    'storage' => '16GB/256GB',
                    'specs' => 'Layar 144Hz AMOLED, Kamera 50MP, Helio G200, Baterai 5160mAh',
                ],
                [
                    'model' => 'Infinix HOT 60i',
                    'sku' => 'INF-H60I-01',
                    'original' => 2000000,
                    'promo' => null,
                    'weight' => 188,
                    'badge' => 'hot',
                    'color' => 'Gold',
                    'storage' => '16GB/256GB',
                    'specs' => 'Layar 120Hz IPS, Kamera 50MP, Helio G81 Ultimate, Baterai 5160mAh',
                ],
                [
                    'model' => 'Infinix Note 50 Pro',
                    'sku' => 'INF-N50P-01',
                    'original' => 3299000,
                    'promo' => 2500000,
                    'weight' => 198,
                    'badge' => 'hot',
                    'color' => 'Hitam',
                    'storage' => '16GB/256GB',
                    'specs' => 'Layar 144Hz AMOLED, Kamera 50MP OIS, Helio G100, Baterai 5200mAh',
                ],
                [
                    'model' => 'Infinix Smart 20',
                    'sku' => 'INF-S20-01',
                    'original' => 1500000,
                    'promo' => null,
                    'weight' => 190,
                    'badge' => 'new',
                    'color' => 'Biru',
                    'storage' => '4GB/64GB',
                    'specs' => 'Rilis awal 2026, Layar 120Hz IPS, Kamera 8MP, Helio G81 Ultimate',
                ],
                [
                    'model' => 'Infinix HOT 60 Pro+',
                    'sku' => 'INF-H60PP-01',
                    'original' => 2700000,
                    'promo' => null,
                    'weight' => 155,
                    'badge' => 'hot',
                    'color' => 'Silver',
                    'storage' => '8GB/256GB',
                    'specs' => 'Layar 144Hz 3D-Curved AMOLED, Kamera 50MP, Helio G200',
                ],
                [
                    'model' => 'Infinix GT 30',
                    'sku' => 'INF-GT30-01',
                    'original' => 3499000,
                    'promo' => 2000000,
                    'weight' => 187,
                    'badge' => 'hot',
                    'color' => null,
                    'storage' => '8GB/256GB',
                    'specs' => 'Gaming Edition, 144Hz AMOLED, Dimensity 7400, Kamera 64MP',
                ],
            ],

            'Oppo' => [
                [
                    'model' => 'Oppo Reno15 F 5G',
                    'sku' => 'OPP-R15F-01',
                    'original' => 4999000,
                    'promo' => 4000000,
                    'weight' => 178,
                    'badge' => 'new',
                    'color' => 'Putih',
                    'storage' => '8GB/256GB',
                    'specs' => 'Rilis Januari 2026, Layar 120Hz AMOLED, Snapdragon 6 Gen 1, IP69',
                ],
                [
                    'model' => 'Oppo A78',
                    'sku' => 'OPP-A78-01',
                    'original' => 3599000,
                    'promo' => 1500000,
                    'weight' => 180,
                    'badge' => 'hot',
                    'color' => 'Putih',
                    'storage' => '8GB/256GB',
                    'specs' => 'Layar 90Hz AMOLED, Kamera 50MP, Snapdragon 680 4G, Baterai 5000mAh',
                ],
                [
                    'model' => 'Oppo A5 Pro',
                    'sku' => 'OPP-A5P-01',
                    'original' => 2799000,
                    'promo' => 2500000,
                    'weight' => 194,
                    'badge' => 'new',
                    'color' => 'Gold',
                    'storage' => '8GB/256GB',
                    'specs' => 'Rilis akhir 2025, Layar 120Hz IPS, Dimensity 6300, Tahan Banting',
                ],
                [
                    'model' => 'Oppo A18',
                    'sku' => 'OPP-A18-01',
                    'original' => 1799000,
                    'promo' => 1200000,
                    'weight' => 188,
                    'badge' => null,
                    'color' => 'Putih',
                    'storage' => '4GB/128GB',
                    'specs' => 'Layar 90Hz IPS, Kamera 8MP, Helio G85, Baterai 5000mAh',
                ],
                [
                    'model' => 'Oppo A60',
                    'sku' => 'OPP-A60-01',
                    'original' => 2599000,
                    'promo' => 2000000,
                    'weight' => 186,
                    'badge' => 'hot',
                    'color' => 'Biru',
                    'storage' => '8GB/256GB',
                    'specs' => 'Layar 90Hz IPS, Kamera 50MP, Snapdragon 680 4G, Tahan Benturan',
                ],
            ],

            'Vivo' => [
                [
                    'model' => 'Vivo Y21d',
                    'sku' => 'VIV-Y21D-01',
                    'original' => 2400000,
                    'promo' => null,
                    'weight' => 209,
                    'badge' => 'new',
                    'color' => 'Ungu',
                    'storage' => '8GB/256GB',
                    'specs' => 'Rilis Oktober 2025, Layar 90Hz, IP69 Tahan Air, Baterai Ekstra 6500mAh',
                ],
                [
                    'model' => 'Vivo V60 Lite',
                    'sku' => 'VIV-V60L-01',
                    'original' => 4000000,
                    'promo' => null,
                    'weight' => 194,
                    'badge' => 'hot',
                    'color' => 'Hitam',
                    'storage' => '8GB/256GB',
                    'specs' => 'Layar 120Hz AMOLED, Dimensity 7360-Turbo, Kamera 50MP OIS, 6500mAh',
                ],
                [
                    'model' => 'Vivo Y19s',
                    'sku' => 'VIV-Y19S-01',
                    'original' => 1999000,
                    'promo' => 1200000,
                    'weight' => 198,
                    'badge' => null,
                    'color' => 'Putih',
                    'storage' => '6GB/128GB',
                    'specs' => 'Layar 90Hz IPS, Kamera 50MP + Ring Light, Unisoc T612, 6000mAh',
                ],
                [
                    'model' => 'Vivo Y29',
                    'sku' => 'VIV-Y29-01',
                    'original' => 2599000,
                    'promo' => 2200000,
                    'weight' => 196,
                    'badge' => 'hot',
                    'color' => 'Putih',
                    'storage' => '8GB/128GB',
                    'specs' => 'Layar 120Hz IPS, Kamera 50MP, Snapdragon 685, Baterai 6500mAh',
                ],
                [
                    'model' => 'Vivo Y28',
                    'sku' => 'VIV-Y28-01',
                    'original' => 2399000,
                    'promo' => 1400000,
                    'weight' => 199,
                    'badge' => 'hot',
                    'color' => 'Pink Keunguan',
                    'storage' => '8GB/128GB',
                    'specs' => 'Layar 90Hz IPS, Kamera 50MP + Star Halo, Helio G85, Baterai 6000mAh',
                ],
            ],
        ];
    }
}
