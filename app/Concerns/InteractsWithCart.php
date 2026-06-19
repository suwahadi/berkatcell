<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Services\CartService;
use Flux\Flux;

trait InteractsWithCart
{
    public function addToCart(int $productId, ?int $variantId = null, int $quantity = 1): void
    {
        try {
            app(CartService::class)->add($productId, $quantity, $variantId);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        $this->dispatch('cart-updated');
        Flux::toast(variant: 'success', text: 'Barang ditambahkan ke keranjang.');
    }
}
