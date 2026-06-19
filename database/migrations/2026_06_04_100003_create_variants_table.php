<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name')->comment('Contoh: "100mm", "200mm", "Spindle 2.2kW"');
            $table->string('sku', 100)->unique();
            $table->unsignedInteger('price');
            $table->unsignedInteger('promo_price')->nullable();
            $table->integer('stock')->default(0);
            $table->unsignedInteger('weight')->comment('Berat dalam gram untuk RajaOngkir');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
