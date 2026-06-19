<?php

use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengguna')] #[Layout('layouts::admin')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $role = '';

    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $roleField = UserRole::CUSTOMER->value;
    public string $password = '';
    public string $password_confirmation = '';

    public ?int $deletingId = null;

    public function updating($property): void
    {
        if (in_array($property, ['search', 'role'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->when($this->role !== '', fn ($q) => $q->where('role', $this->role))
            ->latest()
            ->paginate(15);
    }

    public function roleOptions(): array
    {
        return UserRole::cases();
    }

    public function edit(int $id): void
    {
        $user = User::query()->findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->roleField = $user->role->value;
        $this->password = '';
        $this->password_confirmation = '';

        $this->resetValidation();
        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'roleField' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::default()],
        ], attributes: [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'roleField' => 'role',
            'password' => 'password',
        ]);

        if ($this->editingId === auth()->id() && $validated['roleField'] !== UserRole::ADMIN->value) {
            Flux::toast(variant: 'warning', text: 'Anda tidak dapat menurunkan role akun Anda sendiri.');

            return;
        }

        $user = User::query()->findOrFail($this->editingId);
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'role' => $validated['roleField'],
        ]);

        if ($validated['password'] ?? false) {
            $user->password = $validated['password'];
        }

        $user->save();

        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: 'Pengguna diperbarui.');
        $this->reset(['editingId', 'name', 'email', 'phone', 'roleField', 'password', 'password_confirmation']);
    }

    private function guardDeletable(int $id): ?User
    {
        if ($id === auth()->id()) {
            Flux::toast(variant: 'warning', text: 'Anda tidak dapat menghapus akun Anda sendiri.');

            return null;
        }

        $user = User::query()->findOrFail($id);

        if ($user->role === UserRole::ADMIN && User::query()->where('role', UserRole::ADMIN->value)->count() <= 1) {
            Flux::toast(variant: 'warning', text: 'Tidak dapat menghapus administrator terakhir.');

            return null;
        }

        return $user;
    }

    public function confirmDelete(int $id): void
    {
        if (! $this->guardDeletable($id)) {
            return;
        }

        $this->deletingId = $id;
        Flux::modal('user-delete')->show();
    }

    public function delete(): void
    {
        $user = $this->deletingId ? $this->guardDeletable($this->deletingId) : null;

        if ($user) {
            try {
                $user->delete();
                Flux::toast(variant: 'success', text: 'Pengguna dihapus.');
            } catch (QueryException) {
                Flux::toast(variant: 'danger', text: 'Pengguna tidak dapat dihapus karena masih terkait data lain.');
            }
        }

        Flux::modal('user-delete')->close();
        $this->reset('deletingId');
    }
}; ?>

<div class="space-y-5">
    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Pengguna</h1>

    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari nama / email / telepon..." icon="magnifying-glass" size="sm" clearable />
        </div>
        <flux:select wire:model.live="role" size="sm" class="w-full cursor-pointer sm:w-52" placeholder="Semua role">
            <flux:select.option value="">Semua Role</flux:select.option>
            @foreach ($this->roleOptions() as $opt)
                <flux:select.option :value="$opt->value">{{ $opt->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Pengguna</th>
                    <th class="px-5 py-3.5">Telepon</th>
                    <th class="px-5 py-3.5">Role</th>
                    <th class="px-5 py-3.5">Terdaftar</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->users as $user)
                    <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="user-{{ $user->id }}">
                        <td class="px-5 py-3.5">
                            <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $user->name }}</p>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $user->email }}</p>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-zinc-600 dark:text-zinc-300">{{ $user->phone ?: '—' }}</td>
                        <td class="px-5 py-3.5">
                            @if ($user->role === \App\Enums\UserRole::ADMIN)
                                <span class="inline-flex items-center rounded-sm bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400">{{ $user->role->label() }}</span>
                            @else
                                <span class="inline-flex items-center rounded-sm bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ $user->role->label() }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-zinc-500 dark:text-zinc-400">{{ tanggal_id($user->created_at) }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <flux:button wire:click="edit({{ $user->id }})" size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                            @unless ($user->id === auth()->id())
                                <flux:button wire:click="confirmDelete({{ $user->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-zinc-400 dark:text-zinc-500">Tidak ada pengguna.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->users->links() }}</div>

    <flux:modal name="user-form" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">Ubah Pengguna</flux:heading>

            <flux:input wire:model="name" label="Nama" />
            <flux:input wire:model="email" type="email" label="Email" />
            <flux:input wire:model="phone" label="Nomor Telepon" placeholder="Opsional" />

            <flux:select wire:model="roleField" label="Role" class="cursor-pointer">
                @foreach ($this->roleOptions() as $opt)
                    <flux:select.option :value="$opt->value">{{ $opt->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:separator variant="subtle" />

            <flux:input wire:model="password" type="password" label="Password Baru" viewable
                description="Kosongkan jika tidak ingin mengubah password." />
            <flux:input wire:model="password_confirmation" type="password" label="Konfirmasi Password" viewable />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="user-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Pengguna</flux:heading>
                <flux:subheading>Yakin untuk menghapus data ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
