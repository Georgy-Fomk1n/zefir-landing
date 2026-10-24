<?php

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Services\Deploy\Bootstrapper;
use Illuminate\Support\Facades\Artisan;

it('выключенный хук отдаёт 404', function () {
    config(['shop.deploy_hook.enabled' => false, 'shop.deploy_hook.token' => 'secret']);
    $this->postJson('/api/_deploy/run', [], ['X-Deploy-Token' => 'secret'])->assertNotFound();
});

it('проверяет токен и IP, выполняет миграции и кеширование', function () {
    // Команды подменяем: настоящие down и *:cache пишут в общие storage/ и bootstrap/cache/
    // и ломают соседние процессы при pest --parallel (режим обслуживания отдаёт им 503)
    $calls = [];
    Artisan::shouldReceive('call')->andReturnUsing(function (string $cmd) use (&$calls) {
        $calls[] = $cmd;

        return 0;
    });
    config(['shop.deploy_hook.enabled' => true, 'shop.deploy_hook.token' => 'secret', 'shop.deploy_hook.allowed_ips' => []]);
    $this->postJson('/api/_deploy/run', [], ['X-Deploy-Token' => 'wrong'])->assertNotFound();
    expect($calls)->toBe([]);
    $this->postJson('/api/_deploy/run', [], ['X-Deploy-Token' => 'secret'])->assertOk()->assertExactJson(['ok' => true]);
    expect($calls)->toBe(['down', 'migrate', 'config:cache', 'route:cache', 'view:cache', 'event:cache', 'up']);

    config(['shop.deploy_hook.allowed_ips' => ['10.0.0.1']]);
    $this->postJson('/api/_deploy/run', [], ['X-Deploy-Token' => 'secret'])->assertNotFound();
});

it('ограничивает частоту вызовов хука', function () {
    config(['shop.deploy_hook.enabled' => false]);
    foreach (range(1, 3) as $_) {
        $this->postJson('/api/_deploy/run');
    }
    $this->postJson('/api/_deploy/run')->assertTooManyRequests();
});

it('на пустой базе наполняет каталог и создаёт первого администратора', function () {
    config(['shop.bootstrap_admin.email' => 'Owner@Example.test', 'shop.bootstrap_admin.password' => 'very-long-password']);
    expect(app(Bootstrapper::class)->run())->toBe(['seed:catalog', 'bootstrap:admin'])
        ->and(Product::query()->count())->toBe(8)
        ->and(User::query()->where('email', 'owner@example.test')->first()->hasRole(Role::Admin))->toBeTrue()
        // Повторный запуск ничего не делает
        ->and(app(Bootstrapper::class)->run())->toBe([]);
});
