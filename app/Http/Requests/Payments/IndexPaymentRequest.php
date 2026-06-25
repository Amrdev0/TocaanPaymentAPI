<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPaymentRequest extends FormRequest
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
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'status' => ['nullable', Rule::in(PaymentStatus::values())],
            'payment_method' => ['nullable', Rule::in(PaymentMethod::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
