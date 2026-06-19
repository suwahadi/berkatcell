<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('attempt_sequence');
            $table->string('midtrans_order_id')->unique();
            $table->string('payment_method')->nullable()->comment('bni_va, bri_va, qris, gopay, dll');
            $table->string('status')->default('creating')->comment('Enum PaymentAttemptStatus');
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->string('snap_token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->string('midtrans_transaction_id')->nullable()->index();
            $table->string('midtrans_transaction_status')->nullable();
            $table->string('midtrans_fraud_status')->nullable();
            $table->json('snap_request_payload')->nullable();
            $table->json('snap_response_payload')->nullable();
            $table->json('latest_notification_payload')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'attempt_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
