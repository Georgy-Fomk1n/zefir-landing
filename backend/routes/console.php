<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Catalog\IndexNow;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Orders\OrderLifecycle;
use App\Services\Orders\SlotService;
use App\Services\Payments\PaymentService;
use App\Services\Push\WebPush;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

// Хостинг без постоянных процессов: cron раз в минуту запускает schedule:run (docs/hosting.md).

Artisan::command('orders:release-expired', function (OrderLifecycle $lifecycle) {
    $this->info('Снято резервов: '.$lifecycle->releaseExpired());
})->purpose('Отменить неоплаченные заказы с истёкшим резервом');

Artisan::command('slots:generate {--days=}', function (SlotService $slots) {
    $this->info('Создано слотов: '.$slots->generate($this->option('days') ? (int) $this->option('days') : null));
})->purpose('Создать слоты заказов ко времени и интервалы доставки');

Artisan::command('payments:reconcile', function (PaymentService $payments) {
    $this->info('Сверено платежей: '.$payments->reconcile());
})->purpose('Сверить статусы зависших платежей с провайдером');

Artisan::command('subscriptions:process', function (SubscriptionService $subs) {
    $this->info(json_encode($subs->process(), JSON_UNESCAPED_UNICODE));
})->purpose('Продления, повторы, льготный период и истечение абонементов');

Artisan::command('loyalty:expire', function (LoyaltyService $loyalty) {
    $total = 0;
    User::query()->whereHas('loyaltyTransactions', fn ($q) => $q->where('expires_at', '<', now()))->chunkById(200, function ($users) use ($loyalty, &$total) {
        foreach ($users as $u) {
            $total += $loyalty->expire($u);
        }
    });
    $this->info("Сгорело баллов: {$total}");
})->purpose('Списать просроченные баллы');

Artisan::command('seo:indexnow {paths*}', function (IndexNow $indexNow) {
    $this->info($indexNow->submit($this->argument('paths')) ? 'IndexNow: отправлено' : 'IndexNow: выключен или отклонён');
})->purpose('Отправить URL в IndexNow вручную');

Schedule::command('orders:release-expired')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyTenMinutes()->withoutOverlapping();
Schedule::command('subscriptions:process')->hourly()->withoutOverlapping();
Schedule::command('slots:generate')->dailyAt('03:10')->timezone('Europe/Moscow');
Schedule::command('loyalty:expire')->dailyAt('03:30')->timezone('Europe/Moscow');
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Artisan::command('staff:create-admin {email} {name}', function () {
    $password = Str::password(16);
    $user = User::query()->firstOrCreate(['email' => mb_strtolower($this->argument('email'))], ['name' => $this->argument('name'), 'password' => $password]);
    $user->addRole(Role::Admin);
    $this->info($user->wasRecentlyCreated ? "Создан администратор, временный пароль: {$password}" : 'Пользователю выдана роль admin');
    $this->line('При первом входе на штат.зефир-и-пряник.рф потребуется настроить 2FA.');
})->purpose('Создать первого администратора (без SSH — через деплой-хук не вызывается)');

Artisan::command('api:openapi {--output=../docs/openapi.json}', function () {
    // Справочник API из реальных маршрутов: путь, метод, авторизация, роли, правила валидации FormRequest.
    // Схемы ответов описаны типами packages/shared/src/types.ts (одни и те же для обоих фронтендов).
    $paths = [];
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }
        $mw = $route->gatherMiddleware();
        $auth = in_array('auth:sanctum', $mw, true);
        $ability = collect($mw)->first(fn ($m) => str_starts_with($m, 'abilities:'));
        $roles = collect($mw)->first(fn ($m) => str_starts_with($m, 'role:'));
        $path = '/'.preg_replace('/\{(\w+)\??\}/', '{$1}', $route->uri());
        $tag = match (true) {
            str_starts_with($path, '/api/staff/admin') => 'Админ',
            str_starts_with($path, '/api/staff/seller') => 'Продавец',
            str_starts_with($path, '/api/staff/courier') => 'Курьер',
            str_starts_with($path, '/api/staff') => 'Сотрудники: вход',
            str_starts_with($path, '/api/account'), str_starts_with($path, '/api/cart') && $auth => 'Кабинет покупателя',
            str_starts_with($path, '/api/auth') => 'Покупатель: вход',
            default => 'Публичное API',
        };
        $rules = [];
        $action = $route->getActionName();
        if (str_contains($action, '@')) {
            [$class, $method] = explode('@', $action);
            foreach ((new ReflectionMethod($class, $method))->getParameters() as $param) {
                $type = $param->getType()?->getName();
                if ($type && is_subclass_of($type, FormRequest::class)) {
                    $rules = collect((new $type)->rules())->map(fn ($r) => is_array($r) ? implode('|', array_map(fn ($x) => is_string($x) ? $x : class_basename($x), $r)) : $r)->all();
                }
            }
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $httpMethod) {
            $op = [
                'tags' => [$tag],
                'operationId' => strtolower($httpMethod).str_replace(['/', '{', '}', '-', '.'], ['_', '', '', '_', '_'], substr($path, 4)),
                'summary' => class_basename(str_replace('@', '::', $action)),
                'responses' => ['200' => ['description' => 'OK']],
            ];
            if ($auth) {
                $op['security'] = [['bearer' => []]];
                $op['description'] = trim(($ability ? 'Токен: '.substr($ability, 10).'. ' : '').($roles ? 'Роли: '.substr($roles, 5).'.' : ''));
            }
            preg_match_all('/\{(\w+)\}/', $path, $m);
            if ($m[1]) {
                $op['parameters'] = array_map(fn ($n) => ['name' => $n, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], $m[1]);
            }
            if ($rules) {
                $op['requestBody'] = ['content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'properties' => collect($rules)->mapWithKeys(fn ($r, $k) => [$k => ['type' => 'string', 'description' => $r]])->all(),
                ]]]];
            }
            $paths[$path][strtolower($httpMethod)] = $op;
        }
    }
    ksort($paths);
    $doc = [
        'openapi' => '3.1.0',
        'info' => ['title' => 'Зефир и пряник API', 'version' => '1.0', 'description' => 'Сгенерировано командой php artisan api:openapi. Типы ответов — packages/shared/src/types.ts.'],
        'servers' => [['url' => 'https://api.xn-----mlchgbbqzthe9b5k.xn--p1ai'], ['url' => 'http://localhost:8000']],
        'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Sanctum API token']]],
        'paths' => $paths,
    ];
    $output = $this->option('output');
    file_put_contents(str_starts_with($output, '/') ? $output : base_path($output), json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    $this->info('OpenAPI: '.count($paths).' путей');
})->purpose('Сгенерировать docs/openapi.json из маршрутов');

Artisan::command('push:vapid', function () {
    $keys = WebPush::createVapidKeys();
    $this->line('Добавьте в .env (одинаково на всех серверах окружения, при смене ключей подписки придётся оформить заново):');
    $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
})->purpose('Сгенерировать ключи VAPID для Web Push');
