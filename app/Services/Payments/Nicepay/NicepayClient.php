<?php

declare(strict_types=1);

namespace App\Services\Payments\Nicepay;

use App\Exceptions\BusinessRuleException;
use App\Models\PaymentAttempt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NicepayClient
{
    public const PAY_METHOD_PAYLATER = '06';

    public function baseUrl(): string
    {
        return rtrim((string) (config('services.nicepay.is_production')
            ? config('services.nicepay.production_url')
            : config('services.nicepay.development_url')), '/');
    }

    public function merchantId(): string
    {
        return (string) config('services.nicepay.imid');
    }

    public function timestamp(): string
    {
        return now('Asia/Jakarta')->format('YmdHis');
    }

    public function token(string ...$parts): string
    {
        return hash('sha256', implode('', $parts).config('services.nicepay.merchant_key'));
    }

    public function transactionToken(string $timeStamp, string $referenceNo, int $amount): string
    {
        return $this->token($timeStamp, $this->merchantId(), $referenceNo, (string) $amount);
    }

    public function notificationTokenIsValid(array $payload): bool
    {
        if (blank(config('services.nicepay.merchant_key'))) {
            return false;
        }

        foreach (['tXid', 'amt', 'merchantToken'] as $key) {
            if (! is_scalar($payload[$key] ?? null) || blank($payload[$key])) {
                return false;
            }
        }

        $expected = $this->token($this->merchantId(), (string) $payload['tXid'], (string) $payload['amt']);

        return hash_equals($expected, (string) $payload['merchantToken']);
    }

    public function register(array $payload): array
    {
        $response = $this->post('/nicepay/direct/v2/registration', $payload);

        if (($response['resultCd'] ?? null) !== '0000' || blank($response['tXid'] ?? null)) {
            Log::error('Nicepay registration gagal', [
                'referenceNo' => $payload['referenceNo'] ?? null,
                'resultCd' => $response['resultCd'] ?? null,
                'resultMsg' => $response['resultMsg'] ?? null,
            ]);

            throw new BusinessRuleException('Gagal memulai pembayaran. Silakan coba lagi.');
        }

        return $response;
    }

    public function inquiry(PaymentAttempt $attempt): array
    {
        $timeStamp = $this->timestamp();
        $amount = (int) $attempt->gross_amount;

        return $this->post('/nicepay/direct/v2/inquiry', [
            'timeStamp' => $timeStamp,
            'tXid' => (string) $attempt->midtrans_transaction_id,
            'iMid' => $this->merchantId(),
            'referenceNo' => $attempt->midtrans_order_id,
            'amt' => (string) $amount,
            'merchantToken' => $this->transactionToken($timeStamp, $attempt->midtrans_order_id, $amount),
        ]);
    }

    public function cancel(PaymentAttempt $attempt): array
    {
        if (blank($attempt->midtrans_transaction_id)) {
            return [];
        }

        $timeStamp = $this->timestamp();
        $tXid = (string) $attempt->midtrans_transaction_id;
        $amount = (string) (int) $attempt->gross_amount;

        $response = $this->post('/nicepay/direct/v2/cancel', [
            'timeStamp' => $timeStamp,
            'tXid' => $tXid,
            'iMid' => $this->merchantId(),
            'payMethod' => self::PAY_METHOD_PAYLATER,
            'cancelType' => '1',
            'cancelMsg' => 'Metode pembayaran diganti',
            'amt' => $amount,
            'merchantToken' => $this->token($timeStamp, $this->merchantId(), $tXid, $amount),
        ]);

        if (($response['resultCd'] ?? null) !== '0000') {
            Log::warning('Nicepay cancel tidak berhasil', [
                'referenceNo' => $attempt->midtrans_order_id,
                'resultCd' => $response['resultCd'] ?? null,
                'resultMsg' => $response['resultMsg'] ?? null,
            ]);
        }

        return $response;
    }

    public function paymentUrl(): string
    {
        return $this->baseUrl().'/nicepay/direct/v2/payment';
    }

    public function paymentFormFields(PaymentAttempt $attempt): array
    {
        $timeStamp = $this->timestamp();

        return [
            'timeStamp' => $timeStamp,
            'tXid' => (string) $attempt->midtrans_transaction_id,
            'merchantToken' => $this->transactionToken($timeStamp, $attempt->midtrans_order_id, (int) $attempt->gross_amount),
            'callBackUrl' => route('payments.nicepay.callback'),
        ];
    }

    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::timeout(15)->acceptJson()->asJson()->post($this->baseUrl().$path, $payload);
        } catch (ConnectionException $e) {
            Log::warning('Nicepay tidak dapat dihubungi', ['path' => $path, 'error' => $e->getMessage()]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('Nicepay membalas error HTTP', ['path' => $path, 'status' => $response->status()]);

            return [];
        }

        return $response->json() ?? [];
    }
}
