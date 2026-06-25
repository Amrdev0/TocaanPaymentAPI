<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\IndexPaymentRequest;
use App\Http\Requests\Payments\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(IndexPaymentRequest $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->whereHas(
                'order',
                fn ($query) => $query->where('user_id', $request->user('api')->id)
            )
            ->when(
                $request->filled('order_id'),
                fn ($query) => $query->where('order_id', $request->integer('order_id'))
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->when(
                $request->filled('payment_method'),
                fn ($query) => $query->where('payment_method', $request->string('payment_method')->toString())
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PaymentResource::collection($payments);
    }

    public function orderPayments(IndexPaymentRequest $request, Order $order): AnonymousResourceCollection
    {
        $this->authorizeOrder($order);

        $payments = $order->payments()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PaymentResource::collection($payments);
    }

    public function process(ProcessPaymentRequest $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($order);

        $payment = $this->paymentService->process($order, $request->validated());

        return response()->json([
            'message' => 'Payment processed successfully.',
            'data' => PaymentResource::make($payment)->resolve($request),
        ], 201);
    }

    private function authorizeOrder(Order $order): void
    {
        abort_if($order->user_id !== auth('api')->id(), Response::HTTP_NOT_FOUND);
    }
}
