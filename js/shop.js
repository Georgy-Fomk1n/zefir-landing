/* Зефир и пряник — ядро магазина: корзина, шапка, шторка, карточки.
   Корзина живёт в localStorage браузера. Бэкенда нет: подключение приёма заказов и оплаты
   делается в одном месте — Shop.submitOrder() ниже. */
(function () {
    'use strict';

    var SHOP = {
        demo: true,                       // true: оплата и отправка заказа ещё не подключены
        phone: '+7 (968) 479-15-20',
        phoneHref: 'tel:+79684791520',
        telegram: 'https://t.me/Krasolda_Tihonovna',
        vk: 'https://vk.ru/zefir_i_pryanik.moscow',
        max: 'https://max.ru/id772442265466_biz',
        flowwow: 'https://flowwow.com/shop/zefir-i-pryanik',
        address: 'ул. Базовская, 17, Москва',
        courierFee: 390,
        // график: 0 = воскресенье … 6 = суббота; понедельник выходной
        hours: { open: 10, close: 21, closedDays: [1] }
    };

    var KEY_CART = 'zp_cart', KEY_CARD = 'zp_card', KEY_ORDER = 'zp_last_order', KEY_THEME = 'theme';
    var RM = window.matchMedia('(prefers-reduced-motion: reduce)');
    var root = document.documentElement;

    function $(s, r) { return (r || document).querySelector(s); }
    function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function rub(n) { return Math.round(n).toLocaleString('ru-RU').replace(/\s/g, ' ') + ' ₽'; }
    function store(k, v) { try { if (v === undefined) { var s = localStorage.getItem(k); return s ? JSON.parse(s) : null; } localStorage.setItem(k, JSON.stringify(v)); } catch (_) { return null; } }
    function drop(k) { try { localStorage.removeItem(k); } catch (_) { } }

    root.classList.add('js');

    /* ---------- Тема ---------- */
    try { var th = localStorage.getItem(KEY_THEME); if (th === 'dark' || th === 'light') root.dataset.theme = th; } catch (_) { }
    function toggleTheme() {
        var dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        root.dataset.theme = dark ? 'light' : 'dark';
        try { localStorage.setItem(KEY_THEME, root.dataset.theme); } catch (_) { }
    }

    /* ---------- Товары ---------- */
    var P = window.PRODUCTS || [];
    function product(id) { for (var i = 0; i < P.length; i++) if (P[i].id === id) return P[i]; return null; }
    function variants(p) { return p.variants && p.variants.length ? p.variants : [{ id: 'one', label: p.name, sub: '', price: p.price }]; }
    function variant(p, vid) { var vs = variants(p); for (var i = 0; i < vs.length; i++) if (vs[i].id === vid) return vs[i]; return vs[0]; }
    function buyable(p) { return typeof p.price === 'number' && !p.out && !p.order; }
    function url(p) { return 'product.html?id=' + encodeURIComponent(p.id); }
    function priceText(p) {
        if (p.out) return 'Нет в наличии';
        if (p.priceNote) return p.priceNote;
        if (typeof p.price !== 'number') return 'Цена по запросу';
        return (p.priceFrom ? 'от ' : '') + rub(p.price);
    }

    /* ---------- Корзина ---------- */
    var cart = (store(KEY_CART) || []).filter(function (l) { var p = product(l.id); return p && l.q > 0; });
    var card = store(KEY_CARD) || { on: false, text: '' };
    function save() { store(KEY_CART, cart); store(KEY_CARD, card); }
    function lines() {
        return cart.map(function (l) {
            var p = product(l.id), v = variant(p, l.v);
            var name = variants(p).length > 1 ? p.name + ' · ' + v.label.toLowerCase() : p.name;
            return { l: l, p: p, v: v, name: name, price: v.price, sum: v.price * l.q };
        });
    }
    function total() { return lines().reduce(function (s, x) { return s + x.sum; }, 0); }
    function count() { return cart.reduce(function (s, l) { return s + l.q; }, 0); }
    function qtyOf(id) { return cart.filter(function (l) { return l.id === id; }).reduce(function (s, l) { return s + l.q; }, 0); }
    function find(id, vid) { for (var i = 0; i < cart.length; i++) if (cart[i].id === id && cart[i].v === vid) return cart[i]; return null; }
    function changed() { save(); renderDrawer(); renderBadges(); syncBuy(); document.dispatchEvent(new CustomEvent('cart:change')); }
    function add(id, vid, q, silent) {
        var p = product(id); if (!p || !buyable(p)) return;
        vid = variant(p, vid).id; q = q || 1;
        var l = find(id, vid);
        if (l) l.q = Math.min(99, l.q + q); else cart.push({ id: id, v: vid, q: q });
        changed();
        if (!silent) toast(p.name + ' в корзине');
    }
    function setQty(id, vid, q) {
        var l = find(id, vid); if (!l) return;
        l.q = Math.min(99, q);
        if (l.q <= 0) cart.splice(cart.indexOf(l), 1);
        changed();
    }
    function clearCart() { cart = []; card = { on: false, text: '' }; changed(); }
    function setCard(c) { card = { on: !!c.on, text: c.text || '' }; save(); renderDrawer(); document.dispatchEvent(new CustomEvent('cart:change')); }

    /* ---------- Шапка, подвал, шторка ---------- */
    var ICON = {
        bag: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 8h14l-1.2 11.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg>',
        moon: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 1 1 9.5 4 6.5 6.5 0 0 0 20 14.5z"/></svg>',
        x: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        plus: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12M12 6v12"/></svg>',
        minus: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12"/></svg>',
        check: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="m5 12 5 5 9-10"/></svg>',
        arrow: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M5 12h14M13 6l6 6-6 6"/></svg>'
    };

    function openStatus() {
        var n = new Date(), t = n.getHours() + n.getMinutes() / 60, h = SHOP.hours;
        return h.closedDays.indexOf(n.getDay()) < 0 && t >= h.open && t < h.close;
    }

    function mountChrome(opts) {
        opts = opts || {};
        var a = opts.active || '';
        function cur(k) { return a === k ? ' aria-current="page"' : ''; }
        var head = $('#site-header');
        if (head) {
            head.outerHTML =
                '<header class="top g"><div class="bar glass">' +
                '<a class="logo" href="index.html" aria-label="Зефир и пряник — на главную"><img src="images/logo.png" alt="" width="40" height="40"><span>Зефир <i>и</i> пряник</span></a>' +
                '<nav aria-label="Разделы"><a href="catalog.html"' + cur('catalog') + '>Каталог</a><a href="index.html#cakes">Торты на заказ</a><a href="info.html"' + cur('info') + '>Доставка и контакты</a></nav>' +
                '<div class="tools"><span class="open-now" id="openNow"></span>' +
                '<button class="ib" id="themeBtn" type="button" aria-label="Сменить тему">' + ICON.moon + '</button>' +
                '<button class="ib" type="button" data-open-cart aria-label="Открыть корзину">' + ICON.bag + '<span class="n tn" id="cnt" hidden>0</span></button>' +
                '</div></div></header>';
        }
        var foot = $('#site-footer');
        if (foot) {
            foot.outerHTML =
                '<footer class="site g"><div class="w"><div style="display:flex;flex-direction:column;gap:10px"><img src="images/logo.png" alt="Зефир и пряник" width="150" height="150"><span class="small">Кондитерская ручной работы · ' + SHOP.address + '</span></div>' +
                '<nav aria-label="Мы в сети"><a href="' + SHOP.telegram + '">Telegram</a><a href="' + SHOP.vk + '">ВКонтакте</a><a href="' + SHOP.max + '">MAX</a><a href="' + SHOP.flowwow + '">Flowwow</a></nav></div>' +
                '<div class="w legal"><p style="font-weight:600">ИНДИВИДУАЛЬНЫЙ ПРЕДПРИНИМАТЕЛЬ ГУБАНОВА ТАТЬЯНА СЕРГЕЕВНА</p><p>ОГРНИП 323774600785438 от <a href="https://zachestnyibiznes.ru/day/2023-11-28">28.11.2023</a> · ИНН 772442265466</p>' +
                '<p style="margin-top:8px">Сайт не является публичной офертой, цены представлены для ознакомления и могут быть изменены в одностороннем порядке</p>' +
                '<p style="margin-top:8px">© ' + new Date().getFullYear() + ' Зефир и пряник. Москва. Все права защищены.</p></div></footer>';
        }
        if (!opts.noDrawer) {
            var d = document.createElement('div');
            d.innerHTML =
                '<div class="scrim" data-close-cart></div>' +
                '<aside class="drawer glass" id="drawer" role="dialog" aria-modal="true" aria-label="Корзина">' +
                '<div class="drawer-h"><h2>Корзина</h2><button class="ib" type="button" data-close-cart aria-label="Закрыть">' + ICON.x + '</button></div>' +
                '<div class="drawer-b" id="drawerBody"></div><div class="drawer-f" id="drawerFoot"></div></aside>';
            while (d.firstChild) document.body.appendChild(d.firstChild);
        }
        var t = document.createElement('div');
        t.className = 'toast glass'; t.id = 'toast'; t.setAttribute('role', 'status');
        t.innerHTML = '<span class="dot">' + ICON.check + '</span><span id="toastText"></span>';
        document.body.appendChild(t);
        if (!opts.noDrawer) {
            var m = document.createElement('div');
            m.className = 'mobile-cta glass'; m.id = 'mobileCta'; m.hidden = true;
            m.innerHTML = '<button class="btn btn-main" type="button" data-open-cart></button>';
            document.body.appendChild(m);
        }

        var tb = $('#themeBtn'); if (tb) tb.addEventListener('click', toggleTheme);
        var on = $('#openNow');
        if (on) {
            var paint = function () { var o = openStatus(); on.textContent = o ? 'Открыто до ' + SHOP.hours.close + ':00' : 'Сейчас закрыто'; on.classList.toggle('closed', !o); };
            paint(); setInterval(paint, 60000);
        }
        renderDrawer(); renderBadges();
    }

    function renderBadges() {
        var n = count(), c = $('#cnt');
        if (c) { c.textContent = n; c.hidden = !n; }
        var b = $('#mobileCta');
        if (b) {
            b.hidden = !n;
            b.firstElementChild.innerHTML = 'Корзина · ' + n + ' · ' + rub(total());
            document.body.style.paddingBottom = n && matchMedia('(max-width:720px)').matches ? '84px' : '';
        }
    }

    function renderDrawer() {
        var body = $('#drawerBody'), foot = $('#drawerFoot'); if (!body) return;
        var ls = lines(), sum = total();
        if (!ls.length) {
            body.innerHTML = '<div class="empty-cart"><p>Пока пусто. Загляните в каталог: пирожные собираем утром в день выдачи.</p><a class="btn btn-line btn-sm" href="catalog.html">Перейти в каталог</a></div>';
            foot.innerHTML = '';
            return;
        }
        body.innerHTML = ls.map(function (x) {
            return '<div class="ci" data-id="' + esc(x.p.id) + '" data-v="' + esc(x.v.id) + '">' +
                '<a class="ph" href="' + url(x.p) + '"><img src="' + x.p.img + '" alt="" loading="lazy"></a>' +
                '<div class="m"><div class="r1"><b>' + esc(x.name) + '</b><span class="tn" style="font-weight:500;white-space:nowrap">' + rub(x.sum) + '</span></div>' +
                '<div class="r2"><div class="qty sm" role="group" aria-label="Количество"><button type="button" data-cq="-1" aria-label="Меньше">' + ICON.minus + '</button><output class="tn">' + x.l.q + '</output><button type="button" data-cq="1" aria-label="Больше">' + ICON.plus + '</button></div>' +
                '<span class="small tn">' + rub(x.price) + ' за шт.</span></div>' +
                '<button class="rm" type="button" data-rm style="align-self:flex-start">Убрать</button></div></div>';
        }).join('') +
            '<label class="opt" style="border-top:1px solid var(--line)"><input type="checkbox" id="cardOn"' + (card.on ? ' checked' : '') + '><span class="t">Открытка от руки' +
            (card.on && card.text ? '<small class="hand">' + esc(card.text) + '</small>' : '') + '</span><span class="small">Бесплатно</span></label>' +
            (card.on ? '<div class="field" style="padding:4px 0 16px"><label for="cardText">Текст открытки</label><input class="inp hand-i" id="cardText" maxlength="90" value="' + esc(card.text) + '"><span class="hint">Напишем от руки, до 90 знаков</span></div>' : '');
        foot.innerHTML =
            '<div class="sum tot"><span>Итого</span><span>' + rub(sum) + '</span></div>' +
            '<a class="btn btn-main btn-block" href="checkout.html">Оформить заказ</a>' +
            '<span class="small" style="text-align:center">Стоимость доставки покажем на следующем шаге</span>';
    }

    var lastFocus;
    function openCart() { lastFocus = document.activeElement; document.body.classList.add('cart-open'); setTimeout(function () { var x = $('#drawer [data-close-cart]'); if (x) x.focus({ preventScroll: true }); }, 60); }
    function closeCart() { document.body.classList.remove('cart-open'); if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true }); }

    var tt;
    function toast(text) {
        var el = $('#toast'); if (!el) return;
        $('#toastText').textContent = text;
        el.classList.remove('show'); void el.offsetWidth; el.classList.add('show');
        clearTimeout(tt); tt = setTimeout(function () { el.classList.remove('show'); }, 2500);
    }

    /* ---------- Карточки товаров ---------- */
    function buyHtml(p) {
        if (p.out) return '<a class="btn btn-line btn-sm" href="info.html#contacts">Сообщить о поступлении</a>';
        if (p.order || typeof p.price !== 'number') return '<a class="btn btn-' + (p.order ? 'main' : 'line') + ' btn-sm" href="info.html#contacts">' + (p.order ? 'Заказать' : 'Уточнить цену') + '</a>';
        if (variants(p).length > 1) return '<a class="btn btn-main btn-sm" href="' + url(p) + '">Выбрать формат</a>';
        var q = qtyOf(p.id);
        if (!q) return '<button class="btn btn-main btn-sm" type="button" data-add="' + esc(p.id) + '">В корзину</button>';
        return '<div class="qty pq" role="group" aria-label="' + esc(p.name) + ': количество в корзине"><button type="button" data-pm="-1" aria-label="Меньше">' + ICON.minus + '</button><output class="tn" aria-live="polite">' + q + '</output><button type="button" data-pm="1" aria-label="Больше">' + ICON.plus + '</button></div><span class="small">в корзине</span>';
    }
    function cardHtml(p, i) {
        return '<article class="card' + (p.out ? ' out' : '') + '" data-id="' + esc(p.id) + '">' +
            '<a class="ph" href="' + url(p) + '" aria-label="' + esc(p.name) + '"><div class="photo"><img src="' + p.img + '" alt="' + esc(p.name) + '" loading="' + (i > 3 ? 'lazy' : 'eager') + '" width="400" height="500">' +
            '<span class="badges">' + (p.isNew ? '<span class="badge">Новинка</span>' : '') + (p.pp ? '<span class="badge pp">Без сахара</span>' : '') + (p.order ? '<span class="badge ord">На заказ</span>' : '') + '</span></div></a>' +
            '<div class="head"><h2><a href="' + url(p) + '">' + esc(p.name) + '</a></h2><span class="price' + (typeof p.price !== 'number' || p.out ? ' muted' : '') + '">' + priceText(p) + '</span></div>' +
            '<p class="comp">' + esc(p.short) + '</p><div class="buy">' + buyHtml(p) + '</div></article>';
    }
    function syncBuy() {
        $$('.card[data-id]').forEach(function (c) {
            var p = product(c.dataset.id), box = $('.buy', c); if (!p || !box) return;
            var q = qtyOf(p.id), cur = $('[data-pm]', box) ? true : false;
            if (variants(p).length > 1 || !buyable(p)) return;
            if (q && cur) { var o = $('output', box); if (o.textContent != q) o.textContent = q; return; }
            if (!q && !cur) return;
            var had = box.contains(document.activeElement);
            box.innerHTML = buyHtml(p);
            if (had) { var f = $('button', box); if (f) f.focus({ preventScroll: true }); }
        });
    }

    /* ---------- События ---------- */
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('[data-open-cart]')) { openCart(); return; }
        if (t.closest('[data-close-cart]')) { closeCart(); return; }
        var a = t.closest('[data-add]');
        if (a) { add(a.dataset.add, null, 1); return; }
        var pm = t.closest('[data-pm]');
        if (pm) { var c = pm.closest('.card'), p = product(c.dataset.id), l = find(p.id, variants(p)[0].id); if (l) setQty(p.id, l.v, l.q + (+pm.dataset.pm)); return; }
        var ci = t.closest('.ci');
        if (ci && t.closest('#drawer')) {
            var id = ci.dataset.id, vid = ci.dataset.v, ln = find(id, vid); if (!ln) return;
            if (t.closest('[data-rm]')) setQty(id, vid, 0);
            else { var b = t.closest('[data-cq]'); if (b) setQty(id, vid, ln.q + (+b.dataset.cq)); }
        }
    });
    document.addEventListener('change', function (e) {
        if (e.target.id === 'cardOn') { card.on = e.target.checked; save(); renderDrawer(); document.dispatchEvent(new CustomEvent('cart:change')); }
    });
    document.addEventListener('input', function (e) {
        if (e.target.id === 'cardText') { card.text = e.target.value; save(); }
    });
    window.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.body.classList.contains('cart-open')) closeCart(); });
    window.addEventListener('storage', function (e) {
        if (e.key === KEY_CART || e.key === KEY_CARD) { cart = (store(KEY_CART) || []).filter(function (l) { return product(l.id) && l.q > 0; }); card = store(KEY_CARD) || card; renderDrawer(); renderBadges(); syncBuy(); document.dispatchEvent(new CustomEvent('cart:change')); }
    });

    /* ---------- Появление секций ---------- */
    function reveal() {
        var els = $$('.rv');
        if ('IntersectionObserver' in window && !RM.matches) {
            var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('on'); io.unobserve(e.target); } }); }, { threshold: .06, rootMargin: '0px 0px -40px 0px' });
            els.forEach(function (s) { io.observe(s); });
        } else els.forEach(function (s) { s.classList.add('on'); });
    }

    /* ---------- Телефон: маска +7 (900) 000-00-00 ---------- */
    function telDigits(v) { var d = String(v).replace(/^\s*\+7/, '').replace(/\D/g, ''); if (d.length === 11 && /^[78]/.test(d)) d = d.slice(1); return d.slice(0, 10); }
    function telFmt(d) { if (!d) return ''; var r = '+7 (' + d.slice(0, 3); if (d.length >= 3) r += ')'; if (d.length > 3) r += ' ' + d.slice(3, 6); if (d.length > 6) r += '-' + d.slice(6, 8); if (d.length > 8) r += '-' + d.slice(8, 10); return r; }
    function maskTel(inp) {
        inp.addEventListener('input', function () { inp.value = telFmt(telDigits(inp.value)); inp.removeAttribute('aria-invalid'); });
        inp.addEventListener('focus', function () { if (!inp.value) inp.value = '+7 ('; });
        inp.addEventListener('blur', function () { if (!telDigits(inp.value)) inp.value = ''; });
        if (inp.value) inp.value = telFmt(telDigits(inp.value));
    }

    /* ---------- Приём заказа ----------
       Здесь подключается реальная отправка: Telegram-бот, CRM, платёжный провайдер.
       Функция получает заказ и возвращает Promise<{ok:true}>. Пока заказ только сохраняется в браузере. */
    function submitOrder(order) {
        store(KEY_ORDER, order);
        return Promise.resolve({ ok: true });
    }
    function lastOrder() { return store(KEY_ORDER); }

    function init(opts) {
        mountChrome(opts);
        var grid = $('[data-products]');
        if (grid) { /* страницы сами рисуют сетку через Shop.cardHtml */ }
        reveal();
    }

    window.Shop = {
        cfg: SHOP, $: $, $$: $$, esc: esc, rub: rub, init: init, toast: toast, ICON: ICON,
        product: product, variants: variants, variant: variant, buyable: buyable, url: url, priceText: priceText,
        cardHtml: cardHtml, syncBuy: syncBuy,
        lines: lines, total: total, count: count, add: add, setQty: setQty, clear: clearCart,
        card: function () { return card; }, setCard: setCard,
        openCart: openCart, closeCart: closeCart, openStatus: openStatus,
        telDigits: telDigits, telFmt: telFmt, maskTel: maskTel,
        submitOrder: submitOrder, lastOrder: lastOrder, dropOrder: function () { drop(KEY_ORDER); }
    };
})();
