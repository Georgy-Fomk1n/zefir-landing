<?php

use App\Http\Controllers\Account\AccountOrderController;
use App\Http\Controllers\Account\FavoriteController;
use App\Http\Controllers\Account\LoyaltyController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SubscriptionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DeployHookController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Staff\Admin\AdminController;
use App\Http\Controllers\Staff\Admin\CatalogAdminController;
use App\Http\Controllers\Staff\Courier\CourierController;
use App\Http\Controllers\Staff\DeliveryBoardController;
use App\Http\Controllers\Staff\Seller\SellerController;
use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class);
Route::post('_deploy/run', DeployHookController::class)->middleware('throttle:deploy');

// ── Публичное API магазина ───────────────────────────────────────
Route::prefix('catalog')->group(function () {
    Route::get('export', [CatalogController::class, 'export']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('products/{slug}', [CatalogController::class, 'product']);
});
Route::get('subscription-plans', [SubscriptionController::class, 'plans']);
Route::get('slots', [CheckoutController::class, 'slots']);
Route::get('geo/suggest', [CheckoutController::class, 'suggest'])->middleware('throttle:geo');
Route::post('cart/quote', [CartController::class, 'quote']);
Route::post('orders', [CheckoutController::class, 'place'])->middleware('throttle:checkout');
Route::get('track/{token}', [CheckoutController::class, 'track'])->where('token', '[A-Za-z0-9]{20,64}');

Route::post('webhooks/payments', PaymentWebhookController::class)->middleware('throttle:webhooks');
Route::get('payments/fake/{payment}', [PaymentWebhookController::class, 'fake'])->name('payments.fake');

// ── Покупатель (основной домен) ──────────────────────────────────
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'abilities:customer'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('cart', [CartController::class, 'show']);
    Route::put('cart', [CartController::class, 'replace']);
    Route::post('cart/merge', [CartController::class, 'merge']);

    Route::prefix('account')->group(function () {
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('password', [ProfileController::class, 'password']);
        Route::post('delete', [ProfileController::class, 'destroy']);

        Route::get('orders', [AccountOrderController::class, 'index']);
        Route::get('orders/{order}', [AccountOrderController::class, 'show']);
        Route::post('orders/{order}/reviews', [AccountOrderController::class, 'review']);

        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites/{product}', [FavoriteController::class, 'store']);
        Route::delete('favorites/{product}', [FavoriteController::class, 'destroy']);

        Route::get('loyalty', [LoyaltyController::class, 'show']);

        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('payment-methods', [SubscriptionController::class, 'paymentMethods']);
        Route::delete('payment-methods/{id}', [SubscriptionController::class, 'unlinkCard']);
    });
});

// ── Служебные кабинеты (штат.зефир-и-пряник.рф) ──────────────────
Route::prefix('staff')->group(function () {
    Route::post('auth/login', [StaffAuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'abilities:staff'])->group(function () {
        Route::get('auth/me', [StaffAuthController::class, 'me']);
        Route::post('auth/logout', [StaffAuthController::class, 'logout']);

        Route::middleware('role:seller,admin')->prefix('seller')->group(function () {
            Route::get('queue', [SellerController::class, 'queue']);
            Route::post('orders/{order}/status', [SellerController::class, 'transition']);
            Route::post('orders/{order}/cancel', [SellerController::class, 'cancel']);
            Route::post('scan/loyalty', [SellerController::class, 'loyaltyScan']);
            Route::post('scan/subscription', [SellerController::class, 'subscriptionScan']);
            Route::get('deliveries', [DeliveryBoardController::class, 'board']);
            Route::post('orders/{order}/assign', [DeliveryBoardController::class, 'assign']);
            Route::post('orders/{order}/returned', [DeliveryBoardController::class, 'returned']);
        });

        Route::middleware('role:courier')->prefix('courier')->group(function () {
            Route::get('today', [CourierController::class, 'today']);
            Route::get('deliveries/{delivery}', [CourierController::class, 'show']);
            Route::post('deliveries/{delivery}/status', [CourierController::class, 'transition']);
            Route::post('shift/start', [CourierController::class, 'startShift']);
            Route::post('shift/end', [CourierController::class, 'endShift']);
        });

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('products', [CatalogAdminController::class, 'products']);
            Route::post('products', [CatalogAdminController::class, 'storeProduct']);
            Route::put('products/{product}', [CatalogAdminController::class, 'updateProduct']);
            Route::delete('products/{product}', [CatalogAdminController::class, 'destroyProduct']);
            Route::patch('variants/{variant}', [CatalogAdminController::class, 'updateVariant']);
            Route::get('categories', [CatalogAdminController::class, 'categories']);
            Route::post('categories', [CatalogAdminController::class, 'saveCategory']);
            Route::put('categories/{category}', [CatalogAdminController::class, 'saveCategory']);

            Route::get('promo-codes', [AdminController::class, 'promoCodes']);
            Route::post('promo-codes', [AdminController::class, 'savePromoCode']);
            Route::put('promo-codes/{promoCode}', [AdminController::class, 'savePromoCode']);

            Route::get('users', [AdminController::class, 'users']);
            Route::post('users', [AdminController::class, 'saveUser']);
            Route::put('users/{user}', [AdminController::class, 'saveUser']);
            Route::post('users/{user}/reset-2fa', [AdminController::class, 'resetTwoFactor']);
            Route::get('password-suggestion', [AdminController::class, 'newPassword']);

            Route::get('zones', [AdminController::class, 'zones']);
            Route::post('zones', [AdminController::class, 'saveZone']);
            Route::put('zones/{zone}', [AdminController::class, 'saveZone']);

            Route::get('slots', [AdminController::class, 'slots']);
            Route::patch('slots/{slot}', [AdminController::class, 'updateSlot']);
            Route::post('slots/generate', [AdminController::class, 'generateSlots']);

            Route::get('reviews', [AdminController::class, 'reviews']);
            Route::post('reviews/{review}', [AdminController::class, 'moderateReview']);

            Route::get('plans', [AdminController::class, 'plans']);
            Route::post('plans', [AdminController::class, 'savePlan']);
            Route::put('plans/{plan}', [AdminController::class, 'savePlan']);

            Route::get('seo-pages', [AdminController::class, 'seoPages']);
            Route::post('seo-pages', [AdminController::class, 'saveSeoPage']);

            Route::get('reports/summary', [AdminController::class, 'report']);
            Route::get('reports/orders.csv', [AdminController::class, 'exportOrders']);
        });
    });
});
