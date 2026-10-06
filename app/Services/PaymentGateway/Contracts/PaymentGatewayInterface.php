<?php

namespace App\Services\PaymentGateway\Contracts;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    public function isEnabled(): bool;

    /**
     * @return array{ok: bool, message: string, latency_ms?: int}
     */
    public function testConnection(): array;

    /**
     * @return array{checkout_url: string, gateway_reference: ?string, raw_request: array, raw_response: array}
     */
    public function createCheckout(Invoice $invoice, PaymentTransaction $transaction, string $successUrl, string $failureUrl): array;

    /**
     * @return array{external_id: string, status: string, paid_at: ?\Carbon\Carbon, gateway_reference: ?string, raw: array}
     */
    public function parseWebhook(Request $request): array;
}
