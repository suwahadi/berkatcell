<?php

use App\Enums\NotificationType;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifikasi')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $filter = 'all';

    public ?string $deletingId = null;

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
            $this->redirect(route('admin.orders.show', $orderNumber), navigate: true);
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

    public function confirmDelete(string $id): void
    {
        $this->deletingId = $id;
        Flux::modal('notification-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId !== null) {
            auth()->user()->notifications()->whereKey($this->deletingId)->delete();
            unset($this->notifications, $this->unreadCount);
            $this->dispatch('notifications-updated');
            Flux::toast(variant: 'success', text: 'Notifikasi dihapus.');
        }

        Flux::modal('notification-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div class="space-y-5">
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

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Notifikasi</th>
                    <th class="px-5 py-3.5">Pesanan</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Waktu</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->notifications as $notification)
                    @php($data = $notification->data)
                    @php($type = NotificationType::tryFrom($data['type'] ?? ''))
                    <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ $notification->read_at ? '' : 'bg-amber-50/40 dark:bg-amber-500/5' }}" wire:key="notif-{{ $notification->id }}">
                        <td class="px-5 py-3.5">
                            <div class="flex items-start gap-3">
                                <flux:icon :name="$type?->icon() ?? 'bell'" class="mt-0.5 size-5 text-zinc-400 dark:text-zinc-500" />
                                <div>
                                    <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $data['title'] ?? 'Notifikasi' }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $data['message'] ?? '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5">
                            @if (! empty($data['order_number']))
                                <a href="{{ route('admin.orders.show', $data['order_number']) }}" wire:navigate class="font-mono text-xs font-semibold text-amber-700 hover:underline dark:text-amber-500">{{ $data['order_number'] }}</a>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($notification->read_at)
                                <span class="inline-flex items-center rounded-sm bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">Dibaca</span>
                            @else
                                <span class="inline-flex items-center rounded-sm bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400">Baru</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-zinc-500 dark:text-zinc-400">{{ tanggal_id($notification->created_at) }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <flux:button wire:click="open('{{ $notification->id }}')" size="xs" variant="ghost" icon="eye" class="cursor-pointer" />
                            @unless ($notification->read_at)
                                <flux:button wire:click="markRead('{{ $notification->id }}')" size="xs" variant="ghost" icon="check" class="cursor-pointer" />
                            @endunless
                            <flux:button wire:click="confirmDelete('{{ $notification->id }}')" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-zinc-400 dark:text-zinc-500">Tidak ada notifikasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->notifications->links() }}</div>

    <flux:modal name="notification-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Notifikasi</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
