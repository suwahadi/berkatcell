<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Services\Payments\Midtrans\MidtransWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransWebhookService $service): Response
    {
        try {
            $service->handle($request->all());
        } catch (InvalidWebhookSignatureException) {
            return response('Invalid signature', 403);
        }

        return response('OK', 200);
    }
}
