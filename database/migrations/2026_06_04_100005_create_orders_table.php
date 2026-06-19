<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 100)->unique();
            $table->string('idempotency_key')->unique();

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 50);

            $table->integer('shipping_province_id')->nullable();
            $table->integer('shipping_city_id')->nullable();
            $table->integer('shipping_district_id');
            $table->string('shipping_destination_label')->nullable();
            $table->text('shipping_address');
            $table->string('shipping_courier', 100)->comment('jne | pos | tiki');
            $table->unsignedInteger('shipping_cost');

            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('grand_total');

            $table->string('status', 50)->comment('Enum OrderStatus');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
