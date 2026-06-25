<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12);
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->string('payment_method');
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
