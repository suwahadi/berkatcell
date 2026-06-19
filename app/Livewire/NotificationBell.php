<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public string $position = 'bottom';

    public string $align = 'end';

    #[Computed]
    public function unreadCount(): int
    {
        return $this->user()?->unreadNotifications()->count() ?? 0;
    }

    #[Computed]
    public function recent(): Collection
    {
        $user = $this->user();

        if ($user === null) {
            return collect();
        }

        return $user->notifications()->latest()->limit(5)->get();
    }

    public function open(string $id): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        $notification = $user->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        $this->dispatch('notifications-updated');
        $this->refreshState();

        $orderNumber = $notification?->data['order_number'] ?? null;

        if ($orderNumber !== null) {
            $this->redirect(
                route($user->isAdmin() ? 'admin.orders.show' : 'dashboard.orders.show', $orderNumber),
                navigate: true,
            );
        }
    }

    public function markAllRead(): void
    {
        $this->user()?->unreadNotifications->markAsRead();

        $this->dispatch('notifications-updated');
        $this->refreshState();
    }

    #[On('notifications-updated')]
    public function refreshState(): void
    {
        unset($this->unreadCount, $this->recent);
    }

    public function indexUrl(): string
    {
        return ($this->user()?->isAdmin() ?? false)
            ? route('admin.notifications.index')
            : route('dashboard.notifications.index');
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user;
    }
}
