<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    public const RESERVED_SLUGS = [
        'products', 'product', 'cart', 'wishlist', 'checkout', 'order',
        'dashboard', 'admin', 'settings', 'payments', 'livewire', 'storage',
        'login', 'logout', 'register', 'forgot-password', 'reset-password',
        'verify-email', 'confirm-password', 'two-factor-challenge', 'user',
    ];

    protected $fillable = [
        'title',
        'slug',
        'content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function isReservedSlug(string $slug): bool
    {
        return in_array($slug, self::RESERVED_SLUGS, true);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
