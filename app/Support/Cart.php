<?php

namespace App\Support;

use App\Models\Product;

/**
 * Корзина лежит в сессии: [['id' => slug, 'v' => variantId, 'q' => qty], ...].
 * Цены и названия в сессию не пишутся: считаются из базы при каждом чтении,
 * поэтому подменить цену с клиента нельзя.
 */
class Cart
{
    public const MAX_QTY = 99;

    private function raw(): array
    {
        return session('cart', []);
    }

    private function put(array $cart): void
    {
        session(['cart' => array_values($cart)]);
    }

    /** Строки корзины с актуальными ценами. Недоступные товары молча отбрасываются. */
    public function lines(): array
    {
        $raw = $this->raw();
        if (! $raw) {
            return [];
        }
        $products = Product::visible()->with('variants')->whereIn('slug', array_column($raw, 'id'))->get()->keyBy('slug');
        $out = [];
        foreach ($raw as $l) {
            $p = $products[$l['id']] ?? null;
            if (! $p || ! $p->isBuyable()) {
                continue;
            }
            $v = $p->variant((int) $l['v']);
            $q = max(1, min(self::MAX_QTY, (int) $l['q']));
            $name = count($p->variantList()) > 1 ? $p->name . ' · ' . mb_strtolower($v['label']) : $p->name;
            $out[] = [
                'id' => $p->slug, 'v' => $v['id'], 'name' => $name, 'image' => $p->image, 'url' => $p->url(),
                'price' => $v['price'], 'q' => $q, 'sum' => $v['price'] * $q, 'product_id' => $p->id,
            ];
        }

        return $out;
    }

    public function add(string $slug, int $variant, int $q): bool
    {
        $p = Product::visible()->with('variants')->where('slug', $slug)->first();
        if (! $p || ! $p->isBuyable()) {
            return false;
        }
        $variant = $p->variant($variant)['id'];
        $q = max(1, min(self::MAX_QTY, $q));
        $cart = $this->raw();
        foreach ($cart as &$l) {
            if ($l['id'] === $slug && $l['v'] === $variant) {
                $l['q'] = min(self::MAX_QTY, $l['q'] + $q);
                $this->put($cart);

                return true;
            }
        }
        unset($l);
        $cart[] = ['id' => $slug, 'v' => $variant, 'q' => $q];
        $this->put($cart);

        return true;
    }

    public function set(string $slug, int $variant, int $q): void
    {
        $cart = $this->raw();
        foreach ($cart as $i => $l) {
            if ($l['id'] === $slug && $l['v'] === $variant) {
                if ($q <= 0) {
                    unset($cart[$i]);
                } else {
                    $cart[$i]['q'] = min(self::MAX_QTY, $q);
                }
                $this->put($cart);

                return;
            }
        }
    }

    public function card(): array
    {
        $c = session('card', []);

        return ['on' => ! empty($c['on']), 'text' => (string) ($c['text'] ?? '')];
    }

    public function setCard(bool $on, string $text): void
    {
        session(['card' => ['on' => $on, 'text' => mb_substr(trim($text), 0, 90)]]);
    }

    public function clear(): void
    {
        session()->forget(['cart', 'card']);
    }

    public function state(): array
    {
        $lines = $this->lines();

        return [
            'lines' => array_map(fn ($l) => collect($l)->except('product_id')->all(), $lines),
            'count' => array_sum(array_column($lines, 'q')),
            'total' => array_sum(array_column($lines, 'sum')),
            'card' => $this->card(),
        ];
    }
}
