<x-layouts::auth :title="__('Lupa kata sandi')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Lupa kata sandi')" :description="__('Masukkan email Anda untuk menerima tautan atur ulang kata sandi')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Alamat email')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button" x-bind:disabled="submitting">
                <span x-show="!submitting">{{ __('Kirim tautan atur ulang kata sandi') }}</span>
                <span x-show="submitting" x-cloak>{{ __('Memproses...') }}</span>
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('Atau, kembali ke') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('masuk') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
