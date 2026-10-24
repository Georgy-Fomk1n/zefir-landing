#!/usr/bin/env node
// Постобработка пререндера (dist/):
//  • 404.html / 500.html в корень (ErrorDocument), каталоги /404/ и /500/ удаляются — иначе это soft 404;
//  • CSP <meta> с sha256 inline-скриптов каждой страницы (vite-react-ssg кладёт состояние гидратации inline);
//  • sitemap.xml (только индексируемые канонические URL, с lastmod), robots.txt, manifest, YML-фид, ключ IndexNow;
//  • .htaccess из deploy/htaccess/public.htaccess под окружение (production | staging).
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, readFileSync, renameSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dist = join(root, 'dist');
const repo = join(root, '..');
const env = process.env.VITE_APP_ENV ?? 'development';
const isProd = env === 'production';

const SITE_HOST = 'зефир-и-пряник.рф';
const SITE_URL = `https://${SITE_HOST}`;
const API_ORIGIN = process.env.VITE_API_URL ?? 'http://localhost:8000';
const INDEXNOW_KEY = process.env.INDEXNOW_KEY ?? '';
const catalog = JSON.parse(readFileSync(join(root, 'src/data/catalog.generated.json'), 'utf8'));

// API_ORIGIN в CSP должен быть в punycode: браузер сравнивает origin в ASCII-форме
const apiOriginAscii = (() => {
  try {
    return new URL(API_ORIGIN).origin;
  } catch {
    return API_ORIGIN;
  }
})();

function walk(dir) {
  return readdirSync(dir).flatMap((f) => {
    const p = join(dir, f);
    return statSync(p).isDirectory() ? walk(p) : [p];
  });
}

const xml = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// 1. Страницы ошибок
for (const code of ['404', '500']) {
  const dir = join(dist, code);
  if (existsSync(join(dir, 'index.html'))) {
    renameSync(join(dir, 'index.html'), join(dist, `${code}.html`));
    rmSync(dir, { recursive: true, force: true });
  }
}

// 2. CSP с хешами inline-скриптов
const cspBase = [
  "default-src 'self'",
  `script-src 'self' https://mc.yandex.ru https://yastatic.net`,
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: blob: https://mc.yandex.ru https://*.yandex.net",
  "font-src 'self'",
  `connect-src 'self' ${apiOriginAscii} https://mc.yandex.ru wss://mc.yandex.ru`,
  'frame-src https://yandex.ru https://*.yandex.ru',
  "media-src 'self'",
  "form-action 'self'",
  "manifest-src 'self'",
];

const htmlFiles = walk(dist).filter((f) => f.endsWith('.html'));
for (const file of htmlFiles) {
  let html = readFileSync(file, 'utf8');
  const hashes = [...html.matchAll(/<script(?![^>]*\bsrc=)(?![^>]*type="application\/ld\+json")[^>]*>([\s\S]*?)<\/script>/g)]
    .map((m) => `'sha256-${createHash('sha256').update(m[1]).digest('base64')}'`);
  const csp = cspBase.map((d) => (d.startsWith('script-src') ? `${d} ${hashes.join(' ')}` : d)).join('; ');
  html = html.replace('<head>', `<head><meta http-equiv="Content-Security-Policy" content="${csp}">`);
  writeFileSync(file, html);
}

// 3. sitemap.xml — из готового HTML: только index-страницы, URL берём из canonical
function lastmodFromGit(paths) {
  try {
    return execFileSync('git', ['log', '-1', '--format=%cI', '--', ...paths], { cwd: repo }).toString().trim() || null;
  } catch {
    return null;
  }
}
const contentLastmod = lastmodFromGit(['frontend/src/content', 'frontend/src/pages']) ?? new Date().toISOString();
const productLastmod = new Map(catalog.products.map((p) => [`/catalog/${p.categorySlug}/${p.slug}/`, p.updatedAt]));
const categoryLastmod = new Map(catalog.categories.map((c) => [`/catalog/${c.slug}/`, c.updatedAt]));

