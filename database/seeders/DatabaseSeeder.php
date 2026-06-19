<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@berkatcell.com',
        ]);

        $this->call([
            SettingSeeder::class,
            CatalogSeeder::class,
            VoucherSeeder::class,
            PageSeeder::class,
        ]);
    }
}
