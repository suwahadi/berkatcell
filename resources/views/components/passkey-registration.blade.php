<div
    x-data="passkeyRegistration({
        optionsUrl: @js(route('passkey.registration-options')),
        storeUrl: @js(route('passkey.store')),
        csrf: @js(csrf_token()),
    })"
    class="space-y-3"
>
    <div x-show="! supported" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
        {{ __('Your browser does not support passkeys.') }}
    </div>

    <div x-show="supported" class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <input
            type="text"
            x-model="name"
            x-on:keydown.enter.prevent="register"
            :disabled="busy"
            maxlength="255"
            placeholder="{{ __('Passkey name (e.g. MacBook Touch ID)') }}"
            class="flex-1 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-400 focus:outline-none focus:ring-0 disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
        />

        <flux:button
            variant="primary"
            icon="key"
            x-on:click="register"
            x-bind:disabled="busy"
        >
            <span x-show="! busy">{{ __('Add passkey') }}</span>
            <span x-show="busy" x-cloak>{{ __('Waiting for device…') }}</span>
        </flux:button>
    </div>

    <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600 dark:text-red-400"></p>
</div>

@once
    <script>
        (function () {
            const factory = (config) => ({
                name: '',
                busy: false,
                error: '',
                supported: !! (window.PublicKeyCredential && navigator.credentials && navigator.credentials.create),

                async register() {
                    this.error = '';

                    if (! this.supported) {
                        this.error = @json(__('Your browser does not support passkeys.'));
                        return;
                    }

                    this.busy = true;

                    try {
                        const optionsResponse = await fetch(config.optionsUrl, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        if (! optionsResponse.ok) {
                            throw new Error(@json(__('Could not start passkey registration.')));
                        }

                        const { options } = await optionsResponse.json();

                        const publicKey = PublicKeyCredential.parseCreationOptionsFromJSON(options);

                        const credential = await navigator.credentials.create({ publicKey });

                        const storeResponse = await fetch(config.storeUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': config.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                name: this.name.trim() || @json(__('Passkey')),
                                credential: credential.toJSON(),
                            }),
                        });

                        if (! storeResponse.ok) {
                            const data = await storeResponse.json().catch(() => ({}));
                            throw new Error(data.message || @json(__('Could not save the passkey.')));
                        }

                        this.name = '';

                        if (window.Livewire) {
                            window.Livewire.dispatch('passkey-registered');
                        }
                    } catch (e) {
                        if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) {
                            return;
                        }

                        this.error = (e && e.message) ? e.message : @json(__('Passkey registration failed.'));
                    } finally {
                        this.busy = false;
                    }
                },
            });

            const register = () => window.Alpine.data('passkeyRegistration', factory);

            if (window.Alpine) {
                register();
            } else {
                document.addEventListener('alpine:init', register);
            }
        })();
    </script>
@endonce
