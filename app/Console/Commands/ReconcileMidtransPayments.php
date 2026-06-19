<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentAttemptStatus;
use App\Models\PaymentAttempt;
use App\Services\Payments\Midtrans\MidtransClient;
use App\Services\Payments\Midtrans\MidtransWebhookService;
use Illuminate\Console\Command;

class ReconcileMidtransPayments extends Command
{
    protected $signature = 'payments:midtrans:reconcile {--minutes=10 : Usia minimal attempt pending (menit)}';

    protected $description = 'Sinkronkan status payment attempt pending dengan Midtrans GET Status API.';

    public function handle(MidtransClient $client, MidtransWebhookService $webhook): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));

        $attempts = PaymentAttempt::query()
            ->where('status', PaymentAttemptStatus::PENDING->value)
            ->where('activated_at', '<=', $threshold)
            ->get();

        if ($attempts->isEmpty()) {
            $this->info('Tidak ada attempt pending untuk direkonsiliasi.');

            return self::SUCCESS;
        }

        $processed = 0;

        foreach ($attempts as $attempt) {
            $payload = $client->status($attempt->midtrans_order_id);

            if (! isset($payload['transaction_status'])) {
                $this->warn("Lewati {$attempt->midtrans_order_id}: status tidak tersedia.");

                continue;
            }

            $webhook->syncFromStatus($attempt, $payload);
            $processed++;
        }

        $this->info("Rekonsiliasi selesai: {$processed} attempt diproses.");

        return self::SUCCESS;
    }
}
