<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_guard')
                ->nullable()
                ->virtualAs("CASE WHEN `status` IN ('creating', 'pending') THEN `order_id` END");

            $table->unique('active_guard', 'payment_attempts_active_guard_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropUnique('payment_attempts_active_guard_unique');
            $table->dropColumn('active_guard');
        });
    }
};
