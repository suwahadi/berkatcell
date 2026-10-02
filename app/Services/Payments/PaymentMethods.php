<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;

class PaymentMethods
{
    public const MIDTRANS = 'midtrans';

    public const NICEPAY = 'nicepay';

    /**
     * Urutan menentukan urutan tampil di halaman invoice; elemen pertama jadi
     * metode terpilih default. Kunci metode Midtrans harus sama dengan
     * MidtransPaymentAttemptService::METHOD_MAP.
     */
    private const METHODS = [
        'gopay' => ['provider' => self::MIDTRANS, 'name' => 'QRIS', 'label' => 'QRIS', 'type' => 'Scan QR', 'brand' => '#00aed6'],
        'akulaku' => ['provider' => self::MIDTRANS, 'name' => 'Akulaku PayLater', 'label' => 'Akulaku', 'type' => 'Cicilan tanpa kartu', 'brand' => '#e02020'],
        'bsi_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BSI', 'label' => 'BSI', 'type' => 'Virtual Account', 'brand' => '#00a39d'],
        'bni_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BNI', 'label' => 'BNI', 'type' => 'Virtual Account', 'brand' => '#ee7203'],
        'bri_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BRI', 'label' => 'BRI', 'type' => 'Virtual Account', 'brand' => '#00529c'],
        'echannel' => ['provider' => self::MIDTRANS, 'name' => 'Mandiri Bill Payment', 'label' => 'Mandiri', 'type' => 'Bill Payment', 'brand' => '#003d79'],
        'permata_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account Permata', 'label' => 'Permata', 'type' => 'Virtual Account', 'brand' => '#00854a'],
    ];

    public static function providerFor(string $method): ?string
    {
        return self::METHODS[$method]['provider'] ?? null;
    }

    public static function name(string $method): string
    {
        return self::METHODS[$method]['name'] ?? $method;
    }

    public static function actorFor(string $provider): string
    {
        return $provider === self::NICEPAY ? 'Indodana via Nicepay (otomatis)' : 'Midtrans (otomatis)';
    }

    public static function available(Order $order, ?User $user): array
    {
        return self::METHODS;
    }
}
