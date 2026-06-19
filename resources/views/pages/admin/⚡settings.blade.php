<?php

use App\Models\Setting;
use App\Services\SettingService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan')] #[Layout('layouts::admin')] class extends Component {
    public array $values = [];

    public array $fields = [
        'site_name' => ['label' => 'Nama Situs'],
        'site_tagline' => ['label' => 'Tagline'],
        'site_email' => ['label' => 'Email Customer Service'],
        'site_phone' => ['label' => 'Nomor Telepon'],
        'site_whatsapp' => ['label' => 'Nomor WhatsApp', 'hint' => 'Nomor utama untuk tombol info produk & floating WhatsApp.'],
        'site_address' => ['label' => 'Alamat'],
        'origin_district_id' => ['label' => 'ID Kecamatan Asal (RajaOngkir)', 'hint' => 'Titik asal pengiriman gudang.'],
    ];

    public function mount(): void
    {
        foreach (array_keys($this->fields) as $key) {
            $this->values[$key] = (string) Setting::query()->where('key', $key)->value('value');
        }
    }

    public function save(): void
    {
        $this->validate(
            ['values.site_name' => ['required', 'string', 'max:255'], 'values.site_email' => ['nullable', 'email']],
            attributes: ['values.site_name' => 'nama situs', 'values.site_email' => 'email'],
        );

        foreach ($this->values as $key => $value) {
            SettingService::set($key, $value);
        }

        Flux::toast(variant: 'success', text: 'Pengaturan disimpan & cache diperbarui.');
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Pengaturan Situs</h1>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Konfigurasi dinamis yang diakses via helper <code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-amber-600 dark:bg-zinc-800 dark:text-amber-400">setting()</code>.</p>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        @foreach ($fields as $key => $field)
            <flux:input
                wire:model="values.{{ $key }}"
                :label="$field['label']"
                :description="$field['hint'] ?? null" />
        @endforeach

        <div class="flex justify-end border-t border-zinc-200 pt-5 dark:border-zinc-800">
            <flux:button type="submit" variant="primary" icon="check" class="cursor-pointer">Simpan Pengaturan</flux:button>
        </div>
    </form>
</div>
