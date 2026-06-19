<?php

use App\Enums\VoucherType;
use App\Models\Voucher;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Voucher')] #[Layout('layouts::admin')] class extends Component {
    public ?int $editingId = null;
    public string $code = '';
    public string $discount_type = 'persentase';
    public ?int $discount_value = null;
    public int $min_purchase = 0;
    public int $max_usage = 0;
    public string $valid_until = '';

    public ?int $deletingId = null;
    public ?string $deletingCode = null;

    #[Computed]
    public function vouchers()
    {
        return Voucher::query()->latest()->get();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'code', 'discount_value', 'min_purchase', 'max_usage', 'valid_until']);
        $this->resetValidation();
        $this->discount_type = 'persentase';
        $this->valid_until = now()->addDays(30)->format('Y-m-d\TH:i');
        Flux::modal('voucher-form')->show();
    }

    public function edit(int $id): void
    {
        $voucher = Voucher::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $voucher->id;
        $this->code = $voucher->code;
        $this->discount_type = $voucher->discount_type->value;
        $this->discount_value = $voucher->discount_value;
        $this->min_purchase = $voucher->min_purchase;
        $this->max_usage = $voucher->max_usage;
        $this->valid_until = $voucher->valid_until->format('Y-m-d\TH:i');
        Flux::modal('voucher-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('vouchers', 'code')->ignore($this->editingId)],
            'discount_type' => ['required', Rule::enum(VoucherType::class)],
            'discount_value' => ['required', 'integer', 'min:1'],
            'min_purchase' => ['required', 'integer', 'min:0'],
            'max_usage' => ['required', 'integer', 'min:0'],
            'valid_until' => ['required', 'date'],
        ], attributes: [
            'code' => 'kode', 'discount_value' => 'nilai diskon', 'min_purchase' => 'minimal belanja',
            'max_usage' => 'kuota', 'valid_until' => 'berlaku hingga',
        ]);

        if ($validated['discount_type'] === VoucherType::PERCENTAGE->value && $validated['discount_value'] > 100) {
            $this->addError('discount_value', 'Diskon persentase tidak boleh melebihi 100%.');

            return;
        }

        $payload = [
            ...$validated,
            'code' => strtoupper($validated['code']),
            'valid_until' => Carbon::parse($validated['valid_until']),
        ];

        if ($this->editingId) {
            Voucher::query()->whereKey($this->editingId)->update($payload);
        } else {
            Voucher::query()->create([...$payload, 'used_count' => 0]);
        }

        Flux::modal('voucher-form')->close();
        Flux::toast(variant: 'success', text: 'Voucher disimpan.');
    }

    public function confirmDelete(int $id): void
    {
        $voucher = Voucher::query()->findOrFail($id);
        $this->deletingId = $voucher->id;
        $this->deletingCode = $voucher->code;
        Flux::modal('voucher-delete')->show();
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            Voucher::query()->whereKey($this->deletingId)->delete();
            Flux::toast(variant: 'success', text: 'Voucher dihapus.');
        }

        Flux::modal('voucher-delete')->close();
        $this->reset(['deletingId', 'deletingCode']);
    }
}; ?>

<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Voucher</h1>
        <flux:button wire:click="create" variant="primary" size="sm" icon="plus" class="cursor-pointer">Voucher Baru</flux:button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                <tr>
                    <th class="px-5 py-3.5">Kode</th>
                    <th class="px-5 py-3.5">Tipe</th>
                    <th class="px-5 py-3.5 text-right">Nilai</th>
                    <th class="px-5 py-3.5 text-right">Min. Belanja</th>
                    <th class="px-5 py-3.5 text-right">Pemakaian</th>
                    <th class="px-5 py-3.5">Berlaku Hingga</th>
                    <th class="px-5 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->vouchers as $voucher)
                    <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="vc-{{ $voucher->id }}">
                        <td class="whitespace-nowrap px-5 py-3.5 font-mono font-bold text-zinc-800 dark:text-zinc-100">{{ $voucher->code }}</td>
                        <td class="px-5 py-3.5 text-zinc-500 dark:text-zinc-400">{{ $voucher->discount_type->label() }}</td>
                        <td class="px-5 py-3.5 text-right font-mono text-zinc-900 dark:text-white">{{ $voucher->discount_type === App\Enums\VoucherType::PERCENTAGE ? $voucher->discount_value.'%' : rupiah($voucher->discount_value) }}</td>
                        <td class="px-5 py-3.5 text-right font-mono text-zinc-500 dark:text-zinc-400">{{ rupiah($voucher->min_purchase) }}</td>
                        <td class="px-5 py-3.5 text-right font-mono text-zinc-500 dark:text-zinc-400">{{ $voucher->used_count }} / {{ $voucher->max_usage === 0 ? '∞' : $voucher->max_usage }}</td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-zinc-500 dark:text-zinc-400">{{ tanggal_id($voucher->valid_until) }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <flux:button wire:click="edit({{ $voucher->id }})" size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" />
                            <flux:button wire:click="confirmDelete({{ $voucher->id }})" size="xs" variant="ghost" icon="trash" class="cursor-pointer" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-zinc-400 dark:text-zinc-500">Belum ada voucher.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal name="voucher-form" class="w-full md:w-[40rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Voucher' : 'Voucher Baru' }}</flux:heading>
                <flux:subheading>Atur kode, jenis diskon, dan masa berlaku voucher.</flux:subheading>
            </div>

            <div class="grid items-start gap-x-5 gap-y-4 sm:grid-cols-2">
                <flux:input wire:model="code" label="Kode" placeholder="DISKON10" class="sm:col-span-2" />
                <flux:select wire:model="discount_type" label="Tipe Diskon">
                    <flux:select.option value="persentase">Persentase (%)</flux:select.option>
                    <flux:select.option value="nominal_tetap">Nominal Tetap (Rp)</flux:select.option>
                </flux:select>
                <flux:input wire:model="discount_value" type="number" label="Nilai" placeholder="10" />
                <flux:input wire:model="min_purchase" type="number" label="Min. Belanja (Rp)" placeholder="0" />
                <flux:input wire:model="max_usage" type="number" label="Kuota" description="0 = tak terbatas" placeholder="0" />
                <flux:input wire:model="valid_until" type="datetime-local" label="Berlaku Hingga" class="sm:col-span-2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="voucher-delete" class="md:w-[26rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus voucher?</flux:heading>
                <flux:subheading>
                    Voucher <span class="font-mono font-bold text-zinc-800 dark:text-zinc-100">{{ $deletingCode }}</span>
                    akan dihapus permanen dan tidak dapat dikembalikan.
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
