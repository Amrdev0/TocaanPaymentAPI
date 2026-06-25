# TocaanPaymentAPI

A Laravel 12 REST API for JWT-authenticated order management and simulated payment processing. The payment layer uses a gateway contract plus factory so new gateways can be added without changing controllers.

## Requirements

- PHP 8.2+
- Composer 2+
- MySQL 8 or MariaDB
- XAMPP or another local PHP/MySQL stack

The test suite uses in-memory SQLite through `phpunit.xml`.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve
```

Before running migrations, create the database configured in `.env`:

```env
DB_CONNECTION=mysql
DB_DATABASE=tocaan_payment_api
DB_USERNAME=root
DB_PASSWORD=
```

For a quick local SQLite run, set `DB_CONNECTION=sqlite` and create `database/database.sqlite`.

## Environment Variables

JWT authentication requires:

```env
JWT_SECRET=
JWT_ALGO=HS256
```

Payment gateway simulation reads:

```env
PAYMENT_DEFAULT_GATEWAY=credit_card
CREDIT_CARD_GATEWAY_API_KEY=fake_credit_card_key
CREDIT_CARD_GATEWAY_SECRET=fake_credit_card_secret
PAYPAL_GATEWAY_API_KEY=fake_paypal_key
PAYPAL_GATEWAY_SECRET=fake_paypal_secret
```

## Authentication

Public endpoints:

- `POST /api/register`
- `POST /api/login`

Protected endpoints require:

```http
Authorization: Bearer {jwt_token}
```

Protected auth endpoints:

- `POST /api/logout`
- `GET /api/me`

## API Endpoints

Orders:

- `GET /api/orders`
- `POST /api/orders`
- `GET /api/orders/{order}`
- `PUT /api/orders/{order}`
- `DELETE /api/orders/{order}`

Payments:

- `GET /api/payments`
- `GET /api/orders/{order}/payments`
- `POST /api/orders/{order}/payments`

List endpoints support `per_page`. Orders support `status`. Payments support `order_id`, `status`, and `payment_method`.

## Business Rules

- Order totals are always calculated server-side from submitted items.
- Order item subtotals are always calculated as `quantity * price`.
- Payments can only be processed for confirmed orders.
- Payment amount must match the order total.
- Orders with associated payments cannot be deleted.
- Unsupported payment methods return validation errors.
- Order and payment data is scoped to the authenticated user.

## Payment Gateway Architecture

All gateways implement `App\Contracts\PaymentGatewayInterface`:

```php
public function process(Order $order, float $amount): array;
```

The payment service resolves a gateway through `App\Factories\PaymentGatewayFactory`, then stores the standardized response in `payments.gateway_response`.

Current gateways:

- `App\PaymentGateways\CreditCardGateway`
- `App\PaymentGateways\PaypalGateway`

Gateway configuration lives in `config/payment.php` and reads credentials from `.env`.

## How to Add a New Payment Gateway

1. Add a new value to `App\Enums\PaymentMethod`.
2. Create a gateway class in `app/PaymentGateways`.
3. Implement `PaymentGatewayInterface`.
4. Register the gateway class in `config/payment.php`.
5. Add gateway credentials to `.env.example`.
6. Add unit tests for the gateway and factory resolution.

Controllers do not contain gateway-specific logic.

## Running Tests

```bash
php artisan test
```

Run formatting:

```bash
./vendor/bin/pint
```

## Postman Collection

Import:

```text
docs/postman_collection.json
```

Set collection variables:

- `base_url`: `http://127.0.0.1:8000/api`
- `token`: JWT token returned by register or login

## Assumptions

- Payment processing is simulated and does not call real external gateways.
- The system supports `credit_card` and `paypal`.
- Full payment is required; partial payments are not supported.
- The authenticated user owns the orders they create.
- Customer fields are stored on orders even though each order is linked to a user.
