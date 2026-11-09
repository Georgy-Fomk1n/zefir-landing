<?php

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => seedCatalog());

it('обновляет профиль и согласие на рассылку', function () {
    $user = makeUser();
    $this->putJson('/api/account/profile', ['name' => 'Новое Имя', 'phone' => '+79990001122', 'marketingConsent' => true], asCustomer($user))
        ->assertOk()->assertJsonPath('data.name', 'Новое Имя')->assertJsonPath('data.marketingConsent', true);
    $this->putJson('/api/account/profile', ['name' => 'X', 'phone' => 'abc'], asCustomer($user))->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);
});

it('меняет пароль и завершает остальные сеансы', function () {
    $user = makeUser();
    $other = asCustomer($user);
    $current = asCustomer($user);
    $this->putJson('/api/account/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'], $current)
        ->assertUnprocessable();
    $this->putJson('/api/account/password', ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'], $current)
        ->assertNoContent();
    $this->getJson('/api/auth/me', $other)->assertUnauthorized();
    $this->getJson('/api/auth/me', $current)->assertOk();
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'new-password-1'])->assertOk();
});

it('удаляет аккаунт с обезличиванием ПДн, отменой абонементов и удалением карт', function () {
    $user = makeUser();
    $h = asCustomer($user);
    Order::factory()->create(['user_id' => $user->id, 'address' => ['text' => 'ул. Тестовая']]);
    $plan = SubscriptionPlan::create(['name' => 'П', 'description' => 'д', 'price' => 100000, 'period_days' => 30, 'daily_limit' => 1]);
    Subscription::create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'auto_renew' => true]);
    PaymentMethod::create(['user_id' => $user->id, 'provider' => 'fake', 'token' => 't', 'title' => 'Карта']);
    Review::create(['product_id' => Product::query()->first()->id, 'user_id' => $user->id, 'author_name' => $user->name, 'rating' => 5, 'text' => 'Очень вкусно']);

    $this->postJson('/api/account/delete', ['password' => 'wrong'], $h)->assertUnprocessable();
    $this->postJson('/api/account/delete', ['password' => 'password'], $h)->assertNoContent();

    $user->refresh();
    $order = Order::query()->first();
    expect($user->name)->toBe('Удалённый пользователь')
        ->and($user->email)->toEndWith('@invalid')
        ->and($user->phone)->toBeNull()
        ->and($user->anonymized_at)->not->toBeNull()
        ->and($user->is_active)->toBeFalse()
        ->and($order->contact_name)->toBe('Удалён')->and($order->address)->toBeNull()
        ->and(Subscription::query()->first()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(PaymentMethod::query()->count())->toBe(0)
        ->and(Review::query()->first()->author_name)->toBe('Покупатель')
        ->and($user->tokens()->count())->toBe(0);
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
});

it('ведёт избранное', function () {
    $h = asCustomer();
    $kofe = Product::query()->where('slug', 'kofe')->first();
    $this->postJson("/api/account/favorites/{$kofe->id}", [], $h)->assertNoContent();
    $this->postJson("/api/account/favorites/{$kofe->id}", [], $h)->assertNoContent(); // повтор без дубля
    $this->getJson('/api/account/favorites', $h)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'kofe');
    $this->deleteJson("/api/account/favorites/{$kofe->id}", [], $h)->assertNoContent();
    $this->getJson('/api/account/favorites', $h)->assertJsonCount(0, 'data');
});

it('хранит серверную корзину: слияние берёт максимум, замена отбрасывает несуществующие варианты', function () {
    $h = asCustomer();
    $kofe = ProductVariant::query()->where('sku', 'ZP-KOFE-1')->first();
    $banan = ProductVariant::query()->where('sku', 'ZP-PP-BANAN-1')->first();
    $this->putJson('/api/cart', ['lines' => [['variantId' => $kofe->id, 'qty' => 3], ['variantId' => 99999, 'qty' => 1]]], $h)
        ->assertOk()->assertJsonCount(1, 'data');
    $this->postJson('/api/cart/merge', ['lines' => [['variantId' => $kofe->id, 'qty' => 1], ['variantId' => $banan->id, 'qty' => 2], ['variantId' => 99999, 'qty' => 1]]], $h)
        ->assertOk()->assertJsonCount(2, 'data');
    $lines = collect($this->getJson('/api/cart', $h)->json('data'))->pluck('qty', 'variantId');
    expect($lines[$kofe->id])->toBe(3)->and($lines[$banan->id])->toBe(2);
    $this->postJson('/api/cart/merge', ['lines' => [['variantId' => $kofe->id, 'qty' => 0]]], $h)->assertUnprocessable();
});

