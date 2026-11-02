/* Service worker «Зефир и пряник».
   Задачи: быстрая повторная загрузка (кэш статики и картинок), работа без сети (офлайн-страница)
   и push-уведомления. Корзина, оформление, админка и API всегда идут в сеть. */
const VERSION = 'v1';
const STATIC = `zp-static-${VERSION}`;
const PAGES = `zp-pages-${VERSION}`;
const MEDIA = `zp-media-${VERSION}`;
const KEEP = [STATIC, PAGES, MEDIA];
const MEDIA_LIMIT = 80;

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(PAGES).then((c) => c.add(new Request('/offline', { cache: 'reload' }))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter((n) => n.startsWith('zp-') && !KEEP.includes(n)).map((n) => caches.delete(n)));
        if (self.registration.navigationPreload) await self.registration.navigationPreload.enable();
        await self.clients.claim();
    })());
});

const bypass = (url) => /^\/(api|admin|livewire|filament|order|up)(\/|$)/.test(url.pathname) || url.pathname === '/sw.js';

async function trim(cacheName, max) {
    const c = await caches.open(cacheName);
    const keys = await c.keys();
    if (keys.length > max) await Promise.all(keys.slice(0, keys.length - max).map((k) => c.delete(k)));
}

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin || bypass(url)) return;

    // Хешированные файлы сборки не меняются: кэш первым
    if (url.pathname.startsWith('/build/')) {
        e.respondWith(caches.open(STATIC).then(async (c) => {
            const hit = await c.match(req);
            if (hit) return hit;
            const res = await fetch(req);
            if (res.ok) c.put(req, res.clone());
            return res;
        }));
        return;
    }

    // Картинки и иконки: из кэша сразу, в фоне обновляем
    if (/^\/(images|icons)\//.test(url.pathname) && !/\.mp4$/.test(url.pathname)) {
        e.respondWith(caches.open(MEDIA).then(async (c) => {
            const hit = await c.match(req);
            const net = fetch(req).then((res) => { if (res.ok) { c.put(req, res.clone()); trim(MEDIA, MEDIA_LIMIT); } return res; }).catch(() => hit);
            return hit || net;
        }));
        return;
    }

    // Страницы: сначала сеть, при обрыве — последняя копия или офлайн-страница
    if (req.mode === 'navigate') {
        e.respondWith((async () => {
            try {
                const res = (await e.preloadResponse) || (await fetch(req));
                if (res.ok && res.headers.get('content-type')?.includes('text/html')) {
                    const c = await caches.open(PAGES);
                    c.put(req, res.clone());
                }
                return res;
            } catch (_) {
                const c = await caches.open(PAGES);
                return (await c.match(req)) || (await c.match('/offline')) || new Response('Нет соединения', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
            }
        })());
    }
});

/* ---------- Push ---------- */
self.addEventListener('push', (e) => {
    let data = {};
    try { data = e.data ? e.data.json() : {}; } catch (_) { data = { title: 'Зефир и пряник', body: e.data ? e.data.text() : '' }; }
    const title = data.title || 'Зефир и пряник';
    e.waitUntil(self.registration.showNotification(title, {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        badge: data.badge || '/icons/badge-96.png',
        data: { url: data.url || '/' },
        tag: data.tag || undefined,
        lang: 'ru',
    }));
});

self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const target = new URL(e.notification.data?.url || '/', self.location.origin).href;
    e.waitUntil((async () => {
        const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const w of wins) {
            if (w.url === target && 'focus' in w) return w.focus();
        }
        const same = wins.find((w) => new URL(w.url).origin === self.location.origin && 'navigate' in w);
        if (same) { await same.focus(); return same.navigate(target); }
        return self.clients.openWindow(target);
    })());
});
