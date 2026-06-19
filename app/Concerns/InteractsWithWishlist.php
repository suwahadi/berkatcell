<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Services\WishlistService;
use Flux\Flux;

trait InteractsWithWishlist
{
    public function toggleWishlist(int $productId): void
    {
        $saved = app(WishlistService::class)->toggle($productId);

        $this->dispatch('wishlist-updated');
        Flux::toast(
            variant: $saved ? 'success' : 'warning',
            text: $saved ? 'Disimpan ke favorit.' : 'Dihapus dari favorit.',
        );
    }

    public function wishlistIds(): array
    {
        return app(WishlistService::class)->ids();
    }
}
