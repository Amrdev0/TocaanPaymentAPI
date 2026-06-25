<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'order_id' => $this->order_id,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'payment_method' => $this->payment_method,
            'gateway_reference' => $this->gateway_reference,
            'gateway_response' => $this->when($this->gateway_response !== null, $this->gateway_response),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
