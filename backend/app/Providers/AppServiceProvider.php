<?php

namespace App\Providers;

use App\Enums\Role;
use App\Listeners\ShopEventSubscriber;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Geo\DadataGeoSuggest;
use App\Services\Geo\GeoSuggest;
use App\Services\Geo\NullGeoSuggest;
use App\Services\Geo\YandexGeoSuggest;
use App\Services\Payments\FakePaymentProvider;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\TochkaPaymentProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentProvider::class, function ($app) {
            $provider = config('shop.payments.provider');
            if ($provider === 'fake' && $app->environment('production')) {
                throw new RuntimeException('PAYMENT_PROVIDER=fake запрещён на production');
            }

            return $provider === 'tochka' ? new TochkaPaymentProvider : new FakePaymentProvider;
        });

        $this->app->bind(GeoSuggest::class, fn () => match (config('shop.geo.provider')) {
            'dadata' => new DadataGeoSuggest,
            'yandex' => new YandexGeoSuggest,
            default => new NullGeoSuggest,
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'order' => Order::class,
            'subscription' => Subscription::class,
            'user' => User::class,
        ]);

        Event::subscribe(ShopEventSubscriber::class);

        // Права проверяются на бэкенде. Роли можно совмещать (продавец + курьер).
        Gate::define('admin', fn (User $u) => $u->hasRole(Role::Admin));
        Gate::define('sell', fn (User $u) => $u->hasRole(Role::Seller, Role::Admin));
        Gate::define('courier', fn (User $u) => $u->hasRole(Role::Courier));
        Gate::define('view-order', fn (User $u, Order $o) => $o->user_id === $u->id);
        // Курьер видит только назначенные ему доставки (защита от IDOR)
        Gate::define('deliver', fn (User $u, Delivery $d) => $u->hasRole(Role::Courier) && $d->courier_id === $u->id);

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => [
            Limit::perMinute(10)->by('ip:'.$r->ip()),
            Limit::perMinute(5)->by('email:'.mb_strtolower((string) $r->input('email'))),
        ]);
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('geo', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
        RateLimiter::for('deploy', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(300)->by($r->ip()));
    }
}
