<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\BrevoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBrevoEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Order $order,
        public string $event,
    ) {}

    public function handle(BrevoService $brevo): void
    {
        $payloads = match ($this->event) {
            'new_order' => [
                $brevo->buildNewOrderCustomerPayload($this->order),
                $brevo->buildNewOrderAdminPayload($this->order),
            ],
            'payment_paid' => [
                $brevo->buildPaymentPaidPayload($this->order),
            ],
            default => [],
        };

        foreach ($payloads as $payload) {
            $brevo->send($payload);
        }
    }
}
