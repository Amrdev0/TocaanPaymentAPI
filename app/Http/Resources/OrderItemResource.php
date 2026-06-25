<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'price' => number_format((float) $this->price, 2, '.', ''),
            'subtotal' => number_format((float) $this->subtotal, 2, '.', ''),
        ];
    }
}
