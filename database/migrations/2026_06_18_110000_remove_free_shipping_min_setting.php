<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hapus pengaturan global gratis ongkir; fitur ini kini ditangani lewat voucher.
     */
    public function up(): void
    {
        DB::table('settings')->where('key', 'free_shipping_min')->delete();
        Cache::forget('setting.free_shipping_min');
    }

    public function down(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'free_shipping_min'],
            ['value' => '0'],
        );
    }
};
