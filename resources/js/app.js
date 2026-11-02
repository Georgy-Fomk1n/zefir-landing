import '../css/app.css';

/* Точка входа. Здесь только лёгкая «ванильная» часть: тема, появление блоков, видео, острова React и PWA.
   React и Motion грузятся отдельными чанками и только когда на странице есть остров. */

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const idleCb = window.requestIdleCallback || ((f) => setTimeout(f, 200));
// «После загрузки»: тяжёлое (React, видео) стартует, когда страница уже нарисована, и не мешает главному кадру
const afterLoad = (fn, delay = 0, timeout = 2500) => {
    const run = () => setTimeout(() => idleCb(fn, { timeout }), delay);
    document.readyState === 'complete' ? run() : addEventListener('load', run, { once: true });
};
const slowNet = () => { const c = navigator.connection; return !!c && (c.saveData || /(^|-)2g$|3g/.test(c.effectiveType || '')); };

/* ---------- Тема ---------- */
const root = document.documentElement;
$('#themeBtn')?.addEventListener('click', () => {
    const dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('theme', root.dataset.theme); } catch (_) { }
});

/* ---------- Статус «открыто» (страницы кэшируются, поэтому считаем в браузере, но по времени кондитерской, а не гостя) ---------- */
function shopNow(tz) {
    try {
        const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US', { timeZone: tz, weekday: 'short', hour: 'numeric', minute: 'numeric', hourCycle: 'h23' }).formatToParts(new Date()).map((x) => [x.type, x.value]));
        return { day: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(parts.weekday), t: +parts.hour + +parts.minute / 60 };
    } catch (_) { const n = new Date(); return { day: n.getDay(), t: n.getHours() + n.getMinutes() / 60 }; }
}
function paintOpen() {
    const el = $('[data-open-now]');
    if (!el) return;
    const cfg = { open: +el.dataset.open, close: +el.dataset.close, closed: el.dataset.closed.split(',').filter(Boolean).map(Number) };
    const { day: d, t } = shopNow(el.dataset.tz || 'Europe/Moscow');
    const o = !cfg.closed.includes(d) && t >= cfg.open && t < cfg.close;
    el.textContent = o ? `Открыто до ${cfg.close}:00` : 'Сейчас закрыто';
    el.classList.toggle('closed', !o);
    const st = $('[data-status]');
    if (st) {
        st.classList.toggle('open', o); st.classList.toggle('closed', !o);
        const next = (d + 1) % 7;
        const when = cfg.closed.includes(d) || t >= cfg.close
            ? (cfg.closed.includes(next) ? 'во вторник' : 'завтра')   // выходной — ближайшее открытие через день
            : 'сегодня';
        $('[data-status-text]', st).textContent = o ? 'Сейчас открыто' : `Сейчас закрыто (откроется ${when} в ${cfg.open}:00)`;
    }
}
paintOpen(); setInterval(paintOpen, 60000);

/* ---------- Мобильное меню: закрывается по клику снаружи, Esc и переходу ---------- */
const menu = $('[data-menu]');
if (menu) {
    document.addEventListener('click', (e) => { if (menu.open && !menu.contains(e.target)) menu.open = false; });
    addEventListener('keydown', (e) => { if (e.key === 'Escape' && menu.open) { menu.open = false; $('summary', menu).focus(); } });
    menu.addEventListener('click', (e) => { if (e.target.closest('a')) menu.open = false; });
}

/* ---------- Сортировка в каталоге без кнопки «Применить» ---------- */
$$('[data-autosubmit]').forEach((s) => s.addEventListener('change', () => s.form.submit()));

/* ---------- Появление блоков при прокрутке ---------- */
const rv = $$('.rv');
if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('on'); io.unobserve(e.target); } }), { threshold: 0.06, rootMargin: '0px 0px -40px 0px' });
    rv.forEach((s) => io.observe(s));
} else rv.forEach((s) => s.classList.add('on'));

/* ---------- Видео: грузим, когда страница уже нарисована и ролик виден ---------- */
const vids = $$('video[data-src]');
if (vids.length) {
    const vio = new IntersectionObserver((es) => es.forEach((e) => {
        if (!e.isIntersecting) return;
        const v = e.target; vio.unobserve(v);
        v.src = v.dataset.src; v.addEventListener('playing', () => v.classList.add('on'), { once: true });
        v.play?.().catch(() => { });
    }), { rootMargin: '200px' });
    // видео (по 300 КБ) грузим после загрузки страницы и не на медленной сети или при экономии трафика
    // Видео не должно подменять главный кадр и отнимать канал у страницы: стартует при первом действии человека или через 6 секунд
    if (!slowNet()) {
        let started = false;
        const go = () => { if (started) return; started = true; vids.forEach((v) => vio.observe(v)); };
        ['pointermove', 'touchstart', 'scroll', 'keydown'].forEach((ev) => addEventListener(ev, go, { once: true, passive: true }));
        afterLoad(() => setTimeout(go, 6000), 0, 8000);
    }
}

/* ---------- Острова React ---------- */
const registry = {
    'cart-count': () => import('./islands/CartCount.jsx'),
    'cart-drawer': () => import('./islands/CartDrawer.jsx'),
    buy: () => import('./islands/Buy.jsx'),
    'product-buy': () => import('./islands/ProductBuy.jsx'),
    checkout: () => import('./islands/Checkout.jsx'),
    'push-order': () => import('./islands/PushOrder.jsx'),
    install: () => import('./islands/Install.jsx'),
};

async function mount(el) {
    if (el.dataset.mounting) return;
    el.dataset.mounting = '1';
    const load = registry[el.dataset.island];
    if (!load) return;
    const [mod, { createRoot }, { createElement }] = await Promise.all([load(), import('react-dom/client'), import('react')]);
    const props = el.dataset.props ? JSON.parse(el.dataset.props) : {};
    createRoot(el).render(createElement(mod.default, { ...props, el }));
    el.dataset.hydrated = '1';
    if (el.dataset.pending) setTimeout(() => $('[data-primary]', el)?.click(), 60);
}
window.__mountIsland = mount;

const islands = $$('[data-island]');
islands.forEach((el) => {
    if (el.dataset.lazy === 'eager') { mount(el); return; }
    // раньше срока поднимаем остров, если до него дотронулись
    ['pointerenter', 'touchstart', 'focusin'].forEach((ev) => el.addEventListener(ev, () => mount(el), { once: true, passive: true }));
});
afterLoad(() => islands.forEach((el) => { if (el.dataset.lazy !== 'eager') mount(el); }), 150, 1500);

// Нажатие до загрузки острова не теряется: запоминаем и повторяем после подключения
document.addEventListener('click', (e) => {
    const isl = e.target.closest('[data-island]:not([data-hydrated])');
    if (!isl || !e.target.closest('[data-primary]')) return;
    e.preventDefault();
    isl.dataset.pending = '1';
    mount(isl);
}, true);

// Корзину можно открыть сразу: шторка подтянется и откроется сама
document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-open-cart]')) return;
    window.__cartOpen = true;
    const drawer = $('[data-island="cart-drawer"]');
    if (drawer?.dataset.hydrated) window.dispatchEvent(new Event('cart:open'));
    else if (drawer) mount(drawer);
});

/* ---------- PWA ---------- */
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.__bip = e;
    window.dispatchEvent(new CustomEvent('bip', { detail: e }));
});

if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => { }));
}
