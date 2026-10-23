#!/usr/bin/env node
// SEO-проверки пререндера (раздел 6.10). Запуск после production-сборки: npm run seo:check -w frontend.
// Любая ошибка → exit 1 и падение CI.
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse } from 'node-html-parser';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dist = process.env.SEO_DIST ?? join(root, 'dist');
const SITE_URL = 'https://зефир-и-пряник.рф';
const LEGACY = 'zefiripryanik.ru';
const TITLE_MAX = 60;
const DESC_MAX = 160;
// Закрытые разделы: оболочки SPA с noindex, требования к контенту к ним не применяются
const PRIVATE = /^\/(account|cart|checkout|track|poisk)\//;

const errors = [];
const warn = [];
const fail = (page, msg) => errors.push(`${page}: ${msg}`);

function walk(dir) {
  return readdirSync(dir).flatMap((f) => {
    const p = join(dir, f);
    return statSync(p).isDirectory() ? walk(p) : [p];
  });
}

if (!existsSync(dist)) {
  console.error(`seo-check: нет ${dist}, сначала соберите фронтенд`);
  process.exit(1);
}

const files = walk(dist);
const htmlFiles = files.filter((f) => f.endsWith('.html'));
const pathOf = (file) => `/${relative(dist, file).replace(/index\.html$/, '')}`;
const existing = new Set(htmlFiles.map(pathOf));
const assets = new Set(files.map((f) => `/${relative(dist, f)}`));

const titles = new Map();
const descriptions = new Map();
const checkedLinks = new Set();

function validateJsonLd(page, data) {
  const items = Array.isArray(data) ? data : [data];
  for (const item of items) {
    if (item['@context'] !== 'https://schema.org') fail(page, 'JSON-LD без @context https://schema.org');
    const type = [].concat(item['@type'] ?? []);
    if (type.length === 0) fail(page, 'JSON-LD без @type');
    if (type.includes('Product')) {
      if (!item.name || !item.image?.length) fail(page, 'Product без name/image');
      const offers = item.offers?.offers ?? [item.offers];
      for (const o of offers) {
        if (!o?.price || o.priceCurrency !== 'RUB' || !o.availability) fail(page, 'Offer без price/priceCurrency/availability');
        if (!/^\d+\.\d{2}$/.test(o?.price ?? '')) fail(page, `Offer.price в неверном формате: ${o?.price}`);
      }
      if (item.aggregateRating && !(item.review?.length > 0)) fail(page, 'AggregateRating без Review с сайта');
    }
    if (type.includes('BreadcrumbList')) {
      item.itemListElement?.forEach((el, i) => {
        if (el.position !== i + 1 || !el.item?.startsWith(SITE_URL)) fail(page, 'BreadcrumbList: неверная позиция или URL');
      });
    }
    if (type.includes('FAQPage') && !(item.mainEntity?.length > 0)) fail(page, 'FAQPage без вопросов');
    if (JSON.stringify(item).includes(LEGACY)) fail(page, `JSON-LD содержит старый домен ${LEGACY}`);
  }
}

