<?php

namespace App\Factories;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public static function make(string $paymentMethod): PaymentGatewayInterface
    {
        $gatewayClass = config("payment.gateways.{$paymentMethod}.class");

        if (
            ! is_string($gatewayClass)
            || ! is_subclass_of($gatewayClass, PaymentGatewayInterface::class)
        ) {
            throw new InvalidArgumentException('Unsupported payment method.');
        }

        return app($gatewayClass);
    }
}
