<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_payments_with_pagination(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->count(3)->create(['order_id' => $order->id]);
        Payment::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/payments?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_user_can_filter_payments_by_order(): void
    {
        $user = User::factory()->create();
        $firstOrder = Order::factory()->create(['user_id' => $user->id]);
        $secondOrder = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $firstOrder->id]);
        Payment::factory()->create(['order_id' => $secondOrder->id]);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson("/api/payments?order_id={$secondOrder->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_id', $secondOrder->id);
    }

    public function test_user_can_filter_payments_by_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::Successful->value,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::Failed->value,
        ]);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/payments?status='.PaymentStatus::Failed->value);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', PaymentStatus::Failed->value);
    }

    public function test_user_can_filter_payments_by_method(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::CreditCard->value,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::Paypal->value,
        ]);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/payments?payment_method='.PaymentMethod::Paypal->value);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.payment_method', PaymentMethod::Paypal->value);
    }

    public function test_user_can_list_payments_for_specific_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->count(3)->create(['order_id' => $order->id]);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson("/api/orders/{$order->id}/payments?per_page=2");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_user_cannot_list_payments_for_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $otherUser->id]);
        Payment::factory()->create(['order_id' => $order->id]);

        $response = $this->withToken($this->tokenFor($user))
            ->getJson("/api/orders/{$order->id}/payments");

        $response->assertNotFound();
    }

    public function test_user_can_process_payment_for_confirmed_order(): void
    {
        $user = User::factory()->create();
        $order = $this->confirmedOrderFor($user, 300);

        $response = $this->withToken($this->tokenFor($user))->postJson("/api/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::CreditCard->value,
            'amount' => 300,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Payment processed successfully.')
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.amount', '300.00')
            ->assertJsonPath('data.status', PaymentStatus::Successful->value)
            ->assertJsonPath('data.payment_method', PaymentMethod::CreditCard->value);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 300,
            'status' => PaymentStatus::Successful->value,
            'payment_method' => PaymentMethod::CreditCard->value,
        ]);
    }

    public function test_payment_gateway_response_is_stored_after_processing(): void
    {
        $user = User::factory()->create();
        $order = $this->confirmedOrderFor($user, 300);

        $response = $this->withToken($this->tokenFor($user))->postJson("/api/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Paypal->value,
            'amount' => 300,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.gateway_response.provider', PaymentMethod::Paypal->value)
            ->assertJsonPath('data.gateway_response.simulated', true);

        $this->assertStringStartsWith('PP-', $response->json('data.gateway_reference'));
    }

    public function test_cannot_process_payment_for_pending_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending->value,
            'total_amount' => 300,
        ]);

        $response = $this->withToken($this->tokenFor($user))->postJson("/api/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::CreditCard->value,
            'amount' => 300,
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payment can only be processed for confirmed orders.');
    }

    public function test_cannot_process_payment_for_cancelled_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled->value,
            'total_amount' => 300,
        ]);

        $response = $this->withToken($this->tokenFor($user))->postJson("/api/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::CreditCard->value,
            'amount' => 300,
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payment can only be processed for confirmed orders.');
    }

    public function test_cannot_process_payment_with_unsupported_method(): void
    {
        $user = User::factory()->create();
        $order = $this->confirmedOrderFor($user, 300);

        $response = $this->withToken($this->tokenFor($user))->postJson("/api/orders/{$order->id}/payments", [
            'payment_method' => 'stripe',
            'amount' => 300,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Unsupported payment method.');
    }

    public function test_cannot_process_payment_with_wrong_amount(): void
    {
        $user = User::factory()->create();
        $order = $this->confirmedOrderFor($user, 300);

        $response = $this->withToken($this->tokenFor($user))->postJson("/api/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::CreditCard->value,
            'amount' => 299,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Payment amount must match the order total.');
    }

    public function test_payment_routes_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/payments')->assertUnauthorized();
    }

    private function confirmedOrderFor(User $user, int $total): Order
    {
        return Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Confirmed->value,
            'total_amount' => $total,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return auth('api')->login($user);
    }
}
