<?php

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TimeSlot;
use App\Services\Delivery\CdekProvider;
use App\Services\Delivery\DeliveryProviders;
use App\Services\Delivery\PickupProvider;
use App\Services\Delivery\RussianPostProvider;
use App\Services\Geo\DadataGeoSuggest;
use App\Services\Geo\GeoSuggest;
use App\Services\Geo\NullGeoSuggest;
use App\Services\Geo\YandexGeoSuggest;
use App\Services\Orders\SlotService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => seedCatalog());

it('отдаёт категории, фильтрует ПП, сортирует и пагинирует товары', function () {
    $this->getJson('/api/catalog/categories')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.0.slug', 'vkusy');
    $this->getJson('/api/catalog/products?pp=1')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/catalog/products?sort=price_desc')->assertJsonPath('data.0.slug', 'nabor-10');
    $this->getJson('/api/catalog/products?sort=name')->assertJsonPath('data.0.name', 'Кофе');
    $this->getJson('/api/catalog/products?per_page=3')->assertJsonCount(3, 'data')->assertJsonPath('meta.last_page', 3)->assertJsonPath('meta.total', 8);
    $this->getJson('/api/catalog/products?sort=evil')->assertUnprocessable();
    $this->getJson('/api/catalog/products/kofe')->assertOk()->assertHeader('Cache-Control', 'max-age=30, public');
});

it('не показывает снятые с продажи товары и варианты', function () {
    Product::query()->where('slug', 'kofe')->update(['is_active' => false]);
    ProductVariant::query()->where('sku', 'ZP-CHERNIKA-1')->update(['is_active' => false]);
    $this->getJson('/api/catalog/products/kofe')->assertNotFound();
    $this->getJson('/api/catalog/products/chernika')->assertJsonCount(0, 'data.variants');
    $this->postJson('/api/cart/quote', ['lines' => [['variantId' => ProductVariant::query()->where('sku', 'ZP-KOFE-1')->value('id'), 'qty' => 1]], 'fulfilment' => 'pickup'])
        ->assertJsonPath('data.subtotal', 0);
});

it('подсказывает адреса через DaData, ограничивая Москвой и кешируя', function () {
    config(['shop.geo.provider' => 'dadata', 'shop.geo.dadata_token' => 'tok']);
    Http::fake(['suggestions.dadata.ru/*' => Http::response(['suggestions' => [
        ['value' => 'г Москва, ул Базовская, д 15', 'data' => ['geo_lat' => '55.88', 'geo_lon' => '37.51']],
        ['value' => 'г Москва, ул Базовская', 'data' => ['geo_lat' => null, 'geo_lon' => null]],
    ]])]);
    expect(app(GeoSuggest::class))->toBeInstanceOf(DadataGeoSuggest::class);
    $this->getJson('/api/geo/suggest?q=Базовская 15')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.lat', 55.88);
    $this->getJson('/api/geo/suggest?q=Базовская 15')->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r->header('Authorization')[0] === 'Token tok' && $r['locations'] === [['city' => 'Москва']]);
    $this->getJson('/api/geo/suggest?q=ab')->assertUnprocessable();
});

it('подсказывает адреса через Яндекс Геосаджест и геокодер', function () {
    config(['shop.geo.provider' => 'yandex', 'shop.geo.yandex_key' => 'k']);
    Http::fake([
        'suggest-maps.yandex.ru/*' => Http::response(['results' => [
            ['title' => ['text' => 'Базовская улица, 15'], 'subtitle' => ['text' => 'Москва'], 'uri' => 'ymapsbm1://geo?x'],
            ['title' => ['text' => 'без uri']],
        ]]),
        'geocode-maps.yandex.ru/*' => Http::response(['response' => ['GeoObjectCollection' => ['featureMember' => [['GeoObject' => ['Point' => ['pos' => '37.51 55.88']]]]]]]),
    ]);
    expect(app(GeoSuggest::class))->toBeInstanceOf(YandexGeoSuggest::class);
    $res = app(GeoSuggest::class)->suggest('Базовская 15');
    expect($res)->toBe([['text' => 'Базовская улица, 15, Москва', 'lat' => 55.88, 'lng' => 37.51]]);
});

