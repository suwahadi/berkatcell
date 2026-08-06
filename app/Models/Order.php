<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'order_number',
        'idempotency_key',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_province_id',
        'shipping_city_id',
        'shipping_district_id',
        'shipping_destination_label',
        'shipping_address',
        'shipping_courier',
        'shipping_courier_name',
        'shipping_service',
        'shipping_service_label',
        'shipping_etd',
        'shipping_cost',
        'voucher_id',
        'discount_amount',
        'subtotal',
        'grand_total',
        'status',
        'active_payment_attempt_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_cost' => 'integer',
            'discount_amount' => 'integer',
            'subtotal' => 'integer',
            'grand_total' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public const PICKUP_COURIER = 'pickup';

    public function isPickup(): bool
    {
        return $this->shipping_courier === self::PICKUP_COURIER;
    }

    /**
     * Label status yang sadar konteks: pesanan yang diambil sendiri tidak pernah
     * "dikirim". Enum-nya tidak berubah, hanya teks yang ditampilkan.
     */
    public function statusLabel(): string
    {
        if ($this->isPickup() && $this->status === OrderStatus::SHIPPED) {
            return 'Sudah Diambil';
        }

        return $this->status->label();
    }

    /**
     * Label ringkas jasa kirim untuk headline, mis. "JNE REG" atau "J&T Express EZ".
     * Memakai peta kurir agar singkat; jatuh ke nama API lalu kode bila tidak dikenal.
     */
    public function shippingCourierName(): string
    {
        $courier = \App\Services\ShippingService::COURIERS[$this->shipping_courier]
            ?? ($this->shipping_courier_name ?: strtoupper((string) $this->shipping_courier));

        return trim($courier.' '.(string) $this->shipping_service);
    }

    /**
     * Baris detail layanan kirim dari API RajaOngkir: nama lengkap kurir, deskripsi,
     * dan estimasi tiba — digabung jadi satu kalimat ringkas. Kosong jika tak ada data.
     */
    public function shippingServiceDetail(): string
    {
        $parts = array_filter([
            (string) $this->shipping_courier_name,
            (string) $this->shipping_service_label,
            filled($this->shipping_etd) ? 'Estimasi tiba '.$this->shipping_etd : '',
        ]);

        return implode(' · ', $parts);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('customer_email', $user->email);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class)->orderBy('created_at')->orderBy('id');
    }

    public function activePaymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'active_payment_attempt_id');
    }
}
