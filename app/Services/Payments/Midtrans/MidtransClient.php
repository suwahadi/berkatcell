<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransClient
{
    public function snapUrl(): string
    {
        return config('services.midtrans.is_production')
            ? (string) config('services.midtrans.snap_production_url')
            : (string) config('services.midtrans.snap_sandbox_url');
    }

    public function apiBaseUrl(): string
    {
        return config('services.midtrans.is_production')
            ? (string) config('services.midtrans.api_production_url')
            : (string) config('services.midtrans.api_sandbox_url');
    }

    public function authHeader(): string
    {
        return 'Basic '.base64_encode(config('services.midtrans.server_key').':');
    }

    public function createSnapTransaction(array $payload): array
    {
        if (blank(config('services.midtrans.server_key'))) {
            throw new BusinessRuleException('Konfigurasi pembayaran belum lengkap. Hubungi admin.');
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->post($this->snapUrl(), $payload);

        if ($response->failed()) {
            Log::error('Midtrans Snap gagal membuat transaksi', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new BusinessRuleException('Gagal memulai pembayaran. Silakan coba lagi.');
        }

        return $response->json() ?? [];
    }

    public function cancel(string $midtransOrderId): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->post($this->apiBaseUrl().'/v2/'.urlencode($midtransOrderId).'/cancel');

        return $response->json() ?? [
            'http_status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    public function status(string $midtransOrderId): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->get($this->apiBaseUrl().'/v2/'.urlencode($midtransOrderId).'/status');

        return $response->json() ?? [
            'http_status' => $response->status(),
            'body' => $response->body(),
        ];
    }
}
