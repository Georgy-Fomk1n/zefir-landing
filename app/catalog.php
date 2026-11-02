<?php
declare(strict_types=1);

/*
 * Каталог. Единственное место, где живут товары и цены.
 * price: число (₽) — товар можно положить в корзину; null — цена не указана, покупатель уточняет её у кондитерской.
 * variants: необязательно, если у товара несколько форматов (одно пирожное / набор).
 * order: true — делается на заказ, в корзину не кладётся.
 */

function categories(): array
{
    return [
        'all' => 'Все', 'cake' => 'Пирожные', 'zefir' => 'Зефир', 'pryanik' => 'Пряники',
        'tort' => 'Торты', 'gift' => 'Подарки', 'pp' => 'Без сахара',
    ];
}

function in_category(array $p, string $cat): bool
{
    return match ($cat) {
        'all' => true,
        'gift' => in_array($p['k'], ['gift', 'pryanik'], true),
        'pp' => !empty($p['pp']),
        default => $p['k'] === $cat,
    };
}

function products(): array
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $list = [
        ['id' => 'pear', 'k' => 'cake', 'name' => 'Груша', 'img' => 'images/shop/pear.jpg', 'new' => true, 'price' => null,
            'short' => 'Корпусное: шоколадный корпус, мусс, грушевая начинка'],
        ['id' => 'apple', 'k' => 'cake', 'name' => 'Яблоко и корица', 'img' => 'images/shop/apple.jpg', 'new' => true, 'price' => null,
            'short' => 'Корпусное: ганаш на яблочном пюре, яблоки с корицей'],
        ['id' => 'pistachio', 'k' => 'cake', 'name' => 'Фисташка', 'img' => 'images/shop/pistachio.webp', 'new' => true, 'price' => 280,
            'short' => 'Финансье, фисташковая намелака, хрустящий слой, ганаш',
            'long' => 'Внутри фисташковый бисквит финансье, фисташковая намелака, хрустящий слой и нежнейший ганаш. Сверху тонкий шоколад, который ломается с хрустом.',
            'allergens' => 'Орехи, молоко, глютен',
            'variants' => [
                ['id' => 'one', 'label' => 'Одно пирожное', 'sub' => '110 г', 'price' => 280],
                ['id' => 'four', 'label' => 'Набор из четырёх', 'sub' => 'В коробке с лентой', 'price' => 1050],
                ['id' => 'six', 'label' => 'Набор из шести', 'sub' => 'В коробке с лентой', 'price' => 1550],
            ]],
        ['id' => 'passion', 'k' => 'cake', 'name' => 'Маракуйя', 'img' => 'images/shop/passion.webp', 'price' => null,
            'short' => 'Хрустящий корпус, сливочный крем, яркая маракуйя'],
        ['id' => 'pp-banana', 'k' => 'cake', 'pp' => true, 'name' => 'ПП-банан', 'img' => 'images/shop/pp-banana.webp', 'price' => 270,
            'short' => 'Шоколадный мусс, банановая начинка, корпус на шоколаде без сахара'],
        ['id' => 'pp-blueberry', 'k' => 'cake', 'pp' => true, 'name' => 'ПП-черника', 'img' => 'images/shop/pp-blueberry.webp', 'price' => null, 'out' => true,
            'short' => 'Без сахара, 209 ккал'],
        ['id' => 'potato', 'k' => 'cake', 'name' => 'Картошка в шоколаде', 'img' => 'images/shop/potato.jpg', 'price' => 210,
            'short' => 'Бисквит, сливочный крем, хрустящее покрытие'],
        ['id' => 'eclair', 'k' => 'cake', 'name' => 'Эклер классический', 'img' => 'images/shop/eclair.jpg', 'price' => 200,
            'short' => 'Крем Шарлотт, шоколадная глазурь'],
        ['id' => 'tart', 'k' => 'cake', 'name' => 'Тарт лимонный', 'img' => 'images/shop/tart.jpg', 'price' => 250,
            'short' => 'Лимонный крем и меренга'],
        ['id' => 'zefir-berry', 'k' => 'zefir', 'name' => 'Зефир крыжовник и клубника', 'img' => 'images/shop/zefir.jpg', 'price' => null,
            'short' => 'На натуральном пюре, сахара вдвое меньше'],
        ['id' => 'zefir-choc', 'k' => 'zefir', 'name' => 'Зефир в шоколаде', 'img' => 'images/shop/zefir-choc.jpg', 'price' => null,
            'short' => 'Смородина с клубникой или клюква с черникой'],
        ['id' => 'zefir-mushrooms', 'k' => 'zefir', 'name' => 'Зефирные грибочки', 'img' => 'images/shop/zefir-mushrooms.jpg', 'price' => null,
            'short' => 'Набор в коробке'],
        ['id' => 'zefir-bouquet', 'k' => 'zefir', 'name' => 'Зефирный букет', 'img' => 'images/shop/zefir-bouquet.jpg', 'price' => null, 'order' => true,
            'short' => 'Готовим около двух суток'],
        ['id' => 'gingerbread', 'k' => 'pryanik', 'name' => 'Имбирные пряники', 'img' => 'images/shop/gingerbread.jpg', 'price' => null,
            'short' => 'Мягкие внутри, с глазурью и росписью'],
        ['id' => 'nuts', 'k' => 'gift', 'name' => 'Орехи в шоколаде', 'img' => 'images/shop/nuts.jpg', 'price' => 300, 'price_from' => true,
            'short' => '4 шт. — 300 ₽, 6 шт. — 600 ₽, 12 шт. — 1 000 ₽',
            'variants' => [
                ['id' => 'four', 'label' => '4 штуки', 'sub' => '', 'price' => 300],
                ['id' => 'six', 'label' => '6 штук', 'sub' => '', 'price' => 600],
                ['id' => 'twelve', 'label' => '12 штук', 'sub' => '', 'price' => 1000],
            ]],
        ['id' => 'bento', 'k' => 'tort', 'name' => 'Бенто-торт', 'img' => 'images/shop/bento.jpg', 'price' => null, 'order' => true,
            'short' => 'Мини-торт с надписью, начинка на выбор'],
        ['id' => 'red-velvet', 'k' => 'tort', 'name' => 'Красный бархат', 'img' => 'images/shop/red-velvet.jpg', 'price' => null, 'order' => true,
            'price_note' => '2 700 ₽/кг', 'short' => 'Торт с клубникой, можно кусочком'],
    ];
    return $list;
}

function product(?string $id): ?array
{
    foreach (products() as $p) {
        if ($p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

function variants(array $p): array
{
    return !empty($p['variants']) ? $p['variants'] : [['id' => 'one', 'label' => $p['name'], 'sub' => '', 'price' => $p['price']]];
}

function variant(array $p, ?string $vid): array
{
    foreach (variants($p) as $v) {
        if ($v['id'] === $vid) {
            return $v;
        }
    }
    return variants($p)[0];
}

/** Можно ли положить в корзину */
function buyable(array $p): bool
{
    return is_int($p['price'] ?? null) && empty($p['out']) && empty($p['order']);
}

function product_url(array $p): string
{
    return 'product.php?id=' . rawurlencode($p['id']);
}

function price_text(array $p): string
{
    if (!empty($p['out'])) {
        return 'Нет в наличии';
    }
    if (!empty($p['price_note'])) {
        return $p['price_note'];
    }
    if (!is_int($p['price'] ?? null)) {
        return 'Цена по запросу';
    }
    return (!empty($p['price_from']) ? "от\u{00A0}" : '') . rub($p['price']);
}
