<?php
declare(strict_types=1);

/*
 * Корзина лежит в PHP-сессии: [['id' => 'pistachio', 'v' => 'four', 'q' => 2], ...].
 * Цены и названия в сессию не пишем: они пересчитываются из app/catalog.php при каждом чтении,
 * поэтому подменить цену с клиента нельзя.
 */

const CART_MAX_QTY = 99;

function &cart_raw(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

/** Строки корзины с актуальными ценами. Недоступные товары молча отбрасываются. */
function cart_lines(): array
{
    $out = [];
    foreach (cart_raw() as $l) {
        $p = product((string) ($l['id'] ?? ''));
        if (!$p || !buyable($p)) {
            continue;
        }
        $v = variant($p, (string) ($l['v'] ?? ''));
        $q = max(1, min(CART_MAX_QTY, (int) ($l['q'] ?? 1)));
        $name = count(variants($p)) > 1 ? $p['name'] . ' · ' . mb_strtolower($v['label']) : $p['name'];
        $out[] = [
            'id' => $p['id'], 'v' => $v['id'], 'name' => $name, 'img' => $p['img'], 'url' => product_url($p),
            'price' => (int) $v['price'], 'q' => $q, 'sum' => (int) $v['price'] * $q,
        ];
    }
    return $out;
}

function cart_total(): int
{
    return array_sum(array_column(cart_lines(), 'sum'));
}

function cart_count(): int
{
    return array_sum(array_column(cart_lines(), 'q'));
}

function cart_card(): array
{
    $c = $_SESSION['card'] ?? [];
    return ['on' => !empty($c['on']), 'text' => (string) ($c['text'] ?? '')];
}

function cart_add(string $id, string $vid, int $q): bool
{
    $p = product($id);
    if (!$p || !buyable($p)) {
        return false;
    }
    $vid = variant($p, $vid)['id'];
    $q = max(1, min(CART_MAX_QTY, $q));
    $cart = &cart_raw();
    foreach ($cart as &$l) {
        if ($l['id'] === $id && $l['v'] === $vid) {
            $l['q'] = min(CART_MAX_QTY, (int) $l['q'] + $q);
            return true;
        }
    }
    unset($l);
    $cart[] = ['id' => $id, 'v' => $vid, 'q' => $q];
    return true;
}

function cart_set(string $id, string $vid, int $q): void
{
    $cart = &cart_raw();
    foreach ($cart as $i => $l) {
        if ($l['id'] === $id && $l['v'] === $vid) {
            if ($q <= 0) {
                array_splice($cart, $i, 1);
            } else {
                $cart[$i]['q'] = min(CART_MAX_QTY, $q);
            }
            return;
        }
    }
}

function cart_set_card(bool $on, string $text): void
{
    $_SESSION['card'] = ['on' => $on, 'text' => mb_substr(trim($text), 0, 90)];
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
    $_SESSION['card'] = ['on' => false, 'text' => ''];
}

/** Состояние для JS и шторки */
function cart_state(): array
{
    return ['lines' => cart_lines(), 'count' => cart_count(), 'total' => cart_total(), 'card' => cart_card()];
}

/** Сколько штук товара (по всем форматам) лежит в корзине */
function cart_qty_of(string $id): int
{
    $n = 0;
    foreach (cart_lines() as $l) {
        if ($l['id'] === $id) {
            $n += $l['q'];
        }
    }
    return $n;
}
