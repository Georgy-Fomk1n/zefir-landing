<?php

namespace App\Services\Deploy;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;

class Bootstrapper
{
    /**
     * Первичное наполнение без SSH: каталог из seed — только если товаров ещё нет;
     * первый администратор — из BOOTSTRAP_ADMIN_EMAIL/PASSWORD, только если админов ещё нет.
     * После первого входа переменные нужно удалить из .env (docs/hosting.md).
     */
    public function run(): array
    {
        $done = [];
        if (! Product::query()->exists()) {
            (new CatalogSeeder)->run();
            $done[] = 'seed:catalog';
        }
        $email = config('shop.bootstrap_admin.email');
        $password = config('shop.bootstrap_admin.password');
        if ($email && $password && strlen($password) >= 12 && ! DB::table('user_roles')->where('role', Role::Admin->value)->exists()) {
            $user = User::query()->firstOrCreate(['email' => mb_strtolower($email)], ['name' => 'Администратор', 'password' => $password]);
            $user->addRole(Role::Admin);
            $done[] = 'bootstrap:admin';
        }

        return $done;
    }
}