it('показывает и отвязывает сохранённые карты только своему владельцу', function () {
    $user = makeUser();
    $plan = SubscriptionPlan::create(['name' => 'П', 'description' => 'д', 'price' => 100000, 'period_days' => 30, 'daily_limit' => 1]);
    $card = PaymentMethod::create(['user_id' => $user->id, 'provider' => 'fake', 'token' => 'secret-token', 'title' => 'Карта •• 4242']);
    $sub = Subscription::create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'payment_method_id' => $card->id]);

    $this->getJson('/api/account/payment-methods', asCustomer($user))->assertOk()
        ->assertJsonPath('data.0.title', 'Карта •• 4242')->assertDontSee('secret-token');
    $this->deleteJson("/api/account/payment-methods/{$card->id}", [], asCustomer())->assertNotFound();
    $this->deleteJson("/api/account/payment-methods/{$card->id}", [], asCustomer($user))->assertNoContent();
    expect($sub->refresh()->payment_method_id)->toBeNull();
    $this->getJson('/api/account/subscriptions', asCustomer($user))->assertJsonPath('data.0.renewalMode', 'link');
});

it('показывает тарифы абонементов и не даёт купить неактивный', function () {
    SubscriptionPlan::create(['name' => 'Активный', 'description' => 'д', 'price' => 100000, 'period_days' => 30, 'daily_limit' => 1]);
    $off = SubscriptionPlan::create(['name' => 'Архив', 'description' => 'д', 'price' => 100000, 'period_days' => 30, 'daily_limit' => 1, 'is_active' => false]);
    $this->getJson('/api/subscription-plans')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Активный');
    $this->postJson('/api/account/subscriptions', ['planId' => $off->id], asCustomer())->assertUnprocessable();
});

it('не отменяет чужой или неактивный абонемент', function () {
    $user = makeUser();
    $plan = SubscriptionPlan::create(['name' => 'П', 'description' => 'д', 'price' => 100000, 'period_days' => 30, 'daily_limit' => 1]);
    $sub = Subscription::create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => SubscriptionStatus::Expired]);
    $this->postJson("/api/account/subscriptions/{$sub->id}/cancel", [], asCustomer($user))->assertUnprocessable();
});

it('показывает заказы покупателя с признаком возможности отзыва', function () {
    $user = makeUser();
    $variant = ProductVariant::query()->first();
    $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'payment_status' => PaymentStatus::Paid]);
    $order->items()->create(['product_variant_id' => $variant->id, 'product_id' => $variant->product_id, 'product_name' => 'Кофе', 'variant_name' => '1 шт.', 'sku' => $variant->sku, 'price' => 45000, 'qty' => 1]);
    Order::factory()->create(['user_id' => makeUser()->id]);
    $this->getJson('/api/account/orders', asCustomer($user))->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.items.0.reviewable', true);
    // Товара нет в заказе
    $this->postJson("/api/account/orders/{$order->id}/reviews", ['productId' => 99999, 'rating' => 5, 'text' => 'Очень вкусно!!'], asCustomer($user))
        ->assertUnprocessable()->assertJsonValidationErrors('productId');
});

it('сотрудник с ролью покупателя может войти и в кабинет', function () {
    $both = makeUser(Role::Seller, Role::Customer);
    $this->postJson('/api/auth/login', ['email' => $both->email, 'password' => 'password'])->assertOk();
    expect(DB::table('user_roles')->where('user_id', $both->id)->count())->toBe(2)
        ->and(User::query()->find($both->id)->isStaff())->toBeTrue();
});