const urls = [];
for (const file of htmlFiles) {
  const rel = `/${relative(dist, file).replace(/index\.html$/, '')}`;
  if (rel.endsWith('.html')) continue; // 404.html, 500.html
  const html = readFileSync(file, 'utf8');
  const canonical = html.match(/<link[^>]*rel="canonical"[^>]*href="([^"]+)"/)?.[1];
  const robots = html.match(/<meta[^>]*name="robots"[^>]*content="([^"]+)"/)?.[1] ?? '';
  if (!canonical || (isProd && robots.includes('noindex'))) continue;
  if (!isProd && /\/(account|cart|checkout|track|poisk)\//.test(rel)) continue;
  if (canonical !== `${SITE_URL}${rel}`) continue; // неканонические дубли в sitemap не попадают
  const lastmod = productLastmod.get(rel) ?? categoryLastmod.get(rel) ?? contentLastmod;
  urls.push({ loc: canonical, lastmod: lastmod.slice(0, 10), priority: rel === '/' ? '1.0' : rel.split('/').length > 4 ? '0.8' : '0.6' });
}
urls.sort((a, b) => a.loc.localeCompare(b.loc));
// Разбивка на индекс при росте: лимит протокола 50 000 URL на файл
const CHUNK = 45000;
const chunks = [];
for (let i = 0; i < urls.length; i += CHUNK) chunks.push(urls.slice(i, i + CHUNK));
const urlset = (list) => `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${list.map((u) => `  <url><loc>${xml(u.loc)}</loc><lastmod>${u.lastmod}</lastmod><priority>${u.priority}</priority></url>`).join('\n')}\n</urlset>\n`;
if (chunks.length <= 1) {
  writeFileSync(join(dist, 'sitemap.xml'), urlset(chunks[0] ?? []));
} else {
  chunks.forEach((c, i) => writeFileSync(join(dist, `sitemap-${i + 1}.xml`), urlset(c)));
  writeFileSync(join(dist, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>\n<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${chunks.map((_, i) => `  <sitemap><loc>${xml(`${SITE_URL}/sitemap-${i + 1}.xml`)}</loc></sitemap>`).join('\n')}\n</sitemapindex>\n`);
}

// 4. robots.txt. Служебные пути не упоминаются; staging закрыт целиком.
const robots = isProd
  ? `User-agent: *
Disallow: /account/
Disallow: /cart/
Disallow: /checkout/
Disallow: /track/
Disallow: /poisk/
Disallow: /*?*sort=
Disallow: /*?*pp=
Disallow: /*?*q=
Disallow: /*?*utm_
Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid

Sitemap: ${SITE_URL}/sitemap.xml
`
  : `User-agent: *
Disallow: /
`;
writeFileSync(join(dist, 'robots.txt'), robots);

// 5. manifest
writeFileSync(join(dist, 'manifest.webmanifest'), JSON.stringify({
  name: 'Зефир и пряник',
  short_name: 'Зефир и пряник',
  description: 'Корпусные пирожные ручной работы в Москве',
  lang: 'ru',
  start_url: '/',
  display: 'standalone',
  background_color: '#FFF5FB',
  theme_color: '#D719E3',
  icons: [
    { src: '/icon-192.png', sizes: '192x192', type: 'image/png' },
    { src: '/icon-512.png', sizes: '512x512', type: 'image/png' },
  ],
}, null, 2));

// 6. YML-фид для Яндекс Товаров
const ymlDate = new Date().toISOString().slice(0, 16).replace('T', ' ');
const catIds = new Map(catalog.categories.map((c) => [c.slug, c.id]));
const offers = catalog.products.flatMap((p) => p.variants.map((v) => {
  const image = p.media.find((m) => m.type === 'image');
  const picture = image ? `${SITE_URL}/media/${image.name}-1200.webp` : `${SITE_URL}/media/${p.media[0]?.name}-poster.webp`;
  return `      <offer id="${xml(v.sku)}" available="${v.inStock}">
        <url>${xml(`${SITE_URL}/catalog/${p.categorySlug}/${p.slug}/`)}</url>
        <price>${(v.price / 100).toFixed(0)}</price>
        <currencyId>RUR</currencyId>
        <categoryId>${catIds.get(p.categorySlug)}</categoryId>
        <picture>${xml(picture)}</picture>
        <name>${xml(`${p.name}${p.variants.length > 1 ? `, ${v.name}` : ''}`)}</name>
        <vendor>Зефир и пряник</vendor>
        <description>${xml(p.description)}</description>
        <pickup>true</pickup>
        <delivery>true</delivery>
      </offer>`;
}));
mkdirSync(join(dist, 'feeds'), { recursive: true });
writeFileSync(join(dist, 'feeds', 'yandex.yml'), `<?xml version="1.0" encoding="UTF-8"?>
<yml_catalog date="${ymlDate}">
  <shop>
    <name>Зефир и пряник</name>
    <company>ИП Губанова Татьяна Сергеевна</company>
    <url>${xml(`${SITE_URL}/`)}</url>
    <currencies><currency id="RUR" rate="1"/></currencies>
    <categories>
${catalog.categories.map((c) => `      <category id="${c.id}">${xml(c.name)}</category>`).join('\n')}
    </categories>
    <offers>
${offers.join('\n')}
    </offers>
  </shop>
</yml_catalog>
`);

// 7. Ключ IndexNow (файл {key}.txt в корне сайта)
if (INDEXNOW_KEY && /^[a-zA-Z0-9-]{8,128}$/.test(INDEXNOW_KEY)) {
  writeFileSync(join(dist, `${INDEXNOW_KEY}.txt`), INDEXNOW_KEY);
}

// 8. .htaccess
const tpl = readFileSync(join(repo, 'deploy', 'htaccess', 'public.htaccess'), 'utf8');
const staging = env === 'staging';
const htaccess = tpl
  .replace(/^#STAGING (.*)$/gm, staging ? '$1' : '')
  .replace(/^#PRODUCTION (.*)$/gm, isProd ? '$1' : '')
  .replace(/__CANONICAL_HOST__/g, process.env.CANONICAL_HOST_PUNYCODE ?? 'xn-----mlchgbbqzthe9b5k.xn--p1ai')
  .replace(/__HTPASSWD__/g, process.env.STAGING_HTPASSWD_PATH ?? '/home/USER/.htpasswd');
writeFileSync(join(dist, '.htaccess'), htaccess);

console.log(`postbuild: ${env}, страниц ${htmlFiles.length}, в sitemap ${urls.length}, офферов ${offers.length}`);
