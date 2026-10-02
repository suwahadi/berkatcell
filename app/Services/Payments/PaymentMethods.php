<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;

class PaymentMethods
{
    public const MIDTRANS = 'midtrans';

    public const NICEPAY = 'nicepay';

    public const INDODANA = 'indodana';

    private const INDODANA_MIN_AMOUNT = 10_000;

    private const INDODANA_MAX_AMOUNT = 50_000_000;

    private const INDODANA_MAX_EMAIL_LENGTH = 40;

    /**
     * Urutan menentukan urutan tampil di halaman invoice; elemen pertama jadi
     * metode terpilih default. Kunci metode Midtrans harus sama dengan
     * MidtransPaymentAttemptService::METHOD_MAP.
     */
    private const METHODS = [
        'gopay' => ['provider' => self::MIDTRANS, 'name' => 'QRIS', 'label' => 'QRIS', 'type' => 'Scan QR', 'logo' => 'qris.svg'],
        'akulaku' => ['provider' => self::MIDTRANS, 'name' => 'Akulaku PayLater', 'label' => 'Akulaku', 'type' => 'Cicilan tanpa kartu', 'logo' => 'akulaku.png'],
        'bsi_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BSI', 'label' => 'BSI', 'type' => 'Virtual Account', 'logo' => 'bsi.svg'],
        'bni_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BNI', 'label' => 'BNI', 'type' => 'Virtual Account', 'logo' => 'bni.svg'],
        'bri_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account BRI', 'label' => 'BRI', 'type' => 'Virtual Account', 'logo' => 'bri.svg'],
        'echannel' => ['provider' => self::MIDTRANS, 'name' => 'Mandiri Bill Payment', 'label' => 'Mandiri', 'type' => 'Bill Payment', 'logo' => 'mandiri.svg'],
        'permata_va' => ['provider' => self::MIDTRANS, 'name' => 'Virtual Account Permata', 'label' => 'Permata', 'type' => 'Virtual Account', 'logo' => 'permata.svg'],
        self::INDODANA => ['provider' => self::NICEPAY, 'name' => 'Indodana PayLater', 'label' => 'Indodana', 'type' => 'Cicilan tanpa kartu', 'logo' => 'indodana.svg'],
    ];

    public static function providerFor(string $method): ?string
    {
        return self::METHODS[$method]['provider'] ?? null;
    }

    public static function name(string $method): string
    {
        return self::METHODS[$method]['name'] ?? $method;
    }

    public static function logoUrl(string $method): ?string
    {
        $logo = self::METHODS[$method]['logo'] ?? null;

        return $logo === null ? null : asset('images/payments/'.$logo);
    }

    public static function actorFor(string $provider): string
    {
        return $provider === self::NICEPAY ? 'Indodana via Nicepay (otomatis)' : 'Midtrans (otomatis)';
    }

    public static function available(Order $order, ?User $user): array
    {
        return array_filter(
            self::METHODS,
            fn (array $method): bool => $method['provider'] === self::MIDTRANS || self::indodanaAvailable($order, $user),
        );
    }

    public static function nicepayConfigured(): bool
    {
        return (bool) config('services.nicepay.enabled')
            && filled(config('services.nicepay.imid'))
            && filled(config('services.nicepay.merchant_key'));
    }

    /**
     * Syarat yang melekat pada pesanan, tanpa melihat siapa yang membuka halamannya.
     * Dipakai PaymentAttemptService sebelum membuat tagihan baru.
     */
    public static function eligible(Order $order, string $method): bool
    {
        return match (self::providerFor($method)) {
            self::NICEPAY => self::indodanaEligible($order),
            self::MIDTRANS => true,
            default => false,
        };
    }

    private static function indodanaAvailable(Order $order, ?User $user): bool
    {
        if (config('services.nicepay.admin_only') && ! $user?->isAdmin()) {
            return false;
        }

        return self::indodanaEligible($order);
    }

    private static function indodanaEligible(Order $order): bool
    {
        if (! self::nicepayConfigured()) {
            return false;
        }

        $total = (int) $order->grand_total;

        return $total >= self::INDODANA_MIN_AMOUNT
            && $total <= self::INDODANA_MAX_AMOUNT
            && mb_strlen((string) $order->customer_email) <= self::INDODANA_MAX_EMAIL_LENGTH;
    }
}
