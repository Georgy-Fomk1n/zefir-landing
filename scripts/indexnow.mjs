#!/usr/bin/env node
// IndexNow после деплоя production. CHANGED_PATHS — JSON-массив путей из repository_dispatch
// (изменения каталога); пусто — отправляем URL из sitemap с lastmod за последние 2 дня.
const { SITE_URL, INDEXNOW_KEY, CHANGED_PATHS = '[]' } = process.env;
if (!SITE_URL || !INDEXNOW_KEY) {
  console.log('IndexNow: ключ не задан, пропуск');
  process.exit(0);
}
const site = SITE_URL.replace(/\/$/, '');
let urls = JSON.parse(CHANGED_PATHS || '[]').map((p) => `${site}${p}`);
if (urls.length === 0) {
  const xml = await (await fetch(`${site}/sitemap.xml`)).text();
  const since = new Date(Date.now() - 2 * 864e5).toISOString().slice(0, 10);
  urls = [...xml.matchAll(/<loc>([^<]+)<\/loc><lastmod>([^<]+)<\/lastmod>/g)].filter((m) => m[2] >= since).map((m) => m[1]);
}
if (urls.length === 0) {
  console.log('IndexNow: изменённых URL нет');
  process.exit(0);
}
// IndexNow требует, чтобы host совпадал с URL. Sitemap использует кириллический хост — оставляем его как есть.
const host = new URL(urls[0]).hostname;
const res = await fetch('https://yandex.com/indexnow', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json; charset=utf-8' },
  body: JSON.stringify({ host, key: INDEXNOW_KEY, keyLocation: `${site}/${INDEXNOW_KEY}.txt`, urlList: urls.slice(0, 10000) }),
});
console.log(`IndexNow: ${urls.length} URL → HTTP ${res.status}`);
if (![200, 202].includes(res.status)) process.exit(1);
