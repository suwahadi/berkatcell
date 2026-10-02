<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Nicepay\NicepayClient;
use App\Services\Payments\Nicepay\NicepayPaylaterService;
use App\Services\Payments\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NicepayPaymentController extends Controller
{
    public function redirect(Order $order, NicepayClient $client): Response|RedirectResponse
    {
        $attempt = $order->activePaymentAttempt;

        if (
            ! $attempt instanceof PaymentAttempt
            || $attempt->provider !== PaymentMethods::NICEPAY
            || ! $attempt->isOpen()
            || $attempt->isExpired()
            || blank($attempt->midtrans_transaction_id)
        ) {
            return redirect()->route('checkout.success', ['order' => $order->uuid]);
        }

        return response()
            ->view('payments.nicepay-redirect', [
                'order' => $order,
                'action' => $client->paymentUrl(),
                'fields' => $client->paymentFormFields($attempt),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function callback(Request $request, NicepayPaylaterService $service): RedirectResponse
    {
        $attempt = PaymentAttempt::query()
            ->with('order:id,uuid')
            ->where('provider', PaymentMethods::NICEPAY)
            ->where('midtrans_order_id', (string) $request->input('referenceNo', ''))
            ->first();

        if (! $attempt?->order) {
            return redirect()->route('home');
        }

        rescue(fn () => $service->sync($attempt));

        return redirect()->route('checkout.success', ['order' => $attempt->order->uuid]);
    }
}
