<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PushController;
use Illuminate\Support\Facades\Route;

// Страница заказа открывается по секретной ссылке и не кэшируется
Route::get('/order/{token}', [OrderController::class, 'show'])->name('order');

// Всё, что работает с сессией: корзина, оформление, push
Route::prefix('api')->group(function () {
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart', [CartController::class, 'update']);
    Route::get('/schedule', [CheckoutController::class, 'schedule']);
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/push/subscribe', [PushController::class, 'subscribe'])->middleware('throttle:20,1');
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe']);
});
