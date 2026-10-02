<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentAttemptStatus;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\Nicepay\NicepayPaylaterService;
use App\Services\Payments\PaymentAttemptService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes=10 : Usia minimal attempt pending (menit)}';

    protected $description = 'Sinkronkan status payment attempt pending dengan penyedia pembayarannya.';

    public function handle(PaymentAttemptService $service, NicepayPaylaterService $nicepay): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));

        $attempts = PaymentAttempt::query()
            ->where('status', PaymentAttemptStatus::PENDING->value)
            ->where('activated_at', '<=', $threshold)
            ->get();

        foreach ($attempts as $attempt) {
            rescue(fn () => $service->syncAttempt($attempt));
        }

        $events = PaymentWebhookEvent::query()
            ->where('provider', PaymentMethods::NICEPAY)
            ->where('processing_status', 'received')
            ->whereBetween('created_at', [now()->subDays(2), $threshold])
            ->get();

        foreach ($events as $event) {
            rescue(fn () => $nicepay->reprocessEvent($event));
        }

        $this->info("Rekonsiliasi selesai: {$attempts->count()} attempt dan {$events->count()} notifikasi tertunda diperiksa.");

        return self::SUCCESS;
    }
}
