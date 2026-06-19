<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('variants')->nullOnDelete();
            $table->unsignedInteger('price')->comment('Snapshot harga saat transaksi terjadi');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('total');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
