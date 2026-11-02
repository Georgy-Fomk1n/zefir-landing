<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$lines = cart_lines();
$fee_courier = (int) cfg('shop.courier_fee');
$errors = [];
$old = ['ship' => 'pickup', 'addr' => '', 'name' => '', 'phone' => '', 'comment' => '', 'date' => '', 'hour' => ''];

/** Телефон → 10 цифр после +7 или null */
function phone_digits(string $v): ?string
{
    $d = preg_replace('/\D/', '', $v) ?? '';
    if (strlen($d) === 11 && ($d[0] === '7' || $d[0] === '8')) {
        $d = substr($d, 1);
    }
    return strlen($d) === 10 ? $d : null;
}

function phone_fmt(string $d): string
{
    return sprintf('+7 (%s) %s-%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 2), substr($d, 8, 2));
}

if ($lines && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = trim((string) ($_POST[$k] ?? $old[$k]));
    }
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Страница устарела. Обновите её и отправьте заказ ещё раз.';
    }
    $ship = $old['ship'] === 'courier' ? 'courier' : 'pickup';
    $old['ship'] = $ship;
    if ($old['name'] === '' || mb_strlen($old['name']) > 60) {
        $errors['name'] = 'Введите имя';
    }
    $digits = phone_digits($old['phone']);
    if ($digits === null) {
        $errors['phone'] = 'Номер неполный: нужно 10 цифр после +7';
    }
    if ($ship === 'courier' && (mb_strlen($old['addr']) < 5 || mb_strlen($old['addr']) > 200)) {
        $errors['addr'] = 'Укажите адрес доставки';
    }
    if (!slot_valid($old['date'], (int) $old['hour'])) {
        $errors['slot'] = 'Выберите день и время';
    }
    if (mb_strlen($old['comment']) > 200) {
        $errors['comment'] = 'Комментарий не длиннее 200 знаков';
    }

    if (!$errors) {
        $fee = $ship === 'courier' ? $fee_courier : 0;
        $subtotal = array_sum(array_column($lines, 'sum'));
        $card = cart_card();
        $order = [
            'name' => $old['name'], 'phone' => phone_fmt($digits), 'shipping' => $ship,
            'address' => $ship === 'courier' ? $old['addr'] : (string) cfg('shop.address'),
            'date' => $old['date'], 'hour' => (int) $old['hour'], 'comment' => $old['comment'],
            'card_text' => $card['on'] ? $card['text'] : '',
            'items' => array_map(fn ($l) => ['id' => $l['id'], 'name' => $l['name'], 'img' => $l['img'], 'price' => $l['price'], 'q' => $l['q'], 'sum' => $l['sum']], $lines),
            'subtotal' => $subtotal, 'fee' => $fee, 'total' => $subtotal + $fee,
        ];
        try {
            $id = order_create($order);
        } catch (Throwable $ex) {
            shop_log('order_create: ' . $ex->getMessage());
            $errors['form'] = 'Не получилось сохранить заказ. Попробуйте ещё раз или позвоните нам.';
        }
        if (!$errors) {
            order_notify($order, $id);
            cart_clear();
            session_regenerate_id(true);
            $_SESSION['last_order'] = $id;
            redirect('success.php');
        }
    }
}

page_start(['title' => 'Оформление заказа — Зефир и пряник', 'robots' => 'noindex', 'header' => 'checkout', 'drawer' => false]);
?>
    <main class="g" style="padding-block:40px 120px">
<?php if (!$lines): ?>
        <div class="w" style="display:flex;flex-direction:column;gap:24px;align-items:flex-start">
            <h1 class="d1b">Корзина <em>пуста</em></h1>
            <p class="lead">Добавьте что-нибудь из каталога, и вернёмся к оформлению.</p>
            <a class="btn btn-main" href="catalog.php">Перейти в каталог</a>
        </div>
