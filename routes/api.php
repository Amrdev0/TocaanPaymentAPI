<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('orders', OrderController::class);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/orders/{order}/payments', [PaymentController::class, 'orderPayments']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'process']);
});
