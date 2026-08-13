<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentGatewayWebhookController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    public function xendit(Request $request): Response
    {
        return $this->handle('xendit', $request);
    }

    public function midtrans(Request $request): Response
    {
        return $this->handle('midtrans', $request);
    }

    public function duitku(Request $request): Response
    {
        return $this->handle('duitku', $request);
    }

    protected function handle(string $gateway, Request $request): Response
    {
        try {
            $this->gateways->handleWebhook($gateway, $request);
        } catch (InvalidArgumentException $e) {
            Log::warning('Payment gateway webhook rejected', [
                'gateway' => $gateway,
                'message' => $e->getMessage(),
            ]);

            return response($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Log::error('Payment gateway webhook failed', [
                'gateway' => $gateway,
                'message' => $e->getMessage(),
            ]);

            return response('Webhook processing failed.', 500);
        }

        return response('OK', 200);
    }
}
