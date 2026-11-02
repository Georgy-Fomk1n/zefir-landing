/* Страница товара: выбор формата, количество, добавление в корзину (цены перепроверяет сервер). */
(function () {
    'use strict';
    var $ = Shop.$, root = $('[data-product]'), btn = $('#addBtn');
    if (!root || !btn) return;
    var qty = 1, vid = '', price = +btn.dataset.price;
    var first = $('input[name="fmt"]:checked', root); if (first) vid = first.value;

    function sum() { $('#sumv').innerHTML = Shop.rub(price * qty); }
    root.addEventListener('change', function (e) { if (e.target.name === 'fmt') { vid = e.target.value; price = +e.target.dataset.price; sum(); } });
    root.addEventListener('click', function (e) {
        var q = e.target.closest('[data-q]');
        if (q) { qty = Math.max(1, Math.min(20, qty + (+q.dataset.q))); $('#qv').textContent = qty; sum(); return; }
        if (e.target.closest('#addBtn')) Shop.add(root.dataset.product, vid, qty, $('#note').value.trim());
    });
})();
