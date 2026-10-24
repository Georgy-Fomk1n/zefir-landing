<?php

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionRenewalLink;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Payments\FakePaymentProvider;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\PaymentService;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Support\Facades\Notification;

it('журнал баллов неизменяем', function () {
    $user = makeUser();
    $t = LoyaltyTransaction::create(['user_id' => $user->id, 'type' => 'adjust', 'amount' => 10]);
    expect(fn () => $t->update(['amount' => 1000]))->toThrow(LogicException::class);
    expect(fn () => $t->delete())->toThrow(LogicException::class);
});

it('продавец сканирует QR: баланс, начисление и списание в точке, идемпотентно', function () {
    $customer = makeUser();
    LoyaltyTransaction::create(['user_id' => $customer->id, 'type' => 'adjust', 'amount' => 500]);
    $seller = asStaff(makeUser(Role::Seller));
    $token = $this->getJson('/api/account/loyalty', asCustomer($customer))->assertOk()->json('data.qrToken');

    $this->postJson('/api/staff/seller/scan/loyalty', ['token' => $token], $seller)->assertOk()->assertJsonPath('data.balance', 500);
    // 1000 ₽, списать 300 баллов при лимите 30% (300)
    $this->postJson('/api/staff/seller/scan/loyalty', ['token' => $token, 'purchase' => 100000, 'spend' => 300, 'operationId' => 'pos-1'], $seller)
        ->assertOk()->assertJsonPath('data.spent', 300)->assertJsonPath('data.earned', 35)->assertJsonPath('data.balance', 235);
    $this->postJson('/api/staff/seller/scan/loyalty', ['token' => $token, 'purchase' => 100000, 'spend' => 300, 'operationId' => 'pos-1'], $seller)
        ->assertOk()->assertJsonPath('data.duplicate', true)->assertJsonPath('data.balance', 235);
    $this->postJson('/api/staff/seller/scan/loyalty', ['token' => $token, 'purchase' => 10000, 'spend' => 100, 'operationId' => 'pos-2'], $seller)
        ->assertUnprocessable();
    // Подделанный токен
    $this->postJson('/api/staff/seller/scan/loyalty', ['token' => 'x.y'], $seller)->assertUnprocessable();
});

it('сжигает просроченные баллы по FIFO', function () {
    $user = makeUser();
    LoyaltyTransaction::create(['user_id' => $user->id, 'type' => 'earn', 'amount' => 100, 'expires_at' => now()->addDays(10)]);
    LoyaltyTransaction::create(['user_id' => $user->id, 'type' => 'earn', 'amount' => 50, 'expires_at' => now()->addDays(100)]);
    LoyaltyTransaction::create(['user_id' => $user->id, 'type' => 'spend', 'amount' => -30]);
    $this->travel(11)->days();
    $service = app(LoyaltyService::class);
    expect($service->expire($user))->toBe(70)->and($service->balance($user))->toBe(50)->and($service->expire($user))->toBe(0);
});

function buySubscription($test, $user, $plan): Subscription
{
    $test->postJson('/api/account/subscriptions', ['planId' => $plan->id], asCustomer($user))->assertCreated();
    $sub = Subscription::query()->latest('id')->firstOrFail();
    app(PaymentService::class)->apply(Payment::query()->latest('id')->first(), PaymentStatus::Paid, 'card-token', 'Карта •• 4242');

    return $sub->refresh();
}

beforeEach(function () {
    $this->plan = SubscriptionPlan::create(['name' => 'Пирожное в день', 'description' => 'тест', 'price' => 300000, 'period_days' => 30, 'daily_limit' => 1]);
});

it('активирует абонемент после оплаты и сохраняет карту для рекуррентов', function () {
    $user = makeUser();
    $sub = buySubscription($this, $user, $this->plan);
    expect($sub->status)->toBe(SubscriptionStatus::Active)->and($sub->payment_method_id)->not->toBeNull()
        ->and($sub->current_period_end->diffInDays(now(), true))->toBeGreaterThanOrEqual(29.9);
    $this->postJson('/api/account/subscriptions', ['planId' => $this->plan->id], asCustomer($user))->assertUnprocessable();
});

it('погашает абонемент по QR с дневным лимитом', function () {
    $user = makeUser();
    buySubscription($this, $user, $this->plan);
    $seller = asStaff(makeUser(Role::Seller));
    $token = $this->getJson('/api/account/subscriptions', asCustomer($user))->json('data.0.qrToken');
    $this->postJson('/api/staff/seller/scan/subscription', ['token' => $token], $seller)->assertOk()->assertJsonPath('data.usedToday', 1);
    $this->postJson('/api/staff/seller/scan/subscription', ['token' => $token], $seller)->assertUnprocessable();
});

it('продлевает с сохранённой карты, повторяет при отказе, даёт льготный период и истекает', function () {
    $user = makeUser();
    $sub = buySubscription($this, $user, $this->plan);
    $service = app(SubscriptionService::class);
    $sub->paymentMethod->update(['token' => 'fake_decline']);

    $this->travel(29)->days();
    $service->process();
    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::PastDue)->and($sub->retry_count)->toBe(1);

    foreach ([6, 24, 48] as $hours) {
        $this->travel($hours)->hours();
        $service->process();
    }
    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Grace)->and($sub->grace_until)->not->toBeNull();

    // Карта обновлена: следующая оплата по ссылке переводит в active
    app(PaymentService::class)->start($sub, $sub->plan->price, 'Продление', 'https://x/ok', 'https://x/fail', purpose: 'renewal');
    app(PaymentService::class)->apply(Payment::query()->latest('id')->first(), PaymentStatus::Paid);
    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Active)->and($sub->retry_count)->toBe(0);

    $sub->update(['status' => SubscriptionStatus::Grace, 'grace_until' => now()->subMinute()]);
    $service->process();
    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('успешно продлевает и сдвигает период от конца текущего', function () {
    $user = makeUser();
    $sub = buySubscription($this, $user, $this->plan);
    $end = $sub->current_period_end->copy();
    $this->travel(29)->days();
    app(SubscriptionService::class)->process();
    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->current_period_end->timestamp)->toBe($end->copy()->addDays(30)->timestamp);
});

it('в режиме ссылки отправляет напоминание и переводит в льготный период после конца', function () {
    config(['shop.payments.provider' => 'fake']);
    $provider = Mockery::mock(FakePaymentProvider::class)->makePartial();
    $provider->shouldReceive('supportsRecurring')->andReturn(false);
    app()->instance(PaymentProvider::class, $provider);
    app()->forgetInstance(SubscriptionService::class);
    app()->forgetInstance(PaymentService::class);
    Notification::fake();

    $user = makeUser();
    $sub = buySubscription($this, $user, $this->plan);
    expect($sub->payment_method_id)->toBeNull();
    $this->travel(28)->days();
    app(SubscriptionService::class)->process();
    Notification::assertSentTo($user, SubscriptionRenewalLink::class);
    $this->travel(3)->days();
    app(SubscriptionService::class)->process();
    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Grace);
});

it('отменяет автопродление в кабинете, сохраняя оплаченный период', function () {
    $user = makeUser();
    $sub = buySubscription($this, $user, $this->plan);
    $this->postJson("/api/account/subscriptions/{$sub->id}/cancel", [], asCustomer(makeUser()))->assertNotFound();
    $this->postJson("/api/account/subscriptions/{$sub->id}/cancel", [], asCustomer($user))->assertNoContent();
    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Cancelled)->and($sub->status->isUsable())->toBeTrue();
    $this->travel(31)->days();
    app(SubscriptionService::class)->process();
    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Expired);
});
