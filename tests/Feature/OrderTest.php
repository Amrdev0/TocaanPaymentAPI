<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_order_with_calculated_totals(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->postJson('/api/orders', [
            'customer_name' => 'Amr Ahmed',
            'customer_email' => 'amr@example.com',
            'customer_phone' => '01000000000',
            'status' => OrderStatus::Pending->value,
            'total_amount' => 1,
            'items' => [
                ['product_name' => 'Keyboard', 'quantity' => 1, 'price' => 200],
                ['product_name' => 'Mouse', 'quantity' => 2, 'price' => 50],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Order created successfully.')
            ->assertJsonPath('data.total_amount', '300.00')
            ->assertJsonPath('data.items.0.subtotal', '200.00')
            ->assertJsonPath('data.items.1.subtotal', '100.00');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_amount' => 300,
        ]);
    }

    public function test_order_creation_requires_items(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->postJson('/api/orders', [
            'customer_name' => 'Amr Ahmed',
            'customer_email' => 'amr@example.com',
            'items' => [],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_order_creation_rejects_invalid_quantity(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->postJson('/api/orders', [
            'customer_name' => 'Amr Ahmed',
            'customer_email' => 'amr@example.com',
            'items' => [
                ['product_name' => 'Keyboard', 'quantity' => 0, 'price' => 200],
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_order_creation_rejects_invalid_price(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->postJson('/api/orders', [
            'customer_name' => 'Amr Ahmed',
            'customer_email' => 'amr@example.com',
            'items' => [
                ['product_name' => 'Keyboard', 'quantity' => 1, 'price' => 0],
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.price']);
    }

    public function test_user_can_list_orders_with_pagination(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(3)->create(['user_id' => $user->id]);
        Order::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/orders?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    public function test_user_can_filter_orders_by_status(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending->value,
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Confirmed->value,
        ]);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/orders?status='.OrderStatus::Confirmed->value);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', OrderStatus::Confirmed->value);
    }

    public function test_user_can_view_single_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Keyboard',
            'quantity' => 1,
            'price' => 200,
            'subtotal' => 200,
        ]);

        $response = $this->withToken($this->tokenFor($user))->getJson("/api/orders/{$order->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.items.0.product_name', 'Keyboard');
    }

    public function test_user_can_update_order_and_recalculate_items(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 300,
        ]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        $response = $this->withToken($this->tokenFor($user))->putJson("/api/orders/{$order->id}", [
            'customer_name' => 'Amr Ahmed Updated',
            'customer_email' => 'amr.updated@example.com',
            'customer_phone' => '01111111111',
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_name' => 'Monitor', 'quantity' => 1, 'price' => 3000],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Order updated successfully.')
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('data.total_amount', '3000.00')
            ->assertJsonPath('data.items.0.subtotal', '3000.00');

        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_user_can_delete_order_without_payments(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $response = $this->withToken($this->tokenFor($user))->deleteJson("/api/orders/{$order->id}");

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Order deleted successfully.');

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
        ]);
    }

    public function test_user_cannot_delete_order_with_associated_payments(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create([
            'payment_id' => 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'status' => PaymentStatus::Successful->value,
            'payment_method' => PaymentMethod::CreditCard->value,
        ]);

        $response = $this->withToken($this->tokenFor($user))->deleteJson("/api/orders/{$order->id}");

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cannot delete order because it has associated payments.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
        ]);
    }

    public function test_order_routes_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    private function tokenFor(User $user): string
    {
        return auth('api')->login($user);
    }
}
