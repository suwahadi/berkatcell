<?php

use App\Enums\NotificationType;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifikasi')] #[Layout('layouts::app')] class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $filter = 'all';

    public function updating($property): void
    {
        if ($property === 'filter') {
            $this->resetPage();
        }
    }

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()
            ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        $this->dispatch('notifications-updated');
        unset($this->notifications, $this->unreadCount);

        $orderNumber = $notification?->data['order_number'] ?? null;

        if ($orderNumber !== null) {
            $this->redirect(route('dashboard.orders.show', $orderNumber), navigate: true);
        }
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->first()?->markAsRead();
        unset($this->notifications, $this->unreadCount);
        $this->dispatch('notifications-updated');
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        unset($this->notifications, $this->unreadCount);
        $this->dispatch('notifications-updated');
        Flux::toast(variant: 'success', text: 'Semua notifikasi ditandai dibaca.');
    }

    public function delete(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->delete();
        unset($this->notifications, $this->unreadCount);
        $this->dispatch('notifications-updated');
        Flux::toast(variant: 'success', text: 'Notifikasi dihapus.');
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-5 p-4 lg:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Notifikasi</h1>
        @if ($this->unreadCount > 0)
            <flux:button wire:click="markAllRead" size="sm" variant="subtle" icon="check" class="cursor-pointer">
                Tandai semua dibaca
            </flux:button>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
        <flux:button wire:click="$set('filter', 'all')" size="sm" :variant="$filter === 'all' ? 'primary' : 'ghost'" class="cursor-pointer">Semua</flux:button>
        <flux:button wire:click="$set('filter', 'unread')" size="sm" :variant="$filter === 'unread' ? 'primary' : 'ghost'" class="cursor-pointer">
            Belum dibaca
            @if ($this->unreadCount > 0)
                <span class="ml-1 rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white">{{ $this->unreadCount }}</span>
            @endif
        </flux:button>
    </div>

    <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
        @forelse ($this->notifications as $notification)
            @php($data = $notification->data)
            @php($type = NotificationType::tryFrom($data['type'] ?? ''))
            <div class="flex items-start gap-3 px-5 py-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ $notification->read_at ? '' : 'bg-amber-50/40 dark:bg-amber-500/5' }}" wire:key="notif-{{ $notification->id }}">
                <flux:icon :name="$type?->icon() ?? 'bell'" class="mt-0.5 size-5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $data['title'] ?? 'Notifikasi' }}</p>
                        @unless ($notification->read_at)
                            <span class="inline-flex items-center rounded-sm bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400">Baru</span>
                        @endunless
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $data['message'] ?? '' }}</p>
                    <p class="mt-1 text-[11px] text-zinc-400 dark:text-zinc-500">{{ tanggal_id($notification->created_at) }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if (! empty($data['order_number']))
                        <flux:button wire:click="open('{{ $notification->id }}')" size="xs" variant="ghost" icon="eye" class="cursor-pointer" />
                    @elseif (! $notification->read_at)
                        <flux:button wire:click="markRead('{{ $notification->id }}')" size="xs" variant="ghost" icon="check" class="cursor-pointer" />
                    @endif
                    <flux:button wire:click="delete('{{ $notification->id }}')" wire:confirm="Hapus notifikasi ini?" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                </div>
            </div>
        @empty
            <div class="px-5 py-16 text-center text-sm text-zinc-400 dark:text-zinc-500">Belum ada notifikasi.</div>
        @endforelse
    </div>

    <div>{{ $this->notifications->links() }}</div>
</div>
