<?php

use App\Services\WishlistService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $count = 0;

    public function mount(WishlistService $wishlist): void
    {
        $this->count = $wishlist->count();
    }

    #[On('wishlist-updated')]
    public function refresh(WishlistService $wishlist): void
    {
        $this->count = $wishlist->count();
    }
}; ?>

<span class="contents">
    @if ($count > 0)
        <span class="absolute right-1 top-1 flex h-[17px] min-w-[17px] items-center justify-center rounded-full bg-gold px-1 text-[10px] font-bold leading-none text-ink">
            {{ $count }}
        </span>
    @endif
</span>
