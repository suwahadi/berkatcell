<?php

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentAttemptService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pesanan Berhasil')] #[Layout('layouts::storefront')] class extends Component
{
    public Order $order;

    public ?string $payment_method = null;

    public bool $changingMethod = false;

    public function mount(Order $order): void
    {
        $this->order = $order->load('items.product', 'items.variant', 'voucher', 'activePaymentAttempt');
        $this->payment_method = $this->order->activePaymentAttempt?->payment_method
            ?? array_key_first($this->paymentMethods());
    }

    #[Computed]
    public function attempt(): ?PaymentAttempt
    {
        $attempt = $this->order->activePaymentAttempt;

        return $attempt instanceof PaymentAttempt && $attempt->isOpen() ? $attempt : null;
    }

    public function methods(): array
    {
        return array_map(fn (array $method): string => $method['name'], $this->paymentMethods());
    }

    public function paymentMethods(): array
    {
        return PaymentMethods::available($this->order, auth()->user());
    }

    public function pay(PaymentAttemptService $service): void
    {
        $this->validate([
            'payment_method' => ['required', Rule::in(array_keys($this->paymentMethods()))],
        ]);

        $this->startPayment($service, $this->payment_method);
        $this->changingMethod = false;
    }

    public function continuePayment(PaymentAttemptService $service): void
    {
        $attempt = $this->attempt();

        if (! $attempt) {
            return;
        }

        $this->startPayment($service, $attempt->payment_method);
    }

    /**
     * Tagihan Indodana hidup 24 jam dan setiap polling memanggil Status Inquiry Nicepay,
     * jadi jedanya lebih panjang daripada tagihan Midtrans yang hanya hidup beberapa menit.
     */
    public function pollSeconds(): int
    {
        return $this->attempt()?->provider === PaymentMethods::NICEPAY ? 120 : 30;
    }

    public function startChangeMethod(): void
    {
        $this->changingMethod = true;
    }

    public function cancelChangeMethod(): void
    {
        $this->changingMethod = false;
    }

    public function refreshStatus(PaymentAttemptService $service): void
    {
        $service->syncActiveAttempt($this->order);
        $this->order->refresh();

        if ($this->order->status === OrderStatus::PAID) {
            $this->js('window.location.reload()');
        }
    }

    protected function startPayment(PaymentAttemptService $service, string $method): void
    {
        try {
            $attempt = Cache::lock('order-pay:'.$this->order->id, 60)
                ->block(5, fn () => $service->createOrReuseActiveAttempt($this->order, $method));
        } catch (BusinessRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        } catch (LockTimeoutException) {
            Flux::toast(variant: 'danger', text: 'Pembayaran sedang diproses. Tunggu sebentar lalu coba lagi.');

            return;
        }

        $this->order->refresh();

        if ($attempt->provider === PaymentMethods::NICEPAY) {
            $this->redirect(route('payments.nicepay.pay', ['order' => $this->order->uuid]));

            return;
        }

        if (blank($attempt->snap_token)) {
            Flux::toast(variant: 'danger', text: 'Gagal memuat halaman pembayaran. Coba lagi.');

            return;
        }

        $this->dispatch('snap-pay', token: $attempt->snap_token);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    @php $order->loadMissing('items.product', 'items.variant', 'voucher'); @endphp
    @php $isPaid = $order->status === \App\Enums\OrderStatus::PAID; @endphp

    <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-center sm:gap-5 sm:text-left">
        <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl {{ $isPaid ? 'bg-leaf/15' : 'bg-gold/15' }}">
            <flux:icon.check-circle class="size-8 {{ $isPaid ? 'text-leaf' : 'text-gold-deep' }}" />
        </div>
        <div class="flex-1">
            <h1 class="text-xl font-extrabold tracking-tight text-ink sm:text-2xl">Pesanan Berhasil Dibuat</h1>
            <p class="mt-1 text-sm text-ink/65">Terima kasih, {{ $order->customer_name }}. Pesanan Anda telah kami terima.</p>
        </div>

        <div class="shrink-0" x-data="{ copied: false, t: null }">
            <button type="button"
                    @click="
                        navigator.clipboard?.writeText(@js($order->order_number));
                        copied = true; clearTimeout(t); t = setTimeout(() => copied = false, 2000);
                    "
                    class="group inline-flex items-center gap-2.5 rounded-full bg-ink py-1.5 pl-4 pr-2.5 ring-1 ring-white/10 transition hover:bg-ink-soft"
                    :title="copied ? 'Tersalin' : 'Klik untuk menyalin'">
                <span class="font-mono text-sm font-bold tracking-wider text-gold">{{ $order->order_number }}</span>
                <span class="h-3.5 w-px bg-white/20"></span>
                <span class="inline-flex items-center gap-1 text-xs font-medium transition-colors"
                      :class="copied ? 'text-leaf' : 'text-white/70 group-hover:text-white'">
                    <span x-show="!copied"><flux:icon.clipboard-document class="size-3.5" /></span>
                    <span x-show="copied" x-cloak><flux:icon.check class="size-3.5" /></span>
                    <span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                </span>
            </button>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_350px] lg:items-start">

        <div class="space-y-6">
            @if ($isPaid)
                <div class="overflow-hidden rounded-2xl bg-leaf-dark p-5 shadow-card sm:p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-white/20">
                            <flux:icon.check-badge class="size-7 text-white" />
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-white">{{ $order->statusLabel() }}</p>
                            <h2 class="mt-1 text-lg font-extrabold tracking-tight text-white">Pembayaran Berhasil</h2>
                            <p class="mt-1 max-w-md text-sm leading-relaxed text-white">
                                @if ($order->paid_at)
                                    Pembayaran Anda telah kami terima pada <span class="font-semibold">{{ tanggal_id($order->paid_at) }}</span>.
                                @else
                                    Pembayaran Anda telah kami terima.
                                @endif
                                Pesanan sedang kami siapkan untuk pengiriman.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <div
                    class="space-y-6"
                    wire:poll.{{ $this->pollSeconds() }}s="refreshStatus"
                    x-data
                    @snap-pay.window="
                        if (! window.snap) { return; }
                        try {
                            window.snap.pay($event.detail.token, {
                                // QRIS (GoPay Dynamic): paksa QR, jangan deeplink aplikasi.
                                // Diabaikan Snap untuk kanal non-GoPay.
                                gopayMode: 'qr',
                                onSuccess: () => $wire.refreshStatus(),
                                onPending: () => $wire.refreshStatus(),
                                onClose: () => $wire.refreshStatus(),
                                onError: () => $wire.$refresh(),
                            });
                        } catch (e) {
                            window.location.reload();
                        }
                    "
                >
                    <div class="rounded-2xl border border-gold/30 bg-gold/10 p-5 shadow-card sm:p-6">
                        <p class="flex items-center gap-2 text-sm font-semibold text-ink">
                            <span class="size-2 shrink-0 rounded-full bg-gold"></span>
                            {{ $order->statusLabel() }}
                        </p>
                        <p class="mt-4 text-sm text-ink/70">Selesaikan pembayaran sebesar</p>
                        <p class="mt-0.5 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ rupiah($order->grand_total) }}</p>
                    </div>

                    @php $attempt = $this->attempt(); @endphp

                    @if ($attempt && ! $changingMethod)
                        @php $logo = \App\Services\Payments\PaymentMethods::logoUrl($attempt->payment_method); @endphp
                        <div class="rounded-2xl border border-black/[.06] bg-white p-5 shadow-card sm:p-6">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Metode Pembayaran Aktif</h2>

                            <div class="mt-4 flex items-center gap-3.5">
                                @if ($logo)
                                    <span class="flex h-12 w-24 shrink-0 items-center justify-center rounded-lg border border-black/[.08] bg-white px-2.5">
                                        <img src="{{ $logo }}" alt="" class="h-7 w-full object-contain">
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink">{{ \App\Services\Payments\PaymentMethods::name($attempt->payment_method) }}</p>
                                    @if ($attempt->provider === \App\Services\Payments\PaymentMethods::NICEPAY)
                                        <p class="mt-0.5 text-xs text-ink/70">Tenor cicilan dipilih di halaman Indodana.</p>
                                    @endif
                                </div>
                            </div>

                            @if ($attempt->vaNumber() || $attempt->billKey() || $attempt->expired_at)
                                <dl class="mt-4 space-y-3 border-t border-black/[.06] pt-4 text-sm">
                                    @if ($attempt->vaNumber())
                                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                            <dt class="text-ink/65">{{ $attempt->bankLabel() ? $attempt->bankLabel().' Virtual Account' : 'Nomor VA' }}</dt>
                                            <dd>
                                                <button type="button"
                                                        class="-mx-2 -my-2 inline-flex min-h-11 items-center gap-2 rounded-md px-2 font-mono text-base font-bold tracking-wider text-ink transition-colors hover:bg-black/[.05]"
                                                        x-data
                                                        @click="navigator.clipboard?.writeText('{{ $attempt->vaNumber() }}'); $flux?.toast?.('Nomor VA disalin')"
                                                        title="Klik untuk menyalin">
                                                    {{ $attempt->vaNumber() }}
                                                    <flux:icon.clipboard-document class="size-4 text-ink/65" />
                                                </button>
                                            </dd>
                                        </div>
                                    @elseif ($attempt->billKey())
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-ink/65">Kode Biller</dt>
                                            <dd class="font-mono font-bold text-ink">{{ $attempt->billerCode() }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-ink/65">Bill Key</dt>
                                            <dd class="font-mono font-bold text-ink">{{ $attempt->billKey() }}</dd>
                                        </div>
                                    @endif

                                    @if ($attempt->expired_at)
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-ink/65">Berlaku sampai</dt>
                                            <dd class="text-right font-medium {{ $attempt->isExpired() ? 'text-red-700' : 'text-ink' }}">
                                                {{ tanggal_id($attempt->expired_at) }}
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            @endif

                            @if ($attempt->isExpired())
                                <p class="mt-4 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">
                                    Batas waktu pembayaran telah lewat. Silakan ganti metode untuk membuat tagihan baru.
                                </p>
                            @endif

                            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                                @unless ($attempt->isExpired())
                                    <flux:button wire:click="continuePayment" variant="primary" class="w-full">
                                        Lanjutkan Pembayaran
                                    </flux:button>
                                @endunless
                                <flux:button wire:click="startChangeMethod" variant="{{ $attempt->isExpired() ? 'primary' : 'ghost' }}" icon="arrow-path" class="w-full">
                                    Ganti Metode Pembayaran
                                </flux:button>
                            </div>
                        </div>

                    @else
                        <div class="rounded-2xl border border-black/[.06] bg-white p-5 shadow-card sm:p-6">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">
                                    {{ $attempt ? 'Ganti Metode Pembayaran' : 'Pilih Metode Pembayaran' }}
                                </h2>
                                @if ($attempt)
                                    <button type="button" wire:click="cancelChangeMethod"
                                            class="-my-3 -mr-3 inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-ink/70 transition-colors hover:bg-black/[.05] hover:text-ink">Batal</button>
                                @endif
                            </div>

                            @if ($attempt)
                                <p class="mt-2 text-xs text-ink/70">
                                    Memilih metode baru akan membatalkan tagihan {{ \App\Services\Payments\PaymentMethods::name($attempt->payment_method) }} yang lama.
                                </p>
                            @endif

                            <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-3" role="group" aria-label="Metode pembayaran">
                                @foreach ($this->paymentMethods() as $value => $m)
                                    @php $selected = $payment_method === $value; @endphp
                                    <button type="button" wire:click="$set('payment_method', '{{ $value }}')"
                                            aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                            class="relative flex min-w-0 flex-col items-start gap-3 rounded-xl border p-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink
                                                   {{ $selected
                                                        ? 'border-gold bg-gold/10 ring-1 ring-gold'
                                                        : 'border-black/[.08] bg-white hover:border-ink/30' }}">
                                        <span class="absolute right-2 top-2 flex size-5 items-center justify-center rounded-full bg-gold text-ink transition-transform duration-150 {{ $selected ? 'scale-100' : 'scale-0' }}">
                                            <flux:icon.check variant="micro" class="size-3.5" />
                                        </span>

                                        <img src="{{ asset('images/payments/'.$m['logo']) }}" alt="{{ $m['label'] }}"
                                             class="h-7 w-24 max-w-[calc(100%-1.5rem)] object-contain object-left">

                                        <span class="block text-xs font-medium text-ink/70">{{ $m['type'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <flux:button wire:click="pay" variant="primary" class="mt-5 w-full">
                                {{ $attempt ? 'Buat Tagihan Baru & Bayar' : 'Bayar Sekarang' }}
                            </flux:button>

                        </div>
                    @endif
                </div>
            @endif

            <div class="rounded-2xl border border-black/[.06] bg-white shadow-card">
                <div class="border-b border-black/[.06] px-5 py-3.5 sm:px-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Informasi Pengiriman</h2>
                    <p class="mt-0.5 text-xs text-ink/65">Detail penerima dan jasa kirim pesanan ini.</p>
                </div>

                <div class="grid gap-x-6 gap-y-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
                    <div class="sm:col-span-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/65">Penerima</p>
                        <p class="mt-1.5 text-sm font-medium text-ink">{{ $order->customer_name }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/65">Email</p>
                        <p class="mt-1.5 break-words text-sm text-ink/75">{{ $order->customer_email }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/65">Nomor Telepon</p>
                        <p class="mt-1.5 text-sm text-ink/75">{{ $order->customer_phone }}</p>
                    </div>

                    <div class="sm:col-span-2 border-t border-dashed border-black/[.08] pt-5">
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink/65">Alamat Pengiriman</p>
                        @if ($order->shipping_destination_label)
                            <p class="mt-1.5 text-sm font-medium text-ink">{{ $order->shipping_destination_label }}</p>
                        @endif
                        <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $order->shipping_address }}</p>
                    </div>
                </div>
            </div>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-6">
            <div class="overflow-hidden rounded-2xl border border-black/[.06] bg-white shadow-card">
                <div class="border-b border-black/[.06] px-5 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink">Rincian Pesanan</h2>
                    <p class="mt-0.5 text-xs text-ink/65">{{ tanggal_id($order->created_at) }}</p>
                </div>
                <div class="divide-y divide-black/[.06] px-5">
                    @foreach ($order->items as $item)
                        <div class="flex items-start justify-between gap-3 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-ink">{{ $item->product->name ?? 'Produk' }}</p>
                                @if ($item->variant)
                                    <p class="text-xs text-ink/65">Varian: {{ $item->variant->name }}</p>
                                @endif
                                <p class="text-xs text-ink/65">{{ rupiah($item->price) }} × {{ $item->quantity }}</p>
                            </div>
                            <span class="shrink-0 font-semibold text-ink">{{ rupiah($item->total) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-2 border-t border-black/[.06] bg-paper px-5 py-4 text-sm">
                    <div class="flex justify-between"><span class="text-ink/65">Subtotal</span><span class="text-ink/70">{{ rupiah($order->subtotal) }}</span></div>
                    @if ($order->discount_amount > 0)
                        <div class="flex justify-between text-leaf-dark"><span>Diskon{{ $order->voucher ? ' ('.$order->voucher->code.')' : '' }}</span><span>-{{ rupiah($order->discount_amount) }}</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-ink/65">Ongkos Kirim ({{ $order->shippingCourierName() }})</span><span class="text-ink/70">{{ rupiah($order->shipping_cost) }}</span></div>
                    <div class="flex justify-between border-t border-black/[.06] pt-2 text-base font-bold">
                        <span class="text-ink">Total</span><span class="text-leaf-dark">{{ rupiah($order->grand_total) }}</span>
                    </div>
                </div>
            </div>

            <a href="{{ route('products.index') }}" wire:navigate
               class="flex w-full items-center justify-center gap-2 rounded-lg bg-black/[.07] px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-black/[.12]">
                <flux:icon.squares-2x2 class="size-5" /> Lanjut Belanja
            </a>
        </aside>
    </div>
</div>