it('без провайдера геоподсказок отдаёт пусто, ошибки провайдера не роняют оформление', function () {
    expect(app(GeoSuggest::class))->toBeInstanceOf(NullGeoSuggest::class)->and((new NullGeoSuggest)->suggest('x'))->toBe([]);
    Http::fake(['*' => Http::response(null, 500)]);
    config(['shop.geo.dadata_token' => 't']);
    expect((new DadataGeoSuggest)->suggest('Тверская 1'))->toBe([])
        ->and((new YandexGeoSuggest)->suggest('Тверская 1'))->toBe([]);
});

it('считает СДЭК и Почту в тестовом режиме и создаёт отправление', function () {
    $order = Order::factory()->create();
    $cdek = new CdekProvider;
    $post = new RussianPostProvider;
    expect($cdek->quote(null, 100000)->available)->toBeFalse()
        ->and($cdek->quote(['text' => 'Казань'], 100000)->cost)->toBe(45000)
        ->and($cdek->createShipment($order))->toBe("CDEK-MOCK-{$order->id}")
        ->and($cdek->track($order))->toBe([])
        ->and($post->quote(['text' => 'Тула, 300000'], 1)->cost)->toBe(35000)
        ->and($post->quote(null, 1)->available)->toBeFalse()
        ->and($post->createShipment($order))->toBe("RP-MOCK-{$order->id}")
        ->and($post->track($order))->toBe([]);

    config(['shop.delivery.cdek.enabled' => true, 'shop.delivery.russian_post.enabled' => true]);
    expect($cdek->quote(['text' => 'x'], 1)->available)->toBeFalse()->and($cdek->createShipment($order))->toBeNull()
        ->and($post->quote(['text' => 'x'], 1)->available)->toBeFalse()->and($post->createShipment($order))->toBeNull();

    $pickup = new PickupProvider;
    expect($pickup->quote(null, 1)->cost)->toBe(0)->and($pickup->createShipment($order))->toBeNull()->and($pickup->track($order))->toBe([])
        ->and(app(DeliveryProviders::class)->for(Fulfilment::RussianPost))->toBeInstanceOf(RussianPostProvider::class);
});

it('создаёт трек-номер СДЭК, когда заказ отправлен', function () {
    $order = Order::factory()->create(['fulfilment' => 'cdek', 'status' => 'ready', 'payment_status' => 'paid']);
    $order->transitionTo(OrderStatus::Shipped);
    expect($order->refresh()->external_tracking)->toBe("CDEK-MOCK-{$order->id}");
});

it('генерирует слоты по часам работы: понедельник выходной, повторный запуск ничего не дублирует', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-26 08:00', 'Europe/Moscow')); // понедельник
    $created = app(SlotService::class)->generate(2);
    expect(TimeSlot::query()->whereDate('date', '2026-10-26')->count())->toBe(0)
        ->and(TimeSlot::query()->whereDate('date', '2026-10-27')->where('type', 'pickup')->count())->toBe(11) // 10:00–21:00 по часу
        ->and(TimeSlot::query()->whereDate('date', '2026-10-27')->where('type', 'courier')->count())->toBe(11)
        ->and($created)->toBe(22)
        ->and(app(SlotService::class)->generate(2))->toBe(0);
    $this->artisan('slots:generate --days=3')->assertSuccessful()->expectsOutputToContain('Создано слотов');
    $this->getJson('/api/slots?type=evil')->assertUnprocessable();
});

it('возвращает место в слоте и не уходит в минус', function () {
    $slot = TimeSlot::create(['type' => 'pickup', 'date' => now()->addDay()->toDateString(), 'starts_at' => '12:00:00', 'ends_at' => '13:00:00', 'capacity' => 2, 'booked' => 1]);
    $s = app(SlotService::class);
    $s->release($slot->id);
    $s->release($slot->id);
    $s->release(null);
    expect($slot->fresh()->booked)->toBe(0);
});
