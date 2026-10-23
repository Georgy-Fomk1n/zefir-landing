<?php

use App\Enums\Role;
use App\Jobs\DispatchStaticRebuild;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Services\Catalog\StaticRebuild;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    seedCatalog();
    $this->admin = asStaff(makeUser(Role::Admin));
});

it('ставит пересборку статики с дебаунсом, а при смене цены — сразу', function () {
    config(['shop.rebuild.enabled' => true]);
    Queue::fake();
    $variant = Product::query()->where('slug', 'kofe')->first()->variants->first();

    $this->patchJson("/api/staff/admin/variants/{$variant->id}", ['stock' => 5], $this->admin)->assertOk();
    $this->patchJson("/api/staff/admin/variants/{$variant->id}", ['stock' => 6], $this->admin)->assertOk();
    Queue::assertPushed(DispatchStaticRebuild::class, 1); // дебаунс: одна отложенная задача

    $this->patchJson("/api/staff/admin/variants/{$variant->id}", ['price' => 47000], $this->admin)->assertOk();
    Queue::assertPushed(DispatchStaticRebuild::class, 2);
});

it('отправляет repository_dispatch с изменёнными путями', function () {
    config(['shop.rebuild.enabled' => true, 'shop.rebuild.token' => 't', 'shop.rebuild.repository' => 'o/r']);
    Http::fake(['api.github.com/*' => Http::response(null, 204)]);
    app(StaticRebuild::class)->request(['/catalog/vkusy/kofe/'], immediate: true);
    Http::assertSent(fn ($r) => $r->url() === 'https://api.github.com/repos/o/r/dispatches'
        && $r['event_type'] === 'catalog-updated' && $r['client_payload']['paths'] === ['/catalog/vkusy/kofe/']);
});

it('сохраняет товар с вариантами и SEO-полями', function () {
    $cat = Category::query()->where('slug', 'vkusy')->first();
    $payload = [
        'category_id' => $cat->id, 'slug' => 'mango', 'name' => 'Манго', 'description' => 'Тестовый вкус', 'is_active' => true,
        'seo_title' => 'Пирожное манго — Москва', 'noindex' => false,
        'variants' => [['sku' => 'ZP-MANGO-1', 'name' => '1 шт.', 'price' => 45000, 'stock' => 3]],
        'media' => [['type' => 'image', 'name' => 'marakuyya', 'alt' => 'Манго']],
    ];
    $this->postJson('/api/staff/admin/products', $payload, $this->admin)->assertCreated()->assertJsonPath('data.seo.title', 'Пирожное манго — Москва');
    $this->postJson('/api/staff/admin/products', $payload, $this->admin)->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->postJson('/api/staff/admin/products', ['slug' => 'Плохой Slug'] + $payload, $this->admin)->assertUnprocessable();
});

it('создаёт сотрудников только админом и без саморегистрации', function () {
    $this->postJson('/api/staff/admin/users', ['name' => 'Курьер', 'email' => 'c@example.test', 'password' => 'long-password-1', 'roles' => ['courier']], $this->admin)
        ->assertCreated()->assertJsonPath('data.roles', ['courier']);
    $this->postJson('/api/staff/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'long-password-1', 'roles' => ['admin']], asStaff(makeUser(Role::Seller)))
        ->assertForbidden();
    $this->postJson('/api/staff/auth/register', [])->assertNotFound();
});

it('модерирует отзывы и показывает в каталоге только одобренные', function () {
    $product = Product::query()->where('slug', 'kofe')->first();
    $review = Review::create(['product_id' => $product->id, 'author_name' => 'Аня', 'rating' => 5, 'text' => 'Очень вкусно']);
    $this->getJson('/api/catalog/products/kofe')->assertJsonPath('data.rating', null);
    $this->postJson("/api/staff/admin/reviews/{$review->id}", ['status' => 'approved'], $this->admin)->assertOk();
    $this->getJson('/api/catalog/products/kofe')->assertJsonPath('data.rating.count', 1);
    Cache::flush();
    $this->getJson('/api/catalog/export')->assertJsonPath('reviews.0.productSlug', 'kofe');
});

it('отдаёт отчёт и CSV', function () {
    $this->getJson('/api/staff/admin/reports/summary', $this->admin)->assertOk()->assertJsonStructure(['data' => ['orders', 'revenue', 'deliveries' => ['failedShare']]]);
    $this->get('/api/staff/admin/reports/orders.csv', $this->admin)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');
});
