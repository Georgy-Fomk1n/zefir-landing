<?php
declare(strict_types=1);

/* Общая обёртка страниц: <head>, шапка, шторка корзины, подвал, карточка товара. */

const ICON_PLUS = '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12M12 6v12"/></svg>';
const ICON_MINUS = '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12"/></svg>';
const ICON_ARROW = '<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
const ICON_MOON = '<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 1 1 9.5 4 6.5 6.5 0 0 0 20 14.5z"/></svg>';
const ICON_BAG = '<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 8h14l-1.2 11.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg>';
const ICON_CHECK = '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="m5 12 5 5 9-10"/></svg>';
const ICON_X = '<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>';

/**
 * Начало страницы.
 * $o: title, description?, canonical?, robots?, active?, head?(сырой HTML в <head>), header?('full'|'checkout'|'none'), drawer?(bool)
 */
function page_start(array $o): void
{
    $o += ['description' => '', 'canonical' => '', 'robots' => '', 'active' => '', 'head' => '', 'header' => 'full', 'drawer' => true];
    $GLOBALS['__page'] = $o;
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($o['title']) ?></title>
<?php if ($o['description'] !== ''): ?>
    <meta name="description" content="<?= e($o['description']) ?>">
<?php endif; ?>
<?php if ($o['canonical'] !== ''): ?>
    <link rel="canonical" href="<?= e($o['canonical']) ?>">
<?php endif; ?>
<?php if ($o['robots'] !== ''): ?>
    <meta name="robots" content="<?= e($o['robots']) ?>">
<?php endif; ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" type="image/png" href="favicon.png">
<?= $o['head'] ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600&family=Caveat:wght@500&display=swap">
    <link rel="stylesheet" href="css/shop.css?v=<?= (int) @filemtime(ROOT . '/css/shop.css') ?>">
</head>

<body>
<?php
    if ($o['header'] === 'checkout') {
        page_header_checkout();
    } elseif ($o['header'] === 'full') {
        page_header($o['active']);
    }
}

function page_header(string $active): void
{
    $n = cart_count();
    $cur = fn (string $k) => $active === $k ? ' aria-current="page"' : '';
    $open = open_now();
    ?>
    <header class="top g"><div class="bar glass">
        <a class="logo" href="index.php" aria-label="Зефир и пряник — на главную"><img src="images/logo.png" alt="" width="40" height="40"><span>Зефир <i>и</i> пряник</span></a>
        <nav aria-label="Разделы">
            <a href="catalog.php"<?= $cur('catalog') ?>>Каталог</a>
            <a href="index.php#cakes">Торты на заказ</a>
            <a href="info.php"<?= $cur('info') ?>>Доставка и контакты</a>
        </nav>
        <div class="tools">
            <span class="open-now<?= $open ? '' : ' closed' ?>"><?= $open ? 'Открыто до ' . (int) cfg('hours.close') . ':00' : 'Сейчас закрыто' ?></span>
            <button class="ib" id="themeBtn" type="button" aria-label="Сменить тему"><?= ICON_MOON ?></button>
            <button class="ib" type="button" data-open-cart aria-label="Открыть корзину"><?= ICON_BAG ?><span class="n tn" id="cnt"<?= $n ? '' : ' hidden' ?>><?= $n ?></span></button>
        </div>
    </div></header>
<?php
}

function page_header_checkout(): void
{
    ?>
    <header class="top g"><div class="bar glass">
        <a class="logo" href="index.php" aria-label="Зефир и пряник — на главную"><img src="images/logo.png" alt="" width="40" height="40"><span>Зефир <i>и</i> пряник</span></a>
        <ol class="steps" aria-label="Шаги оформления">
            <li><span>01</span>Корзина</li>
            <li aria-current="step"><span>02</span>Получение</li>
            <li><span>03</span>Заказ принят</li>
        </ol>
        <button class="ib" id="themeBtn" type="button" aria-label="Сменить тему"><?= ICON_MOON ?></button>
    </div></header>
<?php
}

