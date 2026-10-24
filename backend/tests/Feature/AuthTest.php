<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Auth\Totp;

it('регистрирует покупателя только с согласием на обработку ПДн', function () {
    $this->postJson('/api/auth/register', ['name' => 'Анна', 'email' => 'anna@example.test', 'password' => 'secret-123'])
        ->assertUnprocessable()->assertJsonValidationErrors('consent');

    $res = $this->postJson('/api/auth/register', ['name' => 'Анна', 'email' => 'Anna@Example.test', 'password' => 'secret-123', 'consent' => true])
        ->assertCreated()->assertJsonPath('user.roles', ['customer']);
    expect(User::query()->where('email', 'anna@example.test')->value('pd_consent_at'))->not->toBeNull();

    $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$res->json('token')])->assertOk()->assertJsonPath('data.name', 'Анна');
});

it('отзывает токен при выходе', function () {
    $headers = asCustomer();
    $this->postJson('/api/auth/logout', [], $headers)->assertNoContent();
    $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
});

it('не пускает сотрудника в кабинет покупателя и покупателя — в служебные кабинеты', function () {
    $seller = makeUser(Role::Seller);
    $this->postJson('/api/auth/login', ['email' => $seller->email, 'password' => 'password'])->assertUnprocessable();

    $customer = makeUser(Role::Customer);
    $this->postJson('/api/staff/auth/login', ['email' => $customer->email, 'password' => 'password'])->assertUnprocessable();
    // Токен покупателя не подходит к служебным маршрутам
    $this->getJson('/api/staff/seller/queue', asCustomer($customer))->assertForbidden();
    // Служебный токен не подходит к кабинету покупателя
    $this->getJson('/api/account/orders', asStaff($seller))->assertForbidden();
});

it('проверяет роли на бэкенде', function () {
    $courier = makeUser(Role::Courier);
    $this->getJson('/api/staff/admin/products', asStaff($courier))->assertForbidden();
    $this->getJson('/api/staff/seller/queue', asStaff($courier))->assertForbidden();
    $this->getJson('/api/staff/courier/today', asStaff($courier))->assertOk();

    // Роли совмещаются: продавец-курьер видит обе панели
    $both = makeUser(Role::Seller, Role::Courier);
    $h = asStaff($both);
    $this->getJson('/api/staff/seller/queue', $h)->assertOk();
    $this->getJson('/api/staff/courier/today', $h)->assertOk();
});

it('требует настройку и ввод 2FA для администратора', function () {
    $admin = makeUser(Role::Admin);
    $setup = $this->postJson('/api/staff/auth/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertStatus(202)->assertJsonPath('twoFactor', 'setup');
    $secret = $setup->json('secret');

    $this->postJson('/api/staff/auth/login', ['email' => $admin->email, 'password' => 'password', 'code' => '000000'])->assertUnprocessable();
    $this->postJson('/api/staff/auth/login', ['email' => $admin->email, 'password' => 'password', 'code' => Totp::code($secret)])
        ->assertOk()->assertJsonStructure(['token']);

    expect($admin->fresh()->hasTwoFactor())->toBeTrue();
    $this->postJson('/api/staff/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertStatus(202)->assertJsonPath('twoFactor', 'required');
});

it('выдаёт короткоживущий служебный токен и завершает прошлый сеанс', function () {
    $seller = makeUser(Role::Seller);
    $first = $this->postJson('/api/staff/auth/login', ['email' => $seller->email, 'password' => 'password'])->json('token');
    $this->postJson('/api/staff/auth/login', ['email' => $seller->email, 'password' => 'password'])->assertOk();
    $this->getJson('/api/staff/auth/me', ['Authorization' => "Bearer {$first}"])->assertUnauthorized();

    $token = $seller->tokens()->latest('id')->first();
    expect($token->expires_at->diffInMinutes(now(), true))->toBeLessThanOrEqual(config('shop.tokens.staff_ttl'));
});

it('ограничивает частоту попыток входа', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/auth/login', ['email' => 'x@example.test', 'password' => 'wrong']);
    }
    $this->postJson('/api/auth/login', ['email' => 'x@example.test', 'password' => 'wrong'])->assertTooManyRequests();
});

it('закрывает API от индексации и даёт health', function () {
    $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
