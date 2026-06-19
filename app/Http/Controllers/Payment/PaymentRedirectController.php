<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentRedirectController extends Controller
{
    public function finish(Request $request): RedirectResponse
    {
        $midtransOrderId = (string) $request->query('order_id', '');

        $attempt = PaymentAttempt::query()
            ->with('order:id,uuid')
            ->where('midtrans_order_id', $midtransOrderId)
            ->first();

        if ($attempt?->order) {
            return redirect()->route('checkout.success', ['order' => $attempt->order->uuid]);
        }

        return redirect()->route('home');
    }
}
