<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\ShippingService;
use App\Services\VoucherService;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout')] #[Layout('layouts::storefront')] class extends Component {
    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';
    public string $shipping_address = '';

    public string $destinationQuery = '';
    public ?int $destinationId = null;
    public ?string $destinationLabel = null;

    public string $courier = '';

    public ?string $shipping_courier_name = null;
    public ?string $shipping_service = null;
    public ?string $shipping_service_label = null;
    public ?string $shipping_etd = null;
    public int $shipping_cost = 0;

    public string $voucher_code = '';
    public ?string $applied_voucher = null;
    public int $discount = 0;
    public ?string $voucher_error = null;

    public string $idempotency_key = '';

    public function mount(CartService $cart): void
    {
        if ($cart->isEmpty()) {
            $this->redirectRoute('cart.index', navigate: true);

            return;
        }

        if ($user = auth()->user()) {
            $this->customer_name = $user->name;
            $this->customer_email = $user->email;
            $this->customer_phone = $user->phone
                ?: (string) Order::ownedBy($user)->latest()->value('customer_phone');
        }

        $this->idempotency_key = (string) Str::uuid();
    }

    #[Computed]
    public function destinationResults(): array
    {
        if ($this->destinationId !== null || mb_strlen(trim($this->destinationQuery)) < 3) {
            return [];
        }

        try {
            return app(ShippingService::class)->searchDestinations($this->destinationQuery);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return [];
        }
    }

    public function selectDestination(int $id, string $label): void
    {
        $this->destinationId = $id;
        $this->destinationLabel = $label;
        $this->destinationQuery = $label;
        $this->reset(['shipping_courier_name', 'shipping_service', 'shipping_service_label', 'shipping_etd', 'shipping_cost']);
        unset($this->shippingOptions);
    }

    public function clearDestination(): void
    {
        $this->reset(['destinationId', 'destinationLabel', 'destinationQuery', 'shipping_courier_name', 'shipping_service', 'shipping_service_label', 'shipping_etd', 'shipping_cost']);
        unset($this->shippingOptions);
    }

    public function updatedCourier(): void
    {
        $this->reset(['shipping_courier_name', 'shipping_service', 'shipping_service_label', 'shipping_etd', 'shipping_cost']);
        unset($this->shippingOptions);
    }

    #[Computed]
    public function couriers(): array
    {
        return array_intersect_key(
            ShippingService::COURIERS,
            array_flip(ShippingService::ENABLED_COURIERS),
        );
    }

    #[Computed]
    public function shippingOptions(): array
    {
        if ($this->destinationId === null || $this->courier === '') {
            return [];
        }

        try {
            return app(ShippingService::class)->cost($this->destinationId, $this->weight, $this->courier);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return [];
        }
    }

    public function selectShipping(int $index): void
    {
        $option = $this->shippingOptions[$index] ?? null;

        if ($option === null) {
            return;
        }

        $this->shipping_courier_name = $option['name'] ?: (ShippingService::COURIERS[$this->courier] ?? strtoupper($this->courier));
        $this->shipping_service = $option['service'];
        $this->shipping_service_label = $option['description'];
        $this->shipping_etd = $option['etd'];
        $this->shipping_cost = (int) $option['cost'];
    }

    #[Computed]
    public function items()
    {
        return app(CartService::class)->items();
    }

    #[Computed]
    public function subtotal(): int
    {
        return app(CartService::class)->subtotal();
    }

    #[Computed]
    public function weight(): int
    {
        return app(CartService::class)->totalWeight();
    }

    public function grandTotal(): int
    {
        return max(0, $this->subtotal - $this->discount) + $this->shipping_cost;
    }

    public function updatedVoucherCode(): void
    {
        $this->voucher_error = null;
    }

    public function applyVoucher(): void
    {
        $this->voucher_error = null;

        if (trim($this->voucher_code) === '') {
            $this->voucher_error = 'Masukkan kode voucher terlebih dahulu.';

            return;
        }

        try {
            $voucher = app(VoucherService::class)->validate($this->voucher_code, $this->subtotal);
            $this->discount = app(VoucherService::class)->calculateDiscount($voucher, $this->subtotal);
            $this->applied_voucher = $voucher->code;
        } catch (BusinessRuleException $e) {
            $this->reset(['discount', 'applied_voucher']);
            $this->voucher_error = $e->getMessage();
        }
    }

    public function removeVoucher(): void
    {
        $this->reset(['voucher_code', 'applied_voucher', 'discount', 'voucher_error']);
    }

    public function placeOrder(CartService $cart, OrderService $orders): void
    {
        $validated = $this->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'shipping_address' => ['required', 'string'],
            'destinationId' => ['required', 'integer'],
            'courier' => ['required', 'string'],
            'shipping_cost' => ['required', 'integer', 'min:1'],
        ]);

        $payload = [
            'customer' => [
                'name' => $validated['customer_name'],
                'email' => $validated['customer_email'],
                'phone' => $validated['customer_phone'],
            ],
            'shipping' => [
                'destination_id' => $validated['destinationId'],
                'destination_label' => $this->destinationLabel,
                'address' => $validated['shipping_address'],
                'courier' => $validated['courier'],
                'courier_name' => $this->shipping_courier_name,
                'service' => $this->shipping_service,
                'service_label' => $this->shipping_service_label,
                'etd' => $this->shipping_etd,
                'cost' => $this->shipping_cost,
            ],
            'items' => $cart->toCheckoutItems(),
            'voucher_code' => $this->applied_voucher,
        ];

        try {
            $order = $orders->checkout($payload, $this->idempotency_key);
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        if (($user = auth()->user()) && blank($user->phone)) {
            $user->update(['phone' => $validated['customer_phone']]);
        }

        $cart->clear();
        $this->dispatch('cart-updated');
        $this->redirectRoute('checkout.success', ['order' => $order->uuid], navigate: false);
    }
}; ?>

