<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('shipping_service', 100)->nullable()->after('shipping_courier')
                ->comment('Kode layanan kurir, mis. REG / EZ / YES');
            $table->string('shipping_service_label')->nullable()->after('shipping_service')
                ->comment('Deskripsi layanan, mis. Layanan Reguler');
            $table->string('shipping_etd', 50)->nullable()->after('shipping_service_label')
                ->comment('Estimasi waktu pengiriman');
        });

        DB::table('orders')->whereNull('uuid')->orderBy('id')->each(function (object $order): void {
            DB::table('orders')->where('id', $order->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'shipping_service', 'shipping_service_label', 'shipping_etd']);
        });
    }
};
