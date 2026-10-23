<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Catalog\IndexNow;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Orders\OrderLifecycle;
use App\Services\Orders\SlotService;
use App\Services\Payments\PaymentService;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
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
