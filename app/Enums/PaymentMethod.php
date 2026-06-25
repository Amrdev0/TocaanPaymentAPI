<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CreditCard = 'credit_card';
    case Paypal = 'paypal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
