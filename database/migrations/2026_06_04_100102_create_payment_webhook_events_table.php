<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('midtrans_order_id')->index();
            $table->string('transaction_id')->nullable()->index();
            $table->string('transaction_status')->nullable();
            $table->string('status_code')->nullable();
            $table->string('gross_amount')->nullable();
            $table->string('signature_key')->nullable();
            $table->string('event_hash')->unique();
            $table->string('processing_status')->default('received')
                ->comment('received|processed|ignored|invalid_signature|failed');
            $table->json('payload');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
