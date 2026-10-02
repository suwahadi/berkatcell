<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Services\Payments\Nicepay\NicepayPaylaterService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NicepayNotificationController extends Controller
{
    public function __invoke(Request $request, NicepayPaylaterService $service): Response
    {
        try {
            $processed = $service->handleNotification($request->all());
        } catch (InvalidWebhookSignatureException) {
            return response('Invalid token', 403);
        }

        return $processed ? response('OK', 200) : response('Retry later', 503);
    }
}
