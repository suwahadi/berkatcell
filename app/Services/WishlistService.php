<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class WishlistService
{
    private const KEY = 'wishlist';

    public function has(int $productId): bool
    {
        if ($userId = Auth::id()) {
            return Wishlist::query()
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->exists();
        }

        return in_array($productId, $this->sessionIds(), true);
    }

    public function toggle(int $productId): bool
    {
        if ($userId = Auth::id()) {
            $row = Wishlist::query()
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->first();

            if ($row) {
                $row->delete();

                return false;
            }

            Wishlist::create(['user_id' => $userId, 'product_id' => $productId]);

            return true;
        }

        $ids = $this->sessionIds();

        if (in_array($productId, $ids, true)) {
            $this->saveSession(array_values(array_diff($ids, [$productId])));

            return false;
        }

        $ids[] = $productId;
        $this->saveSession($ids);

        return true;
    }

    public function remove(int $productId): void
    {
        if ($userId = Auth::id()) {
            Wishlist::query()
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->delete();

            return;
        }

        $this->saveSession(array_values(array_diff($this->sessionIds(), [$productId])));
    }

    public function clear(): void
    {
        if ($userId = Auth::id()) {
            Wishlist::query()->where('user_id', $userId)->delete();

            return;
        }

        Session::forget(self::KEY);
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function isEmpty(): bool
    {
        return $this->ids() === [];
    }

    public function ids(): array
    {
        if ($userId = Auth::id()) {
            return Wishlist::query()
                ->where('user_id', $userId)
                ->orderBy('id')
                ->pluck('product_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        return $this->sessionIds();
    }

    public function items(): Collection
    {
        $ids = $this->ids();

        if ($ids === []) {
            return collect();
        }

        $products = Product::query()
            ->active()
            ->with('category', 'variants', 'images')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect(array_reverse($ids))
            ->map(fn (int $id): ?Product => $products->get($id))
            ->filter()
            ->values();
    }

    public function migrateGuestToUser(User $user): void
    {
        $sessionIds = $this->sessionIds();

        foreach ($sessionIds as $productId) {
            Wishlist::query()->firstOrCreate([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
        }

        Session::forget(self::KEY);
    }

    private function sessionIds(): array
    {
        return array_values(array_filter(array_map('intval', Session::get(self::KEY, []))));
    }

    private function saveSession(array $ids): void
    {
        Session::put(self::KEY, array_values(array_unique($ids)));
    }
}
