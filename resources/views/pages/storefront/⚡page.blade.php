<?php

use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Informasi')] #[Layout('layouts::storefront')] class extends Component {
    public Page $page;

    public function mount(Page $page): void
    {
        abort_unless($page->is_active, 404);

        $this->page = $page;
    }
}; ?>

<div>
    <h1 class="text-2xl font-extrabold tracking-tight text-ink">{{ $page->title }}</h1>
    <div class="mt-2 h-1 w-16 rounded-full bg-gold"></div>
    <div class="rich-text mt-6 max-w-none">{!! $page->content !!}</div>
</div>
