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
            $table->string('provider')->default('midtrans')->after('order_id')->index();
        });

        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->string('provider')->default('midtrans')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->dropColumn('provider');
        });

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropIndex(['provider']);
            $table->dropColumn('provider');
        });
    }
};
