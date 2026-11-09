<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\DeliveryZone;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Orders\SlotService;
use Illuminate\Database\Seeder;

/**
 * Роли, тестовые пользователи (только не-production), каталог, демо-зона и тариф.
 * На production: `php artisan db:seed --class=CatalogSeeder --force` и создание первого админа командой staff:create-admin.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $users = [
            ['admin@example.test', 'Администратор', [Role::Admin]],
            ['seller@example.test', 'Продавец', [Role::Seller]],
            ['courier@example.test', 'Курьер', [Role::Courier]],
            ['seller-courier@example.test', 'Продавец-курьер', [Role::Seller, Role::Courier]],
            ['customer@example.test', 'Покупатель', [Role::Customer]],
        ];
        foreach ($users as [$email, $name, $roles]) {
            $u = User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => 'password-123', 'phone' => '+79990000000', 'pd_consent_at' => now()]);
            $u->syncRoles($roles);
        }

        // Демо-зона [УТОЧНИТЬ реальные зоны и тарифы]: прямоугольник вокруг точки на Базовской
        DeliveryZone::query()->updateOrCreate(['name' => 'Демо: Западное Дегунино и рядом'], [
            'polygon' => [[55.86, 37.47], [55.86, 37.56], [55.90, 37.56], [55.90, 37.47]],
            'price' => 30000, 'min_order' => 100000, 'free_from' => 300000, 'eta_minutes' => 90, 'sort' => 1,
        ]);
        SubscriptionPlan::query()->updateOrCreate(['name' => 'Демо-абонемент'], [
            'description' => '[УТОЧНИТЬ] предмет абонемента: одно пирожное в день', 'price' => 900000, 'period_days' => 30, 'daily_limit' => 1, 'is_active' => true,
        ]);
        app(SlotService::class)->generate(7);
    }
}
