<div wire:poll.30s>
    <flux:dropdown :position="$position" :align="$align">
        <flux:button variant="ghost" size="sm" class="relative cursor-pointer" aria-label="Notifikasi">
            <flux:icon.bell class="size-5" />

            @if ($this->unreadCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-zinc-900">
                    {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-2 py-1.5">
                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">Notifikasi</span>
                @if ($this->unreadCount > 0)
                    <button type="button" wire:click="markAllRead" class="cursor-pointer text-xs font-medium text-amber-600 hover:underline dark:text-amber-500">
                        Tandai semua dibaca
                    </button>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($this->recent as $notification)
                @php($data = $notification->data)
                @php($type = \App\Enums\NotificationType::tryFrom($data['type'] ?? ''))
                <flux:menu.item
                    as="button"
                    wire:key="bell-{{ $notification->id }}"
                    wire:click="open('{{ $notification->id }}')"
                    :icon="$type?->icon() ?? 'bell'"
                    class="cursor-pointer {{ $notification->read_at ? '' : 'bg-amber-50/60 dark:bg-amber-500/10' }}"
                >
                    <div class="flex flex-col gap-0.5 text-left">
                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $data['title'] ?? 'Notifikasi' }}</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $data['message'] ?? '' }}</span>
                        <span class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $notification->created_at?->diffForHumans() }}</span>
                    </div>
                </flux:menu.item>
            @empty
                <div class="px-3 py-8 text-center text-sm text-zinc-400 dark:text-zinc-500">Belum ada notifikasi.</div>
            @endforelse

            <flux:menu.separator />

            <flux:menu.item :href="$this->indexUrl()" wire:navigate>
                Lihat Semua
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
