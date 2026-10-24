<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Unit');
// Поиск на MySQL идёт через FULLTEXT, который не видит незакоммиченных строк, — там данные коммитятся и чистятся
$searchDb = (getenv('DB_CONNECTION') ?: ($_SERVER['DB_CONNECTION'] ?? 'sqlite')) === 'mysql' ? DatabaseTruncation::class : RefreshDatabase::class;
pest()->extend(TestCase::class)->use($searchDb)->in('Search');

function makeUser(Role ...$roles): User
{
    $user = User::factory()->create();
    $user->syncRoles($roles ?: [Role::Customer]);

    return $user;
}

/** Заголовок с токеном покупателя */
function asCustomer(?User $user = null): array
{
    $user ??= makeUser(Role::Customer);

    return ['Authorization' => 'Bearer '.app(TokenIssuer::class)->customer($user)];
}

/** Заголовок со служебным токеном (без 2FA-процедуры — для тестов прав) */
function asStaff(User $user): array
{
    return ['Authorization' => 'Bearer '.app(TokenIssuer::class)->staff($user)];
}

function seedCatalog(): void
{
    (new CatalogSeeder)->run();
}