for (const file of htmlFiles) {
  const page = pathOf(file);
  const raw = readFileSync(file, 'utf8');
  const doc = parse(raw);
  const isError = /^\/(404|500)\.html$/.test(page);
  const isPrivate = PRIVATE.test(page);

  if (raw.includes(LEGACY)) fail(page, `найден старый домен ${LEGACY}`);
  if (doc.querySelector('html')?.getAttribute('lang') !== 'ru') fail(page, 'нет lang="ru"');
  if (!doc.querySelector('meta[name="viewport"]')) fail(page, 'нет meta viewport');

  const robots = doc.querySelector('meta[name="robots"]')?.getAttribute('content') ?? '';
  const noindex = robots.includes('noindex');
  if ((isError || isPrivate) && !noindex) fail(page, 'служебная страница без noindex');
  if (!isError && !isPrivate && noindex) fail(page, 'публичная страница с noindex (сборка не production?)');

  const title = doc.querySelector('title')?.text.trim() ?? '';
  const description = doc.querySelector('meta[name="description"]')?.getAttribute('content')?.trim() ?? '';
  if (!title) fail(page, 'нет <title>');
  if (!description) fail(page, 'нет meta description');

  // Ссылки и картинки проверяем везде, включая 404 и оболочки
  for (const img of doc.querySelectorAll('img')) {
    if (img.getAttribute('alt') === undefined) fail(page, `img без alt: ${img.getAttribute('src')}`);
    if (!img.getAttribute('width') || !img.getAttribute('height')) fail(page, `img без width/height: ${img.getAttribute('src')}`);
  }
  for (const a of doc.querySelectorAll('a[href]')) {
    const href = a.getAttribute('href');
    if (!href.startsWith('/') || href.startsWith('//')) continue;
    const path = href.split(/[?#]/)[0];
    if (checkedLinks.has(path)) continue;
    checkedLinks.add(path);
    if (/^\/(admin|seller|courier|staff)(\/|$)/.test(path)) fail(page, `ссылка на служебный раздел ${path}`);
    else if (!existing.has(path) && !assets.has(path) && !/^\/account\//.test(path)) fail(page, `битая внутренняя ссылка ${href}`);
    else if (!path.endsWith('/') && !path.includes('.')) fail(page, `ссылка без слэша в конце ${href}`);
  }
  if (isError || isPrivate) continue;

  if (title.length > TITLE_MAX) fail(page, `title длиннее ${TITLE_MAX} (${title.length}): ${title}`);
  if (description.length > DESC_MAX) fail(page, `description длиннее ${DESC_MAX} (${description.length})`);
  if (titles.has(title)) fail(page, `дубль title с ${titles.get(title)}`);
  else titles.set(title, page);
  if (descriptions.has(description)) fail(page, `дубль description с ${descriptions.get(description)}`);
  else descriptions.set(description, page);

  const h1 = doc.querySelectorAll('h1');
  if (h1.length !== 1) fail(page, `h1: ${h1.length}, нужен ровно один`);

  const canonical = doc.querySelector('link[rel="canonical"]')?.getAttribute('href');
  if (!canonical) fail(page, 'нет canonical');
  else if (canonical !== `${SITE_URL}${page}`) fail(page, `canonical ${canonical} ≠ ${SITE_URL}${page}`);

  for (const prop of ['og:title', 'og:description', 'og:url', 'og:image']) {
    if (!doc.querySelector(`meta[property="${prop}"]`)) fail(page, `нет ${prop}`);
  }
  const ogImage = doc.querySelector('meta[property="og:image"]')?.getAttribute('content') ?? '';
  if (!assets.has(ogImage.replace(SITE_URL, ''))) fail(page, `og:image не найден: ${ogImage}`);

  const blocks = doc.querySelectorAll('script[type="application/ld+json"]');
  if (blocks.length === 0) fail(page, 'нет JSON-LD');
  const types = new Set();
  for (const block of blocks) {
    try {
      const data = JSON.parse(block.text);
      [].concat(data['@type']).forEach((t) => types.add(t));
      validateJsonLd(page, data);
    } catch (e) {
      fail(page, `невалидный JSON-LD: ${e.message}`);
    }
  }
  if (page !== '/' && !types.has('BreadcrumbList')) fail(page, 'нет BreadcrumbList');
  if (page !== '/' && !doc.querySelector('nav.breadcrumbs')) fail(page, 'нет хлебных крошек в HTML');
  if (/^\/catalog\/[^/]+\/[^/]+\/$/.test(page) && !types.has('Product')) fail(page, 'карточка товара без Product');
  if (/^\/catalog\/[^/]+\/$/.test(page) && !types.has('ItemList')) fail(page, 'категория без ItemList');
  if (page === '/' && !types.has('WebSite')) fail(page, 'на главной нет WebSite');
  if (page === '/voprosy/' && !types.has('FAQPage')) fail(page, 'на странице вопросов нет FAQPage');

  const headings = doc.querySelectorAll('h1, h2, h3, h4').map((h) => Number(h.tagName[1]));
  for (let i = 1; i < headings.length; i++) {
    if (headings[i] - headings[i - 1] > 1) {
      warn.push(`${page}: пропуск уровня заголовка h${headings[i - 1]} → h${headings[i]}`);
      break;
    }
  }
}

// 404/500
for (const f of ['404.html', '500.html']) if (!existsSync(join(dist, f))) errors.push(`нет ${f}`);
if (existsSync(join(dist, '404', 'index.html'))) errors.push('/404/ отдаётся как обычная страница (soft 404)');

// sitemap.xml
const sitemapPath = join(dist, 'sitemap.xml');
if (!existsSync(sitemapPath)) errors.push('нет sitemap.xml');
else {
  const sm = readFileSync(sitemapPath, 'utf8');
  if (!sm.startsWith('<?xml') || !sm.includes('http://www.sitemaps.org/schemas/sitemap/0.9')) errors.push('sitemap.xml: неверный формат');
  const locs = [...sm.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1].replace(/&amp;/g, '&'));
  if (locs.length === 0) errors.push('sitemap.xml пуст');
  if (new Set(locs).size !== locs.length) errors.push('sitemap.xml: дубли URL');
  for (const loc of locs) {
    if (!loc.startsWith(`${SITE_URL}/`)) errors.push(`sitemap: чужой хост ${loc}`);
    const p = loc.slice(SITE_URL.length);
    if (!existing.has(p) && !p.endsWith('.xml')) errors.push(`sitemap: URL без страницы ${loc}`);
    if (PRIVATE.test(p)) errors.push(`sitemap: закрытый раздел ${loc}`);
  }
  const lastmods = [...sm.matchAll(/<url>[\s\S]*?<\/url>/g)].filter((m) => !m[0].includes('<lastmod>'));
  if (lastmods.length) errors.push(`sitemap: ${lastmods.length} URL без lastmod`);
  for (const [, page] of titles) if (!locs.includes(`${SITE_URL}${page}`)) errors.push(`индексируемая страница не в sitemap: ${page}`);
}

// robots.txt
const robotsPath = join(dist, 'robots.txt');
if (!existsSync(robotsPath)) errors.push('нет robots.txt');
else {
  const r = readFileSync(robotsPath, 'utf8');
  if (!r.includes(`Sitemap: ${SITE_URL}/sitemap.xml`)) errors.push('robots.txt: нет Sitemap');
  for (const d of ['/account/', '/cart/', '/checkout/']) if (!r.includes(`Disallow: ${d}`)) errors.push(`robots.txt: нет Disallow ${d}`);
  if (/admin|seller|courier|штат|xn--80atyc/i.test(r)) errors.push('robots.txt раскрывает служебные пути');
  if (/^Disallow:\s*\/\s*$/m.test(r)) errors.push('robots.txt закрывает весь сайт (это staging-сборка?)');
}

for (const w of warn) console.warn(`⚠ ${w}`);
if (errors.length) {
  console.error(`✗ SEO-проверка: ${errors.length} ошибок`);
  for (const e of errors) console.error(`  • ${e}`);
  process.exit(1);
}
console.log(`✓ SEO-проверка: ${htmlFiles.length} страниц, ${titles.size} индексируемых, ошибок нет`);
