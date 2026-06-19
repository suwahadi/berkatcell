<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'site_name' => 'Berkat Cell',
            'site_tagline' => 'Jual Beli Handphone & Gadget Terpercaya',
            'site_email' => 'berkatcell111777@gmail.com',
            'site_phone' => '0877-7600-6060',
            'site_whatsapp' => '0877-7600-6060',
            'site_address' => 'Jl. Rw. Bebek II No.08 18, RT.4/RW.11, Penjaringan, Kec. Penjaringan, Jakarta Utara, DKI Jakarta',
            'origin_district_id' => '73642',
            'expiry_order' => '15',

            'site_announcement' => 'Gratis ongkir untuk pembelian di atas Rp 5.000.000 ke seluruh Indonesia',

            'site_facebook' => '#',
            'site_instagram' => '#',
            'site_tiktok' => '#',
            'site_youtube' => '#',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
