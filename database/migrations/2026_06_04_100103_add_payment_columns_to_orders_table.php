<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('active_payment_attempt_id')
                ->nullable()
                ->after('status')
                ->constrained('payment_attempts')
                ->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('active_payment_attempt_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_payment_attempt_id');
            $table->dropColumn('paid_at');
        });
    }
};
