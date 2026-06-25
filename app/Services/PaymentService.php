<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Factories\PaymentGatewayFactory;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function process(Order $order, array $data): Payment
    {
        $this->ensureOrderCanBePaid($order);
        $this->ensureAmountMatchesOrder($order, (float) $data['amount']);

        try {
            $gateway = PaymentGatewayFactory::make($data['payment_method']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'payment_method' => ['Unsupported payment method.'],
            ]);
        }

        return DB::transaction(function () use ($gateway, $order, $data): Payment {
            $gatewayResponse = $gateway->process($order, (float) $data['amount']);

            return Payment::query()->create([
                'payment_id' => $this->generatePaymentId(),
                'order_id' => $order->id,
                'amount' => $data['amount'],
                'status' => $gatewayResponse['status'],
                'payment_method' => $data['payment_method'],
                'gateway_reference' => $gatewayResponse['reference'],
                'gateway_response' => $gatewayResponse['raw_response'],
            ]);
        });
    }

    private function ensureOrderCanBePaid(Order $order): void
    {
        $status = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        if ($status !== OrderStatus::Confirmed->value) {
            throw new HttpException(409, 'Payment can only be processed for confirmed orders.');
        }
    }

    private function ensureAmountMatchesOrder(Order $order, float $amount): void
    {
        if (round($amount, 2) !== round((float) $order->total_amount, 2)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must match the order total.'],
            ]);
        }
    }

    private function generatePaymentId(): string
    {
        do {
            $paymentId = 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Payment::query()->where('payment_id', $paymentId)->exists());

        return $paymentId;
    }
}
