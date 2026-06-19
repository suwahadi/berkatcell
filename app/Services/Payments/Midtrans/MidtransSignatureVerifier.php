<?php

declare(strict_types=1);

namespace App\Services\Payments\Midtrans;

class MidtransSignatureVerifier
{
    public function isValid(array $payload): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        $serverKey = (string) config('services.midtrans.server_key');

        $expected = hash('sha512',
            $payload['order_id'].
            $payload['status_code'].
            $payload['gross_amount'].
            $serverKey
        );

        return hash_equals($expected, (string) $payload['signature_key']);
    }
}
