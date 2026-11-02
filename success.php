<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

/* Показываем только заказ, оформленный в этой сессии */
$o = isset($_SESSION['last_order']) ? order_get((int) $_SESSION['last_order']) : null;

page_start(['title' => 'Заказ оформлен — Зефир и пряник', 'robots' => 'noindex']);
?>
    <main class="g" style="padding-block:48px 120px">
<?php if (!$o): ?>
        <div class="w" style="display:flex;flex-direction:column;gap:24px;align-items:flex-start">
            <h1 class="d1b">Заказа <em>пока нет</em></h1>
            <p class="lead">Здесь появится сводка, когда вы оформите заказ.</p>
            <a class="btn btn-main" href="catalog.php">Перейти в каталог</a>
        </div>
<?php else: ?>
        <div class="done">
            <div class="a">
                <img src="images/logo.png" alt="Зефир и пряник" width="160" height="160">
                <span class="stamp"><svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:16px;height:16px;stroke-width:2"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Заказ принят</span>
                <h1 class="d1b" style="font-size:clamp(44px,5.5vw,76px)">Спасибо, <?= e($o['name']) ?>! <em>Заказ принят.</em></h1>
                <p class="lead">Номер заказа <b class="tn" style="color:var(--choc);font-weight:600">№&nbsp;<?= e(order_number((int) $o['id'])) ?></b>. Свяжемся с вами по номеру <span class="tn" style="white-space:nowrap"><?= e($o['phone']) ?></span>, чтобы подтвердить заказ. Оплата при получении.</p>
                <div class="facts-row">
                    <div><span class="small" style="letter-spacing:.06em;text-transform:uppercase">Когда</span><b><?= e(day_label($o['slot_date'])) ?></b><span class="tn"><?= (int) $o['slot_hour'] ?>:00–<?= (int) $o['slot_hour'] + 1 ?>:00</span></div>
                    <div><span class="small" style="letter-spacing:.06em;text-transform:uppercase">Где</span><b><?= $o['shipping'] === 'courier' ? 'Курьером' : 'Самовывоз' ?></b><span><?= e($o['address']) ?></span></div>
                </div>
                <div class="acts" style="margin-top:8px">
                    <a class="btn btn-main" href="catalog.php">Вернуться в каталог</a>
                    <a class="btn btn-text" href="info.php">Как нас найти <?= ICON_ARROW ?></a>
                </div>
            </div>
            <aside class="b" aria-label="Состав заказа">
                <h2 style="font:500 28px/1.1 var(--display);margin-bottom:4px">Ваш заказ</h2>
                <div style="display:flex;flex-direction:column;gap:14px">
                    <?php foreach ($o['items'] as $it): ?>
                    <div class="thumb-line"><img src="<?= e($it['img']) ?>" alt=""><span><?= e($it['name']) ?> × <?= (int) $it['q'] ?></span><span class="tn"><?= rub((int) $it['sum']) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <?php if ($o['card_text'] !== ''): ?><div class="sline" style="padding-top:14px;border-top:1px solid var(--line)"><span>Открытка от руки</span><span style="color:var(--muted)">Бесплатно</span></div><?php endif; ?>
                <?php if ((int) $o['fee']): ?><div class="sline"><span>Доставка</span><span><?= rub((int) $o['fee']) ?></span></div><?php endif; ?>
                <div class="sum tot" style="align-items:baseline"><span>Итого</span><span><?= rub((int) $o['total']) ?></span></div>
            </aside>
        </div>
<?php endif; ?>
    </main>
<?php page_end(); ?>
