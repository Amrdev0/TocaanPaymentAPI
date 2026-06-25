<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\IndexOrderRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        $orders = $request->user('api')
            ->orders()
            ->with('items')
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create($request->validated(), $request->user('api'));

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => OrderResource::make($order)->resolve($request),
        ], 201);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorizeOrder($order);

        return OrderResource::make($order->load(['items', 'payments']));
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($order);

        $order = $this->orderService->update($order, $request->validated());

        return response()->json([
            'message' => 'Order updated successfully.',
            'data' => OrderResource::make($order)->resolve($request),
        ]);
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorizeOrder($order);

        $this->orderService->delete($order);

        return response()->json([
            'message' => 'Order deleted successfully.',
        ]);
    }

    private function authorizeOrder(Order $order): void
    {
        abort_if($order->user_id !== auth('api')->id(), Response::HTTP_NOT_FOUND);
    }
}
