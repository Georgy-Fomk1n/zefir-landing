// Service worker основного домена: только push-уведомления о заказах.
// Обработчика fetch нет намеренно — SW не влияет на загрузку страниц, кеш и SEO.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

function parse(text) {
  let data = {};
  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    data = { body: text || '' };
  }
  return { title: data.title || 'Зефир и пряник', body: data.body || '', url: data.url || '/account/', tag: data.tag || 'zefir' };
}

self.addEventListener('push', (event) => {
  const msg = parse(event.data ? event.data.text() : null);
  event.waitUntil(self.registration.showNotification(msg.title, {
    body: msg.body,
    tag: msg.tag,
    icon: '/icon-192.png',
    badge: '/icon-192.png',
    data: { url: msg.url },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/account/';
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    const same = list.find((c) => new URL(c.url).pathname === new URL(url, self.location.origin).pathname);
    if (same) return same.focus();
    return self.clients.openWindow(url);
  }));
});
