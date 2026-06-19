<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('discount_type', 50)->comment('Enum VoucherType: persentase | nominal_tetap');
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('min_purchase')->default(0)->comment('0 = tanpa minimum');
            $table->unsignedInteger('max_usage')->default(0)->comment('0 = kuota tak terbatas');
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('valid_until');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
