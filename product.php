<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$p = product($_GET['id'] ?? null);
if (!$p) {
    redirect('catalog.php', 302);
}
$vs = variants($p);
$buy = buyable($p);

page_start([
    'title' => $p['name'] . ' — Зефир и пряник',
    'description' => $p['name'] . ': ' . ($p['long'] ?? $p['short']) . ' Кондитерская «Зефир и пряник», Москва.',
    'canonical' => 'https://zefiripryanik.ru/' . product_url($p),
    'active' => 'catalog',
]);
?>
    <main class="g" style="padding-block:24px 120px">
        <div class="w" style="display:flex;flex-direction:column;gap:32px">
            <nav class="crumbs" aria-label="Навигация"><a href="catalog.php">Каталог</a><span aria-hidden="true">/</span><span><?= e($p['name']) ?></span></nav>

            <div class="pdp" data-product="<?= e($p['id']) ?>">
                <div class="gal"><div class="photo">
                    <img src="<?= e($p['img']) ?>" alt="<?= e($p['name']) ?>" width="800" height="640">
                    <span class="badges"><?= !empty($p['pp']) ? '<span class="badge pp">Без сахара</span>' : '' ?><?= !empty($p['order']) ? '<span class="badge ord">На заказ</span>' : '' ?></span>
                </div></div>

                <div class="info">
                    <div style="display:flex;flex-direction:column;gap:14px">
                        <?php if (!empty($p['new'])): ?><span class="label">Новинка</span><?php endif; ?>
                        <h1><?= e($p['name']) ?></h1>
                        <p class="lead" style="font-size:17px;line-height:28px"><?= e($p['long'] ?? $p['short']) ?></p>
                    </div>

<?php if ($buy): ?>
                    <?php if (count($vs) > 1): ?>
                    <fieldset class="opts"><legend>Формат</legend>
                        <?php foreach ($vs as $i => $v): ?>
                        <label class="opt"><input type="radio" name="fmt" value="<?= e($v['id']) ?>" data-price="<?= (int) $v['price'] ?>"<?= $i === 0 ? ' checked' : '' ?>><span class="t"><?= e($v['label']) ?><?= $v['sub'] !== '' ? '<small>' . e($v['sub']) . '</small>' : '' ?></span><span class="pr"><?= rub((int) $v['price']) ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php endif; ?>
                    <?php if ($p['k'] === 'cake'): ?>
                    <div class="made"><svg class="i" aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>Соберём утром в день выдачи. Хранить 72 часа в холоде.</div>
                    <?php endif; ?>
                    <div class="field" style="flex:none"><label for="note">Текст открытки</label><input class="inp hand-i" id="note" maxlength="90" placeholder="Необязательно"><span class="hint">Напишем от руки, до 90 знаков</span></div>
                    <div class="buyrow">
                        <div class="qty lg" role="group" aria-label="Количество"><button type="button" data-q="-1" aria-label="Меньше"><?= ICON_MINUS ?></button><output class="tn" id="qv">1</output><button type="button" data-q="1" aria-label="Больше"><?= ICON_PLUS ?></button></div>
                        <button class="btn btn-main" type="button" id="addBtn" data-price="<?= (int) $vs[0]['price'] ?>">В корзину · <span class="tn" id="sumv"><?= rub((int) $vs[0]['price']) ?></span></button>
                    </div>
<?php else: ?>
                    <div style="display:flex;align-items:baseline;gap:16px;flex-wrap:wrap"><span class="price" style="font-size:22px"><?= e(price_text($p)) ?></span></div>
                    <p class="small"><?= !empty($p['out']) ? 'Сейчас нет в наличии. Напишите нам, и мы сообщим о поступлении.' : (!empty($p['order']) ? 'Делаем на заказ. Цену и сроки назовём после обсуждения, заказывайте минимум за 3 дня.' : 'Цену назовём по запросу.') ?></p>
                    <div class="buyrow"><a class="btn btn-main" href="info.php#contacts"><?= !empty($p['out']) ? 'Сообщить о поступлении' : (!empty($p['order']) ? 'Обсудить заказ' : 'Уточнить цену') ?></a></div>
<?php endif; ?>

                    <?php if (!empty($p['allergens'])): ?><dl class="dl"><dt>Аллергены</dt><dd><?= e($p['allergens']) ?></dd></dl><?php endif; ?>
                </div>
            </div>
        </div>
    </main>
<?php page_end(['js/product.js']); ?>
