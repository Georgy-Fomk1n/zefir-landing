#!/usr/bin/env node
// Smoke-тесты после деплоя (раздел 8, п. 5). Любая ошибка → exit 1.
// SITE_URL, STAFF_URL, API_URL — в punycode; BASIC_AUTH=user:pass для staging; EXPECT_ENV=production|staging.
const { SITE_URL, STAFF_URL, API_URL, BASIC_AUTH = '', EXPECT_ENV = 'production' } = process.env;
if (!SITE_URL || !STAFF_URL || !API_URL) {
  console.error('smoke: нужны SITE_URL, STAFF_URL, API_URL');
  process.exit(1);
}
const headers = BASIC_AUTH ? { Authorization: `Basic ${Buffer.from(BASIC_AUTH).toString('base64')}` } : {};
const failures = [];
const ok = (cond, msg) => (cond ? console.log(`✓ ${msg}`) : failures.push(msg));

async function get(url, extra = {}) {
  const res = await fetch(url, { headers, redirect: 'manual', ...extra });
  return { res, text: res.status < 300 ? await res.text() : '' };
}

const health = await get(`${API_URL}/api/health`);
ok(health.res.status === 200 && JSON.parse(health.text).status === 'ok', 'GET /api/health → ok');
ok((health.res.headers.get('x-robots-tag') ?? '').includes('noindex'), 'API: X-Robots-Tag noindex');

const home = await get(`${SITE_URL}/`);
ok(home.res.status === 200 && home.text.includes('<h1') && home.text.includes('application/ld+json'), 'главная: 200, h1 и JSON-LD в исходном HTML');
ok(EXPECT_ENV === 'production' ? home.text.includes('index,follow') : home.text.includes('noindex'), `главная: robots соответствует окружению ${EXPECT_ENV}`);

const catalog = await (await fetch(`${API_URL}/api/catalog/export`, { headers })).json();
const product = catalog.products[0];
const productPage = await get(`${SITE_URL}/catalog/${product.categorySlug}/${product.slug}/`);
ok(productPage.res.status === 200 && productPage.text.includes('"@type":"Product"'), `товар /catalog/${product.categorySlug}/${product.slug}/: 200 и Product`);

const robots = await get(`${SITE_URL}/robots.txt`);
ok(robots.res.status === 200, 'robots.txt: 200');
ok(EXPECT_ENV === 'production' ? robots.text.includes('Sitemap:') : /Disallow:\s*\/\s*$/m.test(robots.text), 'robots.txt соответствует окружению');

const sitemap = await get(`${SITE_URL}/sitemap.xml`);
ok(sitemap.res.status === 200 && sitemap.text.includes('<urlset') && sitemap.text.includes('<loc>'), 'sitemap.xml: 200 и URL внутри');

const missing = await get(`${SITE_URL}/net-takoj-stranicy-${Date.now()}/`);
ok(missing.res.status === 404, 'несуществующий URL → настоящий 404');
for (const path of ['/admin/', '/courier/', '/seller/']) {
  const r = await get(`${SITE_URL}${path}`);
  ok(r.res.status === 404, `основной домен ${path} → 404`);
}

const noSlash = await get(`${SITE_URL}/catalog`);
ok(noSlash.res.status === 301, '/catalog без слэша → 301');

const staff = await get(`${STAFF_URL}/login`);
ok(staff.res.status === 200 && (staff.res.headers.get('x-robots-tag') ?? '').includes('noindex'), 'штат: страница входа 200 и X-Robots-Tag noindex');

if (failures.length) {
  console.error(`✗ smoke: ${failures.length} проверок не прошли`);
  failures.forEach((f) => console.error(`  • ${f}`));
  process.exit(1);
}
console.log('smoke: все проверки пройдены');
