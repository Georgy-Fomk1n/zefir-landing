<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:30', config('app.timezone')));
    }

    private function cart(array $body)
    {
        return $this->postJson('/api/cart', $body);
    }

    private function validOrder(array $over = []): array
    {
        return $over + ['ship' => 'pickup', 'name' => 'Анна', 'phone' => '89161234567', 'date' => '2026-11-04', 'hour' => 12];
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/catalog', '/catalog?cat=pp&sort=desc', '/product/pistachio', '/product/bento', '/delivery', '/checkout', '/offline'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/product/nope')->assertNotFound();
    }

    public function test_public_pages_set_no_cookies_and_are_cacheable(): void
    {
        $r = $this->get('/catalog')->assertOk();
        $this->assertEmpty($r->headers->getCookies());
        $this->assertStringContainsString('public', (string) $r->headers->get('Cache-Control'));
    }

    public function test_catalog_filters_and_sorts(): void
    {
        $this->get('/catalog?cat=pp')->assertSee('ПП-банан')->assertDontSee('Фисташка');
        $html = $this->get('/catalog?cat=cake&sort=asc')->getContent();
        $this->assertLessThan(strpos($html, 'Тарт лимонный'), strpos($html, 'Эклер классический'));   // 200 ₽ раньше 250 ₽
        $this->assertGreaterThan(strpos($html, 'Тарт лимонный'), strpos($html, 'Груша'));             // «цена по запросу» в конце
    }

    public function test_cart_add_set_and_card(): void
    {
        $this->cart(['action' => 'add', 'id' => 'potato', 'q' => 2])->assertOk()->assertJsonPath('cart.total', 420)->assertJsonPath('cart.count', 2);
        $this->cart(['action' => 'add', 'id' => 'potato', 'q' => 1])->assertJsonPath('cart.count', 3);
        $this->cart(['action' => 'set', 'id' => 'potato', 'v' => 0, 'q' => 1])->assertJsonPath('cart.total', 210);
        $this->cart(['action' => 'card', 'on' => true, 'text' => 'С днём рождения'])->assertJsonPath('cart.card.on', true);
        $this->cart(['action' => 'set', 'id' => 'potato', 'v' => 0, 'q' => 0])->assertJsonPath('cart.count', 0);
    }

    public function test_cart_variants_use_variant_price(): void
    {
        $p = Product::where('slug', 'pistachio')->with('variants')->first();
        $four = $p->variants->firstWhere('label', 'Набор из четырёх');
        $this->cart(['action' => 'add', 'id' => 'pistachio', 'v' => $four->id, 'q' => 2])->assertJsonPath('cart.total', 2100);
    }

    public function test_unbuyable_products_are_rejected(): void
    {
        $this->cart(['action' => 'add', 'id' => 'bento'])->assertStatus(422);         // на заказ
        $this->cart(['action' => 'add', 'id' => 'pear'])->assertStatus(422);          // цена по запросу
        $this->cart(['action' => 'add', 'id' => 'pp-blueberry'])->assertStatus(422);  // нет в наличии
        $this->cart(['action' => 'add', 'id' => 'nope'])->assertStatus(422);
    }

    public function test_client_cannot_set_price_and_price_changes_are_picked_up(): void
    {
        $this->cart(['action' => 'add', 'id' => 'eclair', 'q' => 1, 'price' => 1])->assertJsonPath('cart.total', 200);
        Product::where('slug', 'eclair')->update(['price' => 230]);
        $this->getJson('/api/cart')->assertJsonPath('cart.total', 230);
    }

    public function test_checkout_creates_order_and_clears_cart(): void
    {
        $this->cart(['action' => 'add', 'id' => 'potato', 'q' => 2]);
        $this->cart(['action' => 'card', 'on' => true, 'text' => 'Маше']);
        $res = $this->postJson('/api/checkout', $this->validOrder(['ship' => 'courier', 'addr' => 'Базовская 17, кв 5', 'comment' => 'Домофон']))->assertOk();

        $order = Order::with('items')->first();
        $this->assertSame('+7 (916) 123-45-67', $order->phone);
        $this->assertSame(420 + 390, $order->total);
        $this->assertSame(390, $order->fee);
        $this->assertSame('Маше', $order->card_text);
        $this->assertSame('new', $order->status);
        $this->assertCount(1, $order->items);
        $this->assertSame(420, $order->items[0]->sum);
        $res->assertJsonPath('redirect', route('order', $order->token));
        $this->getJson('/api/cart')->assertJsonPath('cart.count', 0);

        $this->get(route('order', $order->token))->assertOk()->assertSee('Спасибо, Анна')->assertSee($order->number());
        $this->get('/order/wrong-token')->assertNotFound();
    }

    public function test_checkout_validation(): void
    {
        $this->postJson('/api/checkout', $this->validOrder())->assertStatus(422)->assertJsonValidationErrors('cart');   // пустая корзина
        $this->cart(['action' => 'add', 'id' => 'potato']);

        $this->postJson('/api/checkout', $this->validOrder(['phone' => '123']))->assertJsonValidationErrors('phone');
        $this->postJson('/api/checkout', $this->validOrder(['name' => '']))->assertJsonValidationErrors('name');
        $this->postJson('/api/checkout', $this->validOrder(['ship' => 'courier']))->assertJsonValidationErrors('addr');
        $this->postJson('/api/checkout', $this->validOrder(['date' => '2026-11-09']))->assertJsonValidationErrors('slot');  // понедельник
        $this->postJson('/api/checkout', $this->validOrder(['hour' => 14]))->assertJsonValidationErrors('slot');
        $this->postJson('/api/checkout', $this->validOrder(['date' => '2026-11-03', 'hour' => 10]))->assertJsonValidationErrors('slot');   // слишком скоро
        $this->assertSame(0, Order::count());
    }

    public function test_status_messages_for_push(): void
    {
        $o = new Order(['id' => 5, 'status' => 'ready', 'shipping' => 'pickup']);
        $o->id = 5;
        $this->assertStringContainsString('1005', $o->statusPush()[0]);
        $o->status = 'done';
        $this->assertNull($o->statusPush());
    }

    public function test_push_subscription_rules(): void
    {
        $sub = ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a']];
        $this->cart(['action' => 'add', 'id' => 'potato']);
        $this->postJson('/api/checkout', $this->validOrder())->assertOk();
        $order = Order::first();

        // покупатель: только с верной ссылкой на заказ
        $this->postJson('/api/push/subscribe', ['kind' => 'customer', 'order_token' => 'bad', 'subscription' => $sub])->assertNotFound();
        $this->postJson('/api/push/subscribe', ['kind' => 'customer', 'order_token' => $order->token, 'subscription' => $sub])->assertOk();
        $this->assertSame(1, PushSubscription::where('kind', 'customer')->where('order_id', $order->id)->count());
        $this->postJson('/api/push/subscribe', ['kind' => 'customer', 'order_token' => $order->token, 'subscription' => $sub])->assertOk();
        $this->assertSame(1, PushSubscription::count());   // повторная подписка не плодит дубли

        // владелец: только вошедший
        $this->postJson('/api/push/subscribe', ['kind' => 'owner', 'subscription' => ['endpoint' => 'https://push.example/own'] + $sub])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/api/push/subscribe', ['kind' => 'owner', 'subscription' => ['endpoint' => 'https://push.example/own'] + $sub])->assertOk();
        $this->assertSame(1, PushSubscription::where('kind', 'owner')->count());

        $this->postJson('/api/push/unsubscribe', ['endpoint' => 'https://push.example/abc'])->assertOk();
        $this->assertSame(0, PushSubscription::where('kind', 'customer')->count());
    }

    public function test_editing_a_product_flushes_page_cache(): void
    {
        $this->get('/product/tart')->assertSee('250');
        Product::where('slug', 'tart')->first()->update(['price' => 275]);
        $this->get('/product/tart')->assertSee('275');
    }

    public function test_admin_requires_login(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/orders')->assertOk();
    }
}
