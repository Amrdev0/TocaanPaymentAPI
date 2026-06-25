<?php

use App\PaymentGateways\CreditCardGateway;
use App\PaymentGateways\PaypalGateway;

return [
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'credit_card'),

    'gateways' => [
        'credit_card' => [
            'class' => CreditCardGateway::class,
            'api_key' => env('CREDIT_CARD_GATEWAY_API_KEY'),
            'secret' => env('CREDIT_CARD_GATEWAY_SECRET'),
        ],

        'paypal' => [
            'class' => PaypalGateway::class,
            'api_key' => env('PAYPAL_GATEWAY_API_KEY'),
            'secret' => env('PAYPAL_GATEWAY_SECRET'),
        ],
    ],
];
