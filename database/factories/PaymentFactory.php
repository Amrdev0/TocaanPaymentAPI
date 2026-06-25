<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'order_id' => Order::factory(),
            'amount' => 100,
            'status' => PaymentStatus::Successful->value,
            'payment_method' => PaymentMethod::CreditCard->value,
            'gateway_reference' => 'CC-'.fake()->numerify('######'),
            'gateway_response' => [
                'provider' => PaymentMethod::CreditCard->value,
                'simulated' => true,
            ],
        ];
    }
}
