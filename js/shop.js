/* Зефир и пряник — клиентская часть магазина.
   Корзина живёт на сервере (PHP-сессия): здесь только отрисовка и запросы к api/cart.php.
   Цены и названия приходят с сервера, браузеру они не доверяются. */
(function () {
    'use strict';

    var RM = window.matchMedia('(prefers-reduced-motion: reduce)');
    var root = document.documentElement;
    var state = window.__CART__ || { lines: [], count: 0, total: 0, card: { on: false, text: '' } };

    function $(s, r) { return (r || document).querySelector(s); }
    function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function rub(n) { return Math.round(n).toLocaleString('ru-RU').replace(/\s/g, ' ') + ' ₽'; }

    root.classList.add('js');

    /* ---------- Тема ---------- */
    try { var th = localStorage.getItem('theme'); if (th === 'dark' || th === 'light') root.dataset.theme = th; } catch (_) { }
    var tb = $('#themeBtn');
    if (tb) tb.addEventListener('click', function () {
        var dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        root.dataset.theme = dark ? 'light' : 'dark';
        try { localStorage.setItem('theme', root.dataset.theme); } catch (_) { }
    });

    /* ---------- Запросы к корзине ---------- */
    var csrf = (($('meta[name="csrf-token"]') || {}).content) || '';
    function api(body) {
        return fetch('api/cart.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body)
        }).then(function (r) {
            if (r.status === 403) { location.reload(); throw new Error('csrf'); }
            return r.json();
        }).then(function (j) {
            if (!j.ok) throw new Error(j.error || 'fail');
            state = j.cart; paint(); return state;
        }).catch(function (e) {
            if (e.message !== 'csrf') toast('Не получилось обновить корзину. Обновите страницу.');
            throw e;
        });
    }
    function add(id, vid, q, cardText) {
        return api({ action: 'add', id: id, v: vid || '', q: q || 1, card_text: cardText || '' }).then(function () {
            var l = state.lines.filter(function (x) { return x.id === id; })[0];
            toast((l ? l.name.split(' · ')[0] : 'Товар') + ' в корзине');
        }).catch(function () { });
    }
    function setQty(id, vid, q) { return api({ action: 'set', id: id, v: vid, q: q }).catch(function () { }); }

    /* ---------- Отрисовка ---------- */
    var ICON = {
        plus: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12M12 6v12"/></svg>',
        minus: '<svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12"/></svg>'
    };

    function paint() { renderBadges(); renderDrawer(); syncCards(); document.dispatchEvent(new CustomEvent('cart:change', { detail: state })); }

    function renderBadges() {
        var c = $('#cnt'); if (c) { c.textContent = state.count; c.hidden = !state.count; }
        var m = $('#mobileCta');
        if (m) {
            m.hidden = !state.count;
            m.firstElementChild.textContent = 'Корзина · ' + state.count + ' · ' + rub(state.total).replace(/ /g, ' ');
            document.body.style.paddingBottom = state.count && matchMedia('(max-width:720px)').matches ? '84px' : '';
        }
    }

    function renderDrawer() {
        var body = $('#drawerBody'), foot = $('#drawerFoot'); if (!body) return;
        if (!state.lines.length) {
            body.innerHTML = '<div class="empty-cart"><p>Пока пусто. Загляните в каталог: пирожные собираем утром в день выдачи.</p><a class="btn btn-line btn-sm" href="catalog.php">Перейти в каталог</a></div>';
            foot.innerHTML = ''; return;
        }
        var had = document.activeElement && document.activeElement.id === 'cardText';
        body.innerHTML = state.lines.map(function (x) {
            return '<div class="ci" data-id="' + esc(x.id) + '" data-v="' + esc(x.v) + '">' +
                '<a class="ph" href="' + esc(x.url) + '"><img src="' + esc(x.img) + '" alt="" loading="lazy"></a>' +
                '<div class="m"><div class="r1"><b>' + esc(x.name) + '</b><span class="tn" style="font-weight:500;white-space:nowrap">' + rub(x.sum) + '</span></div>' +
                '<div class="r2"><div class="qty sm" role="group" aria-label="Количество"><button type="button" data-cq="-1" aria-label="Меньше">' + ICON.minus + '</button><output class="tn">' + x.q + '</output><button type="button" data-cq="1" aria-label="Больше">' + ICON.plus + '</button></div>' +
                '<span class="small tn">' + rub(x.price) + ' за шт.</span></div>' +
                '<button class="rm" type="button" data-rm style="align-self:flex-start">Убрать</button></div></div>';
        }).join('') +
            '<label class="opt" style="border-top:1px solid var(--line)"><input type="checkbox" id="cardOn"' + (state.card.on ? ' checked' : '') + '><span class="t">Открытка от руки</span><span class="small">Бесплатно</span></label>' +
            (state.card.on ? '<div class="field" style="padding:4px 0 16px"><label for="cardText">Текст открытки</label><input class="inp hand-i" id="cardText" maxlength="90" value="' + esc(state.card.text) + '"><span class="hint">Напишем от руки, до 90 знаков</span></div>' : '');
        foot.innerHTML =
            '<div class="sum tot"><span>Итого</span><span>' + rub(state.total) + '</span></div>' +
            '<a class="btn btn-main btn-block" href="checkout.php">Оформить заказ</a>' +
            '<span class="small" style="text-align:center">Стоимость доставки покажем на следующем шаге</span>';
        if (had) { var t = $('#cardText'); if (t) t.focus(); }
    }

    /* Кнопка «В корзину» ⇄ счётчик под карточками товаров с одним форматом */
    function syncCards() {
        $$('.card[data-simple]').forEach(function (c) {
            var id = c.dataset.id, box = $('.buy', c), q = 0;
            state.lines.forEach(function (l) { if (l.id === id) q += l.q; });
            var has = !!$('[data-pm]', box);
            if (q && has) { var o = $('output', box); if (o.textContent != q) o.textContent = q; return; }
            if (!q && !has) return;
            var focused = box.contains(document.activeElement), name = $('h2', c).textContent;
            box.innerHTML = q
                ? '<div class="qty pq" role="group" aria-label="' + esc(name) + ': количество в корзине"><button type="button" data-pm="-1" aria-label="Меньше">' + ICON.minus + '</button><output class="tn" aria-live="polite">' + q + '</output><button type="button" data-pm="1" aria-label="Больше">' + ICON.plus + '</button></div><span class="small">в корзине</span>'
                : '<button class="btn btn-main btn-sm" type="button" data-add="' + esc(id) + '">В корзину</button>';
            if (focused) { var f = $('button', box); if (f) f.focus({ preventScroll: true }); }
        });
    }

    /* ---------- Шторка ---------- */
    var lastFocus;
    function openCart() { if (!$('#drawer')) { location.href = 'checkout.php'; return; } lastFocus = document.activeElement; document.body.classList.add('cart-open'); setTimeout(function () { var x = $('#drawer [data-close-cart]'); if (x) x.focus({ preventScroll: true }); }, 60); }
    function closeCart() { document.body.classList.remove('cart-open'); if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true }); }

    var tt;
    function toast(text) {
        var el = $('#toast'); if (!el) return;
        $('#toastText').textContent = text;
        el.classList.remove('show'); void el.offsetWidth; el.classList.add('show');
        clearTimeout(tt); tt = setTimeout(function () { el.classList.remove('show'); }, 2500);
    }

    /* ---------- События ---------- */
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('[data-open-cart]')) { openCart(); return; }
        if (t.closest('[data-close-cart]')) { closeCart(); return; }
        var a = t.closest('[data-add]');
        if (a) { add(a.dataset.add, '', 1); return; }
        var pm = t.closest('[data-pm]');
        if (pm) {
            var id = pm.closest('.card').dataset.id, l = state.lines.filter(function (x) { return x.id === id; })[0];
            if (l) setQty(id, l.v, l.q + (+pm.dataset.pm));
            return;
        }
        var ci = t.closest('.ci');
        if (ci && t.closest('#drawer')) {
            var cid = ci.dataset.id, vid = ci.dataset.v, ln = state.lines.filter(function (x) { return x.id === cid && x.v === vid; })[0]; if (!ln) return;
            if (t.closest('[data-rm]')) setQty(cid, vid, 0);
            else { var b = t.closest('[data-cq]'); if (b) setQty(cid, vid, ln.q + (+b.dataset.cq)); }
        }
    });
    document.addEventListener('change', function (e) {
        if (e.target.id === 'cardOn') api({ action: 'card', on: e.target.checked, text: state.card.text }).catch(function () { });
    });
    var ct;
    document.addEventListener('input', function (e) {
        if (e.target.id !== 'cardText') return;
        var v = e.target.value; clearTimeout(ct);
        ct = setTimeout(function () { fetch('api/cart.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ action: 'card', on: true, text: v }) }).then(function (r) { return r.json(); }).then(function (j) { if (j.ok) state = j.cart; }); }, 400);
    });
    window.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.body.classList.contains('cart-open')) closeCart(); });

    /* ---------- Появление секций ---------- */
    var els = $$('.rv');
    if ('IntersectionObserver' in window && !RM.matches) {
        var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('on'); io.unobserve(e.target); } }); }, { threshold: .06, rootMargin: '0px 0px -40px 0px' });
        els.forEach(function (s) { io.observe(s); });
    } else els.forEach(function (s) { s.classList.add('on'); });

    /* ---------- Телефон: маска +7 (900) 000-00-00 ---------- */
    function telDigits(v) { var d = String(v).replace(/^\s*\+7/, '').replace(/\D/g, ''); if (d.length === 11 && /^[78]/.test(d)) d = d.slice(1); return d.slice(0, 10); }
    function telFmt(d) { if (!d) return ''; var r = '+7 (' + d.slice(0, 3); if (d.length >= 3) r += ')'; if (d.length > 3) r += ' ' + d.slice(3, 6); if (d.length > 6) r += '-' + d.slice(6, 8); if (d.length > 8) r += '-' + d.slice(8, 10); return r; }
    function maskTel(inp) {
        inp.addEventListener('input', function () { inp.value = telFmt(telDigits(inp.value)); inp.removeAttribute('aria-invalid'); });
        inp.addEventListener('focus', function () { if (!inp.value) inp.value = '+7 ('; });
        inp.addEventListener('blur', function () { if (!telDigits(inp.value)) inp.value = ''; });
        if (inp.value) inp.value = telFmt(telDigits(inp.value));
    }

    renderBadges(); renderDrawer();
    window.Shop = { $: $, $$: $$, esc: esc, rub: rub, add: add, toast: toast, ICON: ICON, state: function () { return state; }, maskTel: maskTel, telDigits: telDigits };
})();
