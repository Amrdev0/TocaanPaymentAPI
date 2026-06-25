<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGatewayInterface
{
    /**
     * @return array{
     *     success: bool,
     *     status: string,
     *     reference: string|null,
     *     message: string,
     *     raw_response: array<string, mixed>
     * }
     */
    public function process(Order $order, float $amount): array;
}
