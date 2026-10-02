<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentAttemptStatus;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentAttemptService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes=10 : Usia minimal attempt pending (menit)}';

    protected $description = 'Sinkronkan status payment attempt pending dengan penyedia pembayarannya.';

    public function handle(PaymentAttemptService $service): int
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

        foreach ($attempts as $attempt) {
            rescue(fn () => $service->syncAttempt($attempt));
        }

        $this->info("Rekonsiliasi selesai: {$attempts->count()} attempt diperiksa.");

        return self::SUCCESS;
    }
}