/** Конец страницы. $scripts: дополнительные JS-файлы после shop.js */
function page_end(array $scripts = []): void
{
    $o = $GLOBALS['__page'] ?? [];
    $s = cfg('shop');
    ?>

    <footer class="site g"><div class="w">
        <div style="display:flex;flex-direction:column;gap:10px"><img src="images/logo.png" alt="Зефир и пряник" width="150" height="150"><span class="small">Кондитерская ручной работы · <?= e($s['address']) ?></span></div>
        <nav aria-label="Мы в сети"><a href="<?= e($s['telegram']) ?>">Telegram</a><a href="<?= e($s['vk']) ?>">ВКонтакте</a><a href="<?= e($s['max']) ?>">MAX</a><a href="<?= e($s['flowwow']) ?>">Flowwow</a></nav>
    </div>
    <div class="w legal">
        <p style="font-weight:600">ИНДИВИДУАЛЬНЫЙ ПРЕДПРИНИМАТЕЛЬ ГУБАНОВА ТАТЬЯНА СЕРГЕЕВНА</p>
        <p>ОГРНИП 323774600785438 от <a href="https://zachestnyibiznes.ru/day/2023-11-28">28.11.2023</a> · ИНН 772442265466</p>
        <p style="margin-top:8px">Сайт не является публичной офертой, цены представлены для ознакомления и могут быть изменены в одностороннем порядке</p>
        <p style="margin-top:8px">© <?= date('Y') ?> Зефир и пряник. Москва. Все права защищены.</p>
    </div></footer>
<?php if (($o['drawer'] ?? true)): ?>

    <div class="scrim" data-close-cart></div>
    <aside class="drawer glass" id="drawer" role="dialog" aria-modal="true" aria-label="Корзина">
        <div class="drawer-h"><h2>Корзина</h2><button class="ib" type="button" data-close-cart aria-label="Закрыть"><?= ICON_X ?></button></div>
        <div class="drawer-b" id="drawerBody"></div>
        <div class="drawer-f" id="drawerFoot"></div>
    </aside>
    <div class="mobile-cta glass" id="mobileCta" hidden><button class="btn btn-main" type="button" data-open-cart></button></div>
<?php endif; ?>
    <div class="toast glass" id="toast" role="status"><span class="dot"><?= ICON_CHECK ?></span><span id="toastText"></span></div>

    <script>window.__CART__ = <?= json_encode(cart_state(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
    <script src="js/shop.js?v=<?= (int) @filemtime(ROOT . '/js/shop.js') ?>"></script>
<?php foreach ($scripts as $js): ?>
    <script src="<?= e($js) ?>?v=<?= (int) @filemtime(ROOT . '/' . $js) ?>"></script>
<?php endforeach; ?>
</body>

</html>
<?php
}

/** Кнопка покупки под карточкой: зависит от товара и от того, что уже в корзине */
function buy_html(array $p): string
{
    if (!empty($p['out'])) {
        return '<a class="btn btn-line btn-sm" href="info.php#contacts">Сообщить о поступлении</a>';
    }
    if (!empty($p['order']) || !is_int($p['price'] ?? null)) {
        return !empty($p['order'])
            ? '<a class="btn btn-main btn-sm" href="info.php#contacts">Заказать</a>'
            : '<a class="btn btn-line btn-sm" href="info.php#contacts">Уточнить цену</a>';
    }
    if (count(variants($p)) > 1) {
        return '<a class="btn btn-main btn-sm" href="' . e(product_url($p)) . '">Выбрать формат</a>';
    }
    $q = cart_qty_of($p['id']);
    if (!$q) {
        return '<button class="btn btn-main btn-sm" type="button" data-add="' . e($p['id']) . '">В корзину</button>';
    }
    return '<div class="qty pq" role="group" aria-label="' . e($p['name']) . ': количество в корзине"><button type="button" data-pm="-1" aria-label="Меньше">' . ICON_MINUS . '</button><output class="tn" aria-live="polite">' . $q . '</output><button type="button" data-pm="1" aria-label="Больше">' . ICON_PLUS . '</button></div><span class="small">в корзине</span>';
}

/** Карточка товара для сеток */
function card_html(array $p, int $i = 0): string
{
    $priced = is_int($p['price'] ?? null) && empty($p['out']);
    $simple = buyable($p) && count(variants($p)) === 1;
    $badges = (!empty($p['new']) ? '<span class="badge">Новинка</span>' : '') . (!empty($p['pp']) ? '<span class="badge pp">Без сахара</span>' : '') . (!empty($p['order']) ? '<span class="badge ord">На заказ</span>' : '');
    return '<article class="card' . (!empty($p['out']) ? ' out' : '') . '" data-id="' . e($p['id']) . '"' . ($simple ? ' data-simple="1"' : '') . '>' .
        '<a class="ph" href="' . e(product_url($p)) . '" aria-label="' . e($p['name']) . '"><div class="photo"><img src="' . e($p['img']) . '" alt="' . e($p['name']) . '" loading="' . ($i > 3 ? 'lazy' : 'eager') . '" width="400" height="500"><span class="badges">' . $badges . '</span></div></a>' .
        '<div class="head"><h2><a href="' . e(product_url($p)) . '">' . e($p['name']) . '</a></h2><span class="price' . ($priced ? '' : ' muted') . '">' . e(price_text($p)) . '</span></div>' .
        '<p class="comp">' . e($p['short'] ?? '') . '</p><div class="buy">' . buy_html($p) . '</div></article>';
}
