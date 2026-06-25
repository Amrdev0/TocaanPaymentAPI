<?php

namespace App\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Str;

class CreditCardGateway implements PaymentGatewayInterface
{
    /**
     * @return array<string, mixed>
     */
    public function process(Order $order, float $amount): array
    {
        return [
            'success' => true,
            'status' => PaymentStatus::Successful->value,
            'reference' => 'CC-'.Str::upper(Str::random(8)),
            'message' => 'Payment processed successfully.',
            'raw_response' => [
                'provider' => PaymentMethod::CreditCard->value,
                'simulated' => true,
                'credentials_configured' => $this->hasCredentials(),
                'order_id' => $order->id,
                'amount' => number_format($amount, 2, '.', ''),
            ],
        ];
    }

    private function hasCredentials(): bool
    {
        return filled(config('payment.gateways.credit_card.api_key'))
            && filled(config('payment.gateways.credit_card.secret'));
    }
}
