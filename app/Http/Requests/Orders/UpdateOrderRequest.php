<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_email' => ['sometimes', 'required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'required', Rule::in(OrderStatus::values())],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0.01'],
        ];
    }
}
