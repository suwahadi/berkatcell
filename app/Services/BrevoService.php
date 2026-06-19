<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    public function send(array $payload): bool
    {
        $baseUrl = rtrim((string) config('services.brevo.base_url'), '/');
        $apiKey = config('services.brevo.key');

        if (blank($apiKey)) {
            Log::info('Brevo API key kosong; email dilewati.', ['subject' => $payload['subject'] ?? null]);

            return false;
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
        ])->post($baseUrl.'/smtp/email', $payload);

        if ($response->failed()) {
            Log::error('Brevo gagal mengirim email', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    public function buildNewOrderCustomerPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => $order->customer_email, 'name' => $order->customer_name],
            ],
            'subject' => 'Pesanan Anda #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.new-order-customer', $order),
        ];
    }

    public function buildNewOrderAdminPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => (string) config('services.brevo.admin_email'), 'name' => 'Gudang CV. Jajar Wayang'],
            ],
            'subject' => '[Pesanan Baru] #'.$order->order_number.' — '.$order->customer_name,
            'htmlContent' => $this->render('emails.orders.new-order-admin', $order),
        ];
    }

    public function buildPaymentPaidPayload(Order $order): array
    {
        return [
            'sender' => $this->sender(),
            'to' => [
                ['email' => $order->customer_email, 'name' => $order->customer_name],
            ],
            'subject' => 'Pembayaran Diterima — Pesanan #'.$order->order_number,
            'htmlContent' => $this->render('emails.orders.payment-paid', $order),
        ];
    }

    private function render(string $view, Order $order): string
    {
        $order->loadMissing('items.product', 'items.variant', 'voucher');

        return view($view, ['order' => $order])->render();
    }

    private function sender(): array
    {
        return [
            'name' => (string) config('services.brevo.sender_name'),
            'email' => (string) config('services.brevo.sender_email'),
        ];
    }
}
