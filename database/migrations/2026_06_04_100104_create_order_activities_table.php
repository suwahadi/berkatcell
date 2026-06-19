<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('description');
            $table->string('actor')->nullable()->comment('Pelanggan / nama staff / Midtrans (otomatis) / Sistem');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['order_id', 'created_at']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_activities');
    }

    private function backfill(): void
    {
        DB::table('orders')->orderBy('id')->each(function (object $order): void {
            $rows = [[
                'order_id' => $order->id,
                'type' => 'dibuat',
                'description' => 'Pesanan dibuat oleh '.$order->customer_name.'.',
                'actor' => $order->customer_name,
                'meta' => null,
                'created_at' => $order->created_at,
            ]];

            $paidAt = $order->paid_at ?? $order->updated_at;

            if (! empty($order->paid_at) || in_array($order->status, ['lunas', 'dikirim'], true)) {
                $rows[] = [
                    'order_id' => $order->id,
                    'type' => 'lunas',
                    'description' => 'Pembayaran diterima.',
                    'actor' => 'Sistem',
                    'meta' => null,
                    'created_at' => $paidAt,
                ];
            }

            if ($order->status === 'dikirim') {
                $rows[] = [
                    'order_id' => $order->id,
                    'type' => 'dikirim',
                    'description' => 'Pesanan dikirim ke pelanggan.',
                    'actor' => 'Sistem',
                    'meta' => null,
                    'created_at' => $order->updated_at,
                ];
            }

            if ($order->status === 'dibatalkan') {
                $rows[] = [
                    'order_id' => $order->id,
                    'type' => 'dibatalkan',
                    'description' => 'Pesanan dibatalkan.',
                    'actor' => 'Sistem',
                    'meta' => null,
                    'created_at' => $order->updated_at,
                ];
            }

            DB::table('order_activities')->insert($rows);
        });
    }
};
