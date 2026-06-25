<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Factories\PaymentGatewayFactory;
use App\Models\Order;
use App\PaymentGateways\CreditCardGateway;
use App\PaymentGateways\PaypalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_card_gateway_returns_standardized_successful_response(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Confirmed->value,
            'total_amount' => 300,
        ]);

        $response = app(CreditCardGateway::class)->process($order, 300);

        $this->assertTrue($response['success']);
        $this->assertSame('successful', $response['status']);
        $this->assertStringStartsWith('CC-', $response['reference']);
        $this->assertSame('credit_card', $response['raw_response']['provider']);
        $this->assertTrue($response['raw_response']['simulated']);
    }

    public function test_paypal_gateway_returns_standardized_successful_response(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Confirmed->value,
            'total_amount' => 300,
        ]);

        $response = app(PaypalGateway::class)->process($order, 300);

        $this->assertTrue($response['success']);
        $this->assertSame('successful', $response['status']);
        $this->assertStringStartsWith('PP-', $response['reference']);
        $this->assertSame('paypal', $response['raw_response']['provider']);
        $this->assertTrue($response['raw_response']['simulated']);
    }

    public function test_factory_returns_credit_card_gateway(): void
    {
        $this->assertInstanceOf(CreditCardGateway::class, PaymentGatewayFactory::make('credit_card'));
    }

    public function test_factory_returns_paypal_gateway(): void
    {
        $this->assertInstanceOf(PaypalGateway::class, PaymentGatewayFactory::make('paypal'));
    }

    public function test_factory_rejects_unsupported_payment_method(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported payment method.');

        PaymentGatewayFactory::make('stripe');
    }
}
