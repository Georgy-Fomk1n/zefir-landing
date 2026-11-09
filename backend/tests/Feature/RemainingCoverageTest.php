<?php

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\PushSubscription;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\Channels\PushChannel;
use App\Notifications\SubscriptionRenewalLink;
use App\Services\Delivery\CdekProvider;
use App\Services\Delivery\OwnCourierProvider;
use App\Services\Delivery\PickupProvider;
use App\Services\Delivery\RussianPostProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

it('даёт русские подписи всем статусам и список служебных ролей', function () {
    foreach (OrderStatus::cases() as $s) {
        expect($s->label())->not->toBe('');
    }
    foreach (DeliveryStatus::cases() as $s) {
        expect($s->label())->not->toBe('');
    }
    expect(OrderStatus::sellerManaged())->toContain(OrderStatus::Ready)
        ->and(Role::staff())->toBe([Role::Seller, Role::Courier, Role::Admin])
        ->and(Role::Admin->isStaff())->toBeTrue()->and(Role::Customer->isStaff())->toBeFalse();
});

it('связи моделей каталога и подписок', function () {
    seedCatalog();
    $cat = Category::query()->where('slug', 'vkusy')->first();
    $kofe = Product::query()->where('slug', 'kofe')->first();
    $user = makeUser();
    $sub = PushSubscription::create(['user_id' => $user->id, 'scope' => 'customer', 'endpoint' => 'https://p.test/1', 'endpoint_hash' => 'h', 'public_key' => 'k', 'auth_token' => 'a']);
    expect($cat->products()->count())->toBe(4)
        ->and($kofe->media()->count())->toBe(1)
        ->and($kofe->reviews()->count())->toBe(0)
        ->and($sub->user->id)->toBe($user->id)
        ->and($sub->toArray())->not->toHaveKey('auth_token');
});

it('коды и трекинг всех способов доставки', function () {
    $order = Order::factory()->create(['fulfilment' => 'courier']);
    $order->delivery()->create(['status' => DeliveryStatus::Assigned]);
    $d = Delivery::query()->first();
    $d->history()->create(['to' => 'assigned', 'comment' => 'Назначен', 'happened_at' => now()]);
    $own = new OwnCourierProvider;
    expect([(new PickupProvider)->code(), $own->code(), (new CdekProvider)->code(), (new RussianPostProvider)->code()])
        ->toBe(['pickup', 'courier', 'cdek', 'russian_post'])
        ->and($own->createShipment($order))->toBeNull()
        ->and($own->track($order->refresh()))->toHaveCount(1)
        ->and($own->quote(['text' => 'без координат'], 100000)->available)->toBeFalse()
        ->and($own->quote(['lat' => 1.0, 'lng' => 1.0], 100000)->message)->toContain('вне зоны');
});

it('уведомление о продлении абонемента: письмо, текст и push', function () {
    $plan = SubscriptionPlan::create(['name' => 'Пирожное в день', 'description' => 'д', 'price' => 300000, 'period_days' => 30, 'daily_limit' => 1]);
    $user = makeUser();
    $sub = Subscription::create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'current_period_end' => now()->addDays(3)]);
    $n = new SubscriptionRenewalLink($sub, 'https://pay.test/1');
    expect($n->via($user))->toContain(PushChannel::class)
        ->and($n->toText($user))->toContain('https://pay.test/1')
        ->and($n->toMail($user)->actionUrl)->toBe('https://pay.test/1')
        ->and($n->toPush($user))->url->toBe('https://pay.test/1')->scope->toBe('customer');
});

it('служебный профиль и выход', function () {
    $seller = makeUser(Role::Seller);
    $h = asStaff($seller);
    $this->getJson('/api/staff/auth/me', $h)->assertOk()->assertJsonPath('data.roles', ['seller']);
    $this->postJson('/api/staff/auth/logout', [], $h)->assertNoContent();
    $this->getJson('/api/staff/auth/me', $h)->assertUnauthorized();
    // Неверный пароль и неверный 2FA-код
    $this->postJson('/api/staff/auth/login', ['email' => $seller->email, 'password' => 'wrong'])->assertUnprocessable();
    $admin = makeUser(Role::Admin);
    $admin->forceFill(['two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'two_factor_confirmed_at' => now()])->save();
    $this->postJson('/api/staff/auth/login', ['email' => $admin->email, 'password' => 'password', 'code' => '000000'])->assertUnprocessable();
});

it('деплой-хук сообщает об ошибке шага без деталей и всё равно поднимает сайт', function () {
    config(['shop.deploy_hook.enabled' => true, 'shop.deploy_hook.token' => 'secret', 'shop.deploy_hook.allowed_ips' => []]);
    Artisan::shouldReceive('call')->with('down', Mockery::any())->once();
    Artisan::shouldReceive('call')->with('migrate', Mockery::any())->andThrow(new RuntimeException('SQLSTATE secret details'));
    Artisan::shouldReceive('call')->with('up')->once();
    Log::spy();
    $this->postJson('/api/_deploy/run', [], ['X-Deploy-Token' => 'secret'])->assertStatus(500)->assertExactJson(['ok' => false]);
    Log::shouldHaveReceived('error')->withArgs(fn ($m, $ctx) => $m === 'deploy hook failed' && $ctx['step'] === 'down');
});
