<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VariantFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Variant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'price',
        'promo_price',
        'stock',
        'weight',
        'image_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'promo_price' => 'integer',
            'stock' => 'integer',
            'weight' => 'integer',
            'image_id' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    public function thumbnailUrl(): ?string
    {
        $this->loadMissing('image', 'product');

        return $this->image?->url ?? $this->product?->thumbnailUrl();
    }

    protected function effectivePrice(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->promo_price ?? $this->price,
        );
    }

    public function isOnPromo(): bool
    {
        return $this->promo_price !== null && $this->promo_price < $this->price;
    }
}
