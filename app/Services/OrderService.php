<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Order
    {
        return DB::transaction(function () use ($data, $user): Order {
            $items = $data['items'];

            $order = $user->orders()->create([
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'status' => $data['status'] ?? OrderStatus::Pending->value,
                'total_amount' => $this->calculateTotal($items),
            ]);

            $this->createItems($order, $items);

            return $order->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $orderData = collect($data)
                ->only(['customer_name', 'customer_email', 'customer_phone', 'status'])
                ->all();

            if (array_key_exists('items', $data)) {
                $orderData['total_amount'] = $this->calculateTotal($data['items']);
            }

            $order->update($orderData);

            if (array_key_exists('items', $data)) {
                $order->items()->delete();
                $this->createItems($order, $data['items']);
            }

            return $order->refresh()->load('items');
        });
    }

    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            if ($order->payments()->exists()) {
                throw new HttpException(409, 'Cannot delete order because it has associated payments.');
            }

            $order->delete();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function calculateTotal(array $items): float
    {
        return round(array_sum(array_map(
            fn (array $item): float => (int) $item['quantity'] * (float) $item['price'],
            $items
        )), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function createItems(Order $order, array $items): void
    {
        $order->items()->createMany(array_map(function (array $item): array {
            $subtotal = round((int) $item['quantity'] * (float) $item['price'], 2);

            return [
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $subtotal,
            ];
        }, $items));
    }
}
