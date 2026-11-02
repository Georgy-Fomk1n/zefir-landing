<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$cats = categories();
$cat = (string) ($_GET['cat'] ?? 'all');
if (!isset($cats[$cat])) {
    $cat = 'all';
}
$sort = (string) ($_GET['sort'] ?? 'pop');
if (!in_array($sort, ['pop', 'asc', 'desc'], true)) {
    $sort = 'pop';
}

$list = array_values(array_filter(products(), fn ($p) => in_category($p, $cat)));
if ($sort !== 'pop') {
    $dir = $sort === 'asc' ? 1 : -1;
    $idx = array_flip(array_column($list, 'id'));
    usort($list, function ($a, $b) use ($dir, $idx) {
        $x = is_int($a['price']) ? $a['price'] : null;
        $y = is_int($b['price']) ? $b['price'] : null;
        if ($x === null && $y === null) {
            return $idx[$a['id']] <=> $idx[$b['id']];
        }
        if ($x === null) {
            return 1;
        }
        if ($y === null) {
            return -1;
        }
        return (($x <=> $y) * $dir) ?: ($idx[$a['id']] <=> $idx[$b['id']]);
    });
}

function cat_url(string $c, string $sort): string
{
    $q = [];
    if ($c !== 'all') {
        $q['cat'] = $c;
    }
    if ($sort !== 'pop') {
        $q['sort'] = $sort;
    }
    return 'catalog.php' . ($q ? '?' . http_build_query($q) : '');
}

page_start([
    'title' => 'Каталог — Зефир и пряник: пирожные, зефир, пряники, торты',
    'description' => 'Каталог кондитерской «Зефир и пряник»: корпусные пирожные, ПП-десерты без сахара, зефир на натуральном пюре, имбирные пряники, торты на заказ. Москва, ул. Базовская, 17.',
    'canonical' => 'https://zefiripryanik.ru/catalog.php' . ($cat !== 'all' ? '?cat=' . $cat : ''),
    'active' => 'catalog',
]);
?>
    <main class="g" style="padding-block:24px 120px">
        <div class="w" style="display:flex;flex-direction:column;gap:32px">
            <nav class="crumbs" aria-label="Навигация"><a href="index.php">Главная</a><span aria-hidden="true">/</span><span>Каталог</span></nav>

            <div style="display:flex;flex-wrap:wrap;gap:24px 48px;align-items:flex-end;justify-content:space-between">
                <div style="display:flex;flex-direction:column;gap:16px">
                    <span class="eyebrow">Витрина на Базовской, 17</span>
                    <h1 class="d1b">Каталог</h1>
                </div>
                <p class="lead" style="font-size:17px;line-height:28px;max-width:44ch">Пирожные готовим небольшими партиями, чтобы не залёживались. Зефир, букеты и торты делаем на заказ.</p>
            </div>

            <div class="toolbar">
                <div class="chips" role="group" aria-label="Категория">
                    <?php foreach ($cats as $id => $label): $n = count(array_filter(products(), fn ($p) => in_category($p, $id))); ?>
                        <a class="chip-b" href="<?= e(cat_url($id, $sort)) ?>" aria-pressed="<?= $id === $cat ? 'true' : 'false' ?>"><?= e($label) ?><span><?= $n ?></span></a>
                    <?php endforeach; ?>
                </div>
                <form class="sort" method="get" action="catalog.php">
                    <?php if ($cat !== 'all'): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
                    <label for="sort">Сортировка</label>
                    <select class="sel" id="sort" name="sort" onchange="this.form.submit()">
                        <option value="pop"<?= $sort === 'pop' ? ' selected' : '' ?>>Сначала популярные</option>
                        <option value="asc"<?= $sort === 'asc' ? ' selected' : '' ?>>Сначала дешевле</option>
                        <option value="desc"<?= $sort === 'desc' ? ' selected' : '' ?>>Сначала дороже</option>
                    </select>
                    <noscript><button class="btn btn-line btn-sm" type="submit">Применить</button></noscript>
                </form>
            </div>

            <div class="grid" id="grid">
                <?php foreach ($list as $i => $p) { echo card_html($p, $i); } ?>
            </div>
            <?php if (!$list): ?><p class="empty">В этой категории пока ничего нет.</p><?php endif; ?>

            <section class="about" aria-label="О кондитерской">
                <h2>Кондитерская <em style="color:var(--choc-2)">на Базовской</em></h2>
                <p>«Зефир и пряник» — кондитерская в Москве на ул. Базовская, 17. Делаем зефир на натуральном фруктовом пюре, корпусные и ПП-пирожные, имбирные пряники с росписью, зефирные букеты, бенто-торты и торты на заказ. Работаем со вторника по воскресенье с 10:00 до 21:00.</p>
            </section>
        </div>
    </main>
<?php page_end(); ?>