<div class="pb-24 lg:pb-0">
    <h1 class="text-2xl font-extrabold tracking-tight text-ink">Checkout</h1>

    <form wire:submit="placeOrder" class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-black/[.06] bg-white p-5 shadow-card">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Data Pelanggan</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="customer_name" label="Nama Lengkap" />
                    <flux:input wire:model="customer_phone" label="Nomor Telepon" />
                    <flux:input wire:model="customer_email" type="email" label="Email" class="sm:col-span-2" />
                </div>
            </section>

            <section class="rounded-2xl border border-black/[.06] bg-white p-5 shadow-card">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Alamat Pengiriman</h2>

                <div class="mt-4">
                    @if ($destinationId)
                        <label class="text-sm font-medium text-ink/70">Wilayah Tujuan</label>
                        <div class="mt-1 flex items-center justify-between gap-3 rounded-lg border border-leaf/40 bg-leaf/10 px-4 py-2.5">
                            <span class="text-sm font-medium text-leaf-dark">{{ $destinationLabel }}</span>
                            <flux:button type="button" size="xs" variant="subtle" wire:click="clearDestination" class="cursor-pointer">Ubah</flux:button>
                        </div>
                    @else
                        <flux:input wire:model.live.debounce.500ms="destinationQuery"
                                    label="Wilayah Tujuan (Kecamatan / Kelurahan)"
                                    placeholder="Ketik min. 3 huruf, mis. Tanah Abang"
                                    icon="magnifying-glass" />

                        @if (! empty($this->destinationResults))
                            <div class="mt-2 max-h-56 divide-y divide-black/[.06] overflow-y-auto rounded-lg border border-black/[.06]">
                                @foreach ($this->destinationResults as $dest)
                                    <button type="button" wire:click="selectDestination({{ $dest['id'] }}, @js($dest['label']))"
                                            class="block w-full cursor-pointer px-3 py-2 text-left text-sm text-ink/70 transition hover:bg-gold/10">
                                        {{ $dest['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        @elseif (mb_strlen(trim($destinationQuery)) >= 3)
                            <p class="mt-2 text-xs text-ink/45" wire:loading.remove wire:target="destinationQuery">Tidak ada wilayah yang cocok.</p>
                            <p class="mt-2 text-xs text-ink/45" wire:loading wire:target="destinationQuery">Mencari wilayah...</p>
                        @endif
                    @endif

                    @error('destinationId')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4">
                    <flux:textarea wire:model="shipping_address" label="Alamat Lengkap" placeholder="Nama jalan, nomor, RT/RW, patokan..." rows="3" />
                </div>
            </section>

            <section class="rounded-2xl border border-black/[.06] bg-white p-5 shadow-card">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Metode Pengiriman</h2>
                <div class="mt-4">
                    <flux:select wire:model.live="courier" label="Pilih Kurir" placeholder="Pilih kurir…"
                                 class="cursor-pointer font-medium text-ink [&>option]:text-ink">
                        @foreach ($this->couriers as $code => $name)
                            <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="mt-4 space-y-2">
                    @if (! $destinationId)
                        <!-- <p class="rounded-r-md border-l-4 border-gold bg-gold/10 px-4 py-3 text-sm text-gold-deep">Pilih wilayah tujuan untuk melihat ongkos kirim.</p> -->
                    @elseif ($courier === '')
                        <!-- <p class="rounded-r-md border-l-4 border-gold bg-gold/10 px-4 py-3 text-sm text-gold-deep">Pilih kurir terlebih dahulu untuk melihat ongkos kirim.</p> -->
                    @else
                        <div wire:loading.flex wire:target="shippingOptions, courier, selectDestination" class="items-center gap-2 text-sm text-ink/55">
                            <flux:icon.arrow-path class="size-4 animate-spin" /> Menghitung ongkir...
                        </div>
                        <div wire:loading.remove wire:target="shippingOptions, courier, selectDestination" class="grid grid-cols-2 gap-2 lg:grid-cols-3">
                            @forelse ($this->shippingOptions as $i => $opt)
                                @php $courierLabel = $opt['name'] ?: (\App\Services\ShippingService::COURIERS[$courier] ?? strtoupper($courier)); @endphp
                                <label class="flex cursor-pointer flex-col gap-1.5 rounded-lg border px-4 py-3 transition {{ $shipping_service === $opt['service'] ? 'border-gold bg-gold/10' : 'border-black/[.06] hover:border-ink/30' }}">
                                    <div class="flex items-start gap-2">
                                        <input type="radio" name="ship" class="mt-0.5 accent-leaf"
                                               @checked($shipping_service === $opt['service'])
                                               wire:click="selectShipping({{ $i }})" />
                                        <p class="text-sm font-semibold text-ink">{{ $courierLabel }} <span class="text-gold-deep">{{ $opt['service'] }}</span></p>
                                    </div>
                                    <p class="text-xs text-ink/55">{{ $opt['description'] }} &middot; Estimasi {{ $opt['etd'] }}</p>
                                    <span class="text-sm font-bold text-ink">{{ rupiah($opt['cost']) }}</span>
                                </label>
                            @empty
                                <p class="col-span-2 text-sm text-ink/55 lg:col-span-3">Tidak ada layanan tersedia untuk tujuan ini.</p>
                            @endforelse
                        </div>

                        @error('shipping_cost')
                            <p class="flex items-center gap-1.5 rounded-r-md border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                                <flux:icon.exclamation-triangle class="size-4 shrink-0" /> {{ $message }}
                            </p>
                        @enderror
                    @endif
                </div>
            </section>
        </div>

        <div class="h-fit space-y-4 self-start rounded-2xl border border-black/[.06] bg-white p-5 shadow-card lg:sticky lg:top-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Ringkasan Pesanan</h2>

            <div class="divide-y divide-black/[.06]">
                @foreach ($this->items as $item)
                    <div class="flex justify-between gap-2 py-2 text-sm">
                        <span class="text-ink/70">{{ $item['product']->name }} <span class="text-ink/45">×{{ $item['quantity'] }}</span></span>
                        <span class="shrink-0 font-semibold text-ink">{{ rupiah($item['line_total']) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-black/[.06] pt-4">
                @if ($applied_voucher)
                    <div class="relative flex items-stretch overflow-hidden rounded-lg border border-dashed border-leaf/50 bg-leaf/10">
                        <div class="flex flex-1 items-center gap-3 py-3 pl-4 pr-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-leaf/15 text-leaf-dark">
                                <flux:icon.ticket class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold uppercase tracking-wide text-leaf-dark">{{ $applied_voucher }}</p>
                                <p class="text-xs font-medium text-leaf-dark/80">Hemat {{ rupiah($discount) }}</p>
                            </div>
                        </div>

                        {{-- Garis putus-putus + gunting (efek kupon sobek) --}}
                        <div class="relative flex items-center">
                            <span class="absolute -top-1.5 left-1/2 size-3 -translate-x-1/2 rounded-full bg-white"></span>
                            <span class="absolute -bottom-1.5 left-1/2 size-3 -translate-x-1/2 rounded-full bg-white"></span>
                            <div class="h-full border-l border-dashed border-leaf/50"></div>
                            <span class="absolute left-1/2 top-1/2 grid -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white p-0.5 text-leaf-dark/70">
                                <flux:icon.scissors class="size-3.5 -rotate-90" />
                            </span>
                        </div>

                        <div class="flex items-center px-3">
                            <button type="button" wire:click="removeVoucher" aria-label="Hapus voucher" title="Hapus voucher"
                                    class="grid size-8 place-items-center rounded-full bg-red-500 text-white shadow-sm ring-1 ring-red-600/20 transition hover:bg-red-600 active:scale-95">
                                <flux:icon.x-mark variant="micro" class="size-4" />
                            </button>
                        </div>
                    </div>
                @else
                    <label class="text-sm font-medium text-ink/70">Kode Voucher</label>
                    <div class="mt-1.5 flex gap-2">
                        <input type="text" wire:model="voucher_code" wire:keydown.enter.prevent="applyVoucher"
                               placeholder="HEMAT10"
                               class="h-10 min-w-0 flex-1 rounded-lg border border-black/10 bg-white px-3 text-sm text-ink placeholder:text-ink/35 focus:border-ink/30 focus:outline-none focus:ring-2 focus:ring-ink/10" />
                        <button type="button" wire:click="applyVoucher"
                                class="shrink-0 rounded-lg bg-[#555] px-5 text-sm font-semibold text-white transition hover:bg-[#444] active:bg-[#333]">
                            Pakai
                        </button>
                    </div>
                    @if ($voucher_error)
                        <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-red-600">
                            <flux:icon.exclamation-triangle variant="solid" class="size-4 shrink-0" /> {{ $voucher_error }}
                        </p>
                    @endif
                @endif
            </div>

            <div class="space-y-2 border-t border-black/[.06] pt-4 text-sm">
                <div class="flex justify-between"><span class="text-ink/55">Subtotal</span><span class="font-semibold text-ink">{{ rupiah($this->subtotal) }}</span></div>
                @if ($discount > 0)
                    <div class="flex justify-between text-leaf"><span>Diskon</span><span>-{{ rupiah($discount) }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-ink/55">Ongkos Kirim</span><span class="font-semibold text-ink">{{ rupiah($shipping_cost) }}</span></div>
                <div class="flex justify-between border-t border-black/[.06] pt-2 text-base font-bold">
                    <span class="text-ink">Total</span>
                    <span class="text-leaf">{{ rupiah($this->grandTotal()) }}</span>
                </div>
            </div>

            <div class="hidden lg:block">
                <flux:button type="submit" variant="primary" class="w-full cursor-pointer"
                             wire:loading.attr="disabled" wire:target="placeOrder">
                    <span wire:loading.remove wire:target="placeOrder">Buat Pesanan</span>
                    <span wire:loading wire:target="placeOrder">Memproses...</span>
                </flux:button>
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-black/[.06] bg-white px-4 py-3 shadow-[0_-2px_12px_rgba(0,0,0,0.08)] lg:hidden">
            <div class="flex items-center gap-3">
                <div class="leading-tight">
                    <p class="text-xs text-ink/55">Total</p>
                    <p class="text-lg font-extrabold text-leaf">{{ rupiah($this->grandTotal()) }}</p>
                </div>
                <flux:button type="submit" variant="primary" class="flex-1 cursor-pointer"
                             wire:loading.attr="disabled" wire:target="placeOrder">
                    <span wire:loading.remove wire:target="placeOrder">Buat Pesanan</span>
                    <span wire:loading wire:target="placeOrder">Memproses...</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
