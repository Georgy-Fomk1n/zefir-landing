/* Оформление: выбор дня и часа, способ получения, телефон. Всё перепроверяет сервер (checkout.php). */
(function () {
    'use strict';
    var $ = Shop.$, $$ = Shop.$$, form = $('#form'), D = window.__CHECKOUT__;
    if (!form || !D) return;
    var subtotal = +form.dataset.subtotal, courierFee = +form.dataset.fee;

    /* ---- Способ получения ---- */
    var ship = ($('input[name="ship"]:checked') || {}).value || 'pickup';
    function totals() {
        var fee = ship === 'courier' ? courierFee : 0;
        $('#shipSum').innerHTML = Shop.rub(fee); $('#totSum').innerHTML = Shop.rub(subtotal + fee);
    }
    $$('input[name="ship"]').forEach(function (r) {
        r.addEventListener('change', function () {
            ship = r.value; $('#addrBox').hidden = ship !== 'courier';
            $('#whoH').textContent = ship === 'courier' ? 'Кто получит' : 'Кто заберёт';
            totals(); sumLine();
        });
    });

    /* ---- День и время ---- */
    var MG = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
        MN = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
        WD = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'], days = D.schedule.days, parts = D.schedule.parts;
    var selI = null, selH = null, rail = $('#rail');

    function freeIdx(from) { for (var i = from || 0; i < days.length; i++) if (days[i].ok.length) return i; return null; }
    if (D.sel && D.sel.date) { for (var k = 0; k < days.length; k++) if (days[k].date === D.sel.date && days[k].ok.indexOf(D.sel.hour) >= 0) { selI = k; selH = D.sel.hour; } }
    if (selI === null) { selI = freeIdx(0); selH = selI === null ? null : days[selI].ok[0]; }

    rail.innerHTML = days.map(function (d, i) {
        var f = d.ok.length > 0, note = i === 0 ? 'сегодня' : i === 1 ? 'завтра' : !f ? (d.closed ? 'выходной' : 'нет мест') : d.d === 1 ? MG[d.m].slice(0, 3) : '';
        return '<button type="button" class="dtp-day' + (d.dow === 0 || d.dow === 6 ? ' we' : '') + '" data-i="' + i + '" aria-pressed="false" aria-label="' + WD[d.dow] + ', ' + d.d + ' ' + MG[d.m] + (f ? '' : ', недоступно') + '"' + (f ? '' : ' disabled') + '><small>' + WD[d.dow] + '</small><b>' + d.d + '</b><i>' + note + '</i></button>';
    }).join('');

    function head() {
        var b = $$('.dtp-day', rail).filter(function (x) { return x.offsetLeft + x.offsetWidth / 2 >= rail.scrollLeft; })[0];
        var d = days[b ? +b.dataset.i : 0];
        $('#dtpMonth').textContent = MN[d.m] + ' ' + d.y;
        $('#prev').disabled = rail.scrollLeft < 4;
        $('#next').disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
    }
    function drawSlots() {
        if (selI === null) { $('#times').innerHTML = '<p class="small">Ближайшие дни недоступны. Позвоните нам, и мы подберём время.</p>'; return; }
        var ok = days[selI].ok;
        $('#times').innerHTML = parts.map(function (g) {
            return '<div class="dtp-g"><span>' + g[0] + '</span><div>' + g[1].map(function (h) {
                var a = ok.indexOf(h) >= 0;
                return '<button type="button" class="slot" data-h="' + h + '" aria-pressed="' + (a && h === selH) + '"' + (a ? '' : ' disabled') + '><b>' + h + ':00</b><small>' + (a ? 'до ' + (h + 1) + ':00' : 'занято') + '</small></button>';
            }).join('') + '</div></div>';
        }).join('');
    }
    function dayName(i) { var d = days[i]; return (i === 0 ? 'Сегодня' : i === 1 ? 'Завтра' : WD[d.dow]) + ', ' + d.d + ' ' + MG[d.m]; }
    function sumLine() {
        $('#fDate').value = selI === null ? '' : days[selI].date; $('#fHour').value = selH === null ? '' : selH;
        $('#dtpSum').innerHTML = selI === null || selH === null ? '—' : '<b>' + dayName(selI) + '</b>&nbsp;· ' + selH + ':00–' + (selH + 1) + ':00 · ' + (ship === 'courier' ? 'курьер' : 'самовывоз');
    }
    function pick(i, scroll) {
        selI = i; if (days[i].ok.indexOf(selH) < 0) selH = days[i].ok[0];
        $$('.dtp-day', rail).forEach(function (b) { b.setAttribute('aria-pressed', +b.dataset.i === i); });
        if (scroll) { var b = rail.querySelector('[data-i="' + i + '"]'); if (b) rail.scrollTo({ left: Math.max(0, b.offsetLeft - 76), behavior: 'auto' }); }
        drawSlots(); sumLine(); $('#dtpE').hidden = true;
    }
    rail.addEventListener('click', function (e) { var b = e.target.closest('.dtp-day'); if (b && !b.disabled) pick(+b.dataset.i, false); });
    $('#times').addEventListener('click', function (e) {
        var b = e.target.closest('.slot'); if (!b || b.disabled) return;
        selH = +b.dataset.h; $$('.slot', $('#times')).forEach(function (x) { x.setAttribute('aria-pressed', x === b); }); sumLine(); $('#dtpE').hidden = true;
    });
    var rs; rail.addEventListener('scroll', function () { cancelAnimationFrame(rs); rs = requestAnimationFrame(head); }, { passive: true });
    $('#prev').addEventListener('click', function () { rail.scrollBy({ left: -rail.clientWidth * .8, behavior: 'smooth' }); });
    $('#next').addEventListener('click', function () { rail.scrollBy({ left: rail.clientWidth * .8, behavior: 'smooth' }); });
    if (selI !== null) pick(selI, true); else { drawSlots(); sumLine(); }
    requestAnimationFrame(head); addEventListener('resize', head);

    /* ---- Поля ---- */
    Shop.maskTel($('#phone'));
    $$('#name,#addr').forEach(function (el) { el.addEventListener('input', function () { el.removeAttribute('aria-invalid'); $('#' + el.id + 'E').hidden = true; }); });
    $('#phone').addEventListener('input', function () { $('#phoneE').hidden = true; });

    /* ---- Проверка до отправки (то же самое проверит сервер) ---- */
    function bad(id, errId, on) { var el = $(id); if (on) el.setAttribute('aria-invalid', 'true'); else el.removeAttribute('aria-invalid'); $(errId).hidden = !on; return on; }
    form.addEventListener('submit', function (e) {
        var errs = [];
        if (bad('#name', '#nameE', !$('#name').value.trim())) errs.push('#name');
        if (bad('#phone', '#phoneE', Shop.telDigits($('#phone').value).length !== 10)) errs.push('#phone');
        if (ship === 'courier' && bad('#addr', '#addrE', $('#addr').value.trim().length < 5)) errs.push('#addr');
        if (selI === null || selH === null) { $('#dtpE').hidden = false; errs.push('#dtp'); }
        if (errs.length) { e.preventDefault(); var f = $(errs[0]); f.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (f.focus) f.focus({ preventScroll: true }); return; }
        var btn = $('#pay'); btn.disabled = true; btn.textContent = 'Отправляем…';
    });
})();