<?php else:
    $card = cart_card();
    $subtotal = cart_total();
    $fee = $old['ship'] === 'courier' ? $fee_courier : 0;
    $err = fn (string $k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';
    ?>
        <form class="w co" id="form" method="post" action="checkout.php" novalidate
              data-subtotal="<?= $subtotal ?>" data-fee="<?= $fee_courier ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="date" id="fDate" value="<?= e($old['date']) ?>">
            <input type="hidden" name="hour" id="fHour" value="<?= e($old['hour']) ?>">
            <div class="main">
                <h1 class="d1b" style="font-size:clamp(44px,5vw,72px)">Оформление <em>без регистрации</em></h1>
                <?php if (isset($errors['form'])): ?><p class="demo-note" role="alert" style="border-color:var(--bad);color:var(--bad)"><?= e($errors['form']) ?></p><?php endif; ?>

                <fieldset class="opts">
                    <legend style="font:500 28px/1.1 var(--display);text-transform:none;letter-spacing:0;color:var(--choc);padding-bottom:8px">Как получить</legend>
                    <label class="opt"><input type="radio" name="ship" value="pickup"<?= $old['ship'] === 'pickup' ? ' checked' : '' ?>><span class="t">Самовывоз<small><?= e(cfg('shop.address')) ?></small></span><span class="pr">0&nbsp;₽</span></label>
                    <label class="opt"><input type="radio" name="ship" value="courier"<?= $old['ship'] === 'courier' ? ' checked' : '' ?>><span class="t">Курьером<small>По Москве в пределах МКАД, с хладоэлементом</small></span><span class="pr"><?= rub($fee_courier) ?></span></label>
                    <div class="field" id="addrBox"<?= $old['ship'] === 'courier' ? '' : ' hidden' ?> style="padding-top:20px;flex:none">
                        <label for="addr">Адрес доставки</label>
                        <input class="inp" id="addr" name="addr" autocomplete="street-address" placeholder="Улица, дом, квартира, подъезд, этаж" value="<?= e($old['addr']) ?>"<?= $err('addr') ?>>
                        <span class="hint e" id="addrE"<?= isset($errors['addr']) ? '' : ' hidden' ?>><?= e($errors['addr'] ?? 'Укажите адрес доставки') ?></span>
                    </div>
                </fieldset>

                <section aria-label="Дата и время" class="dtp" id="dtp">
                    <div class="dtp-head">
                        <h2 style="font:500 28px/1.1 var(--display)">Когда · <em style="color:var(--muted)" id="dtpMonth"></em></h2>
                        <div class="dtp-nav">
                            <button class="ib" type="button" id="prev" aria-label="Раньше"><svg class="i" viewBox="0 0 24 24" aria-hidden="true" style="transform:scaleX(-1)"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
                            <button class="ib" type="button" id="next" aria-label="Позже"><svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
                        </div>
                    </div>
                    <div class="dtp-rail" id="rail" role="group" aria-label="Дата"></div>
                    <div class="dtp-times" id="times" role="group" aria-label="Время"></div>
                    <div class="dtp-sum"><svg class="i" viewBox="0 0 24 24" aria-hidden="true" style="width:18px;height:18px"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg><span id="dtpSum">—</span></div>
                    <span class="hint e" id="dtpE"<?= isset($errors['slot']) ? '' : ' hidden' ?>><?= e($errors['slot'] ?? 'Выберите день и время') ?></span>
                    <noscript><p class="hint e">Для выбора времени нужен JavaScript. Позвоните нам: <?= e(cfg('shop.phone')) ?>.</p></noscript>
                </section>

                <section aria-label="Контакты" style="display:flex;flex-direction:column;gap:22px">
                    <h2 style="font:500 28px/1.1 var(--display)" id="whoH"><?= $old['ship'] === 'courier' ? 'Кто получит' : 'Кто заберёт' ?></h2>
                    <div class="row">
                        <div class="field"><label for="name">Имя</label><input class="inp" id="name" name="name" autocomplete="given-name" maxlength="60" value="<?= e($old['name']) ?>"<?= $err('name') ?>><span class="hint e" id="nameE"<?= isset($errors['name']) ? '' : ' hidden' ?>><?= e($errors['name'] ?? 'Введите имя') ?></span></div>
                        <div class="field"><label for="phone">Телефон</label><input class="inp tn" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7 (900) 000-00-00" value="<?= e($old['phone']) ?>"<?= $err('phone') ?>><span class="hint e" id="phoneE"<?= isset($errors['phone']) ? '' : ' hidden' ?>><?= e($errors['phone'] ?? 'Номер неполный: нужно 10 цифр после +7') ?></span></div>
                    </div>
                    <div class="field" style="flex:none"><label for="comment">Комментарий к заказу</label><input class="inp" id="comment" name="comment" placeholder="Необязательно" maxlength="200" value="<?= e($old['comment']) ?>"></div>
                </section>
            </div>

            <aside class="side" aria-label="Заказ">
                <h2 style="font:500 28px/1.1 var(--display);margin-bottom:6px">Заказ</h2>
                <div style="display:flex;flex-direction:column;gap:14px">
                    <?php foreach ($lines as $l): ?><div class="sline"><span><?= e($l['name']) ?> × <?= $l['q'] ?></span><span><?= rub($l['sum']) ?></span></div><?php endforeach; ?>
                    <?php if ($card['on']): ?><div class="sline"><span>Открытка от руки<?= $card['text'] !== '' ? ': <span class="hand" style="font-size:18px">' . e($card['text']) . '</span>' : '' ?></span><span style="color:var(--muted)">Бесплатно</span></div><?php endif; ?>
                </div>
                <div class="sline" style="padding-bottom:14px;border-bottom:1px solid var(--line)"><span>Получение</span><span id="shipSum"><?= rub($fee) ?></span></div>
                <div class="sum tot"><span>Итого</span><span id="totSum"><?= rub($subtotal + $fee) ?></span></div>
                <button class="btn btn-main btn-block" type="submit" id="pay" style="margin-top:8px">Оформить заказ</button>
                <p class="small">Оплата при получении: картой или наличными. Мы позвоним или напишем, чтобы подтвердить заказ.</p>
                <a class="small" href="catalog.php" style="text-decoration:underline">Изменить состав заказа</a>
            </aside>
        </form>
        <script>window.__CHECKOUT__ = <?= json_encode(['schedule' => schedule_data(), 'sel' => ['date' => $old['date'], 'hour' => $old['hour'] === '' ? null : (int) $old['hour']]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php endif; ?>
    </main>
<?php page_end($lines ? ['js/checkout.js'] : []); ?>
