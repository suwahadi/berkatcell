<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'original_price',
        'promo_price',
        'description',
        'weight',
        'stock',
        'is_active',
        'badge',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'integer',
            'promo_price' => 'integer',
            'weight' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function mainImage(): ?ProductImage
    {
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->firstWhere('is_main', true) ?? $images->first();
    }

    public function thumbnailUrl(): ?string
    {
        return $this->mainImage()?->url;
    }

    protected function effectivePrice(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->promo_price ?? $this->original_price,
        );
    }

    public function isOnPromo(): bool
    {
        if ($this->hasVariants()) {
            return $this->variants->contains(fn (Variant $v): bool => $v->isOnPromo());
        }

        return $this->promo_price !== null && $this->promo_price < $this->original_price;
    }

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    public function fromPrice(): int
    {
        if ($this->hasVariants()) {
            return (int) $this->variants->min(fn (Variant $v): int => $v->effective_price);
        }

        return $this->effective_price;
    }

    public static function effectivePriceSql(): string
    {
        return '(COALESCE('
            .'(SELECT MIN(COALESCE(variants.promo_price, variants.price)) '
            .'FROM variants WHERE variants.product_id = products.id), '
            .'COALESCE(products.promo_price, products.original_price)))';
    }

    public function totalStock(): int
    {
        if ($this->hasVariants()) {
            return (int) $this->variants->sum('stock');
        }

        return $this->stock;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function badgeMeta(): ?array
    {
        return match ($this->badge) {
            'new' => ['label' => 'Baru', 'tone' => 'new'],
            'hot' => ['label' => 'Terlaris', 'tone' => 'hot'],
            default => null,
        };
    }
}
