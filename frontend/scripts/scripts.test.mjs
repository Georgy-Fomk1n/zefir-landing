import { execFileSync, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { fromSeed } from './fetch-catalog.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const seed = JSON.parse(readFileSync(join(here, '..', '..', 'backend', 'database', 'data', 'catalog-seed.json'), 'utf8'));
const SITE = 'https://зефир-и-пряник.рф';

describe('fetch-catalog: снимок из seed', () => {
  it('переводит seed в формат API с последовательными id вариантов', () => {
    const c = fromSeed(seed, '2026-01-01T00:00:00Z');
    expect(c.categories.map((x) => x.slug)).toEqual(['vkusy', 'pp-bez-sahara', 'nabory', 'podarki', 'drugie-deserty']);
    expect(c.products).toHaveLength(8);
    expect(c.products.flatMap((p) => p.variants.map((v) => v.id))).toEqual([1, 2, 3, 4, 5, 6, 7, 8]);
    const kofe = c.products.find((p) => p.slug === 'kofe');
    expect(kofe).toMatchObject({ categorySlug: 'vkusy', isPp: false, rating: null, seo: { noindex: false } });
    expect(kofe.variants[0]).toMatchObject({ price: 45000, inStock: true, priceFrom: false });
    expect(c.categories.find((x) => x.slug === 'podarki').crossListed).toEqual(['nabor-4', 'nabor-10']);
    expect(c.seoPages).toEqual([]);
  });

  it('при недоступном API сборка падает (нельзя выложить устаревшую статику)', () => {
    const res = spawnSync(process.execPath, [join(here, 'fetch-catalog.mjs')], { env: { ...process.env, CATALOG_API_URL: 'http://127.0.0.1:9' }, encoding: 'utf8' });
    expect(res.status).toBe(1);
  });
});

function page(path, { title = `Страница ${path}`, description = `Описание ${path}`, h1 = 1, canonical = `${SITE}${path}`, robots = 'index,follow', body = '', jsonld = true } = {}) {
  const ld = [{ '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: [{ '@type': 'ListItem', position: 1, name: 'Главная', item: `${SITE}/` }] }];
  if (path === '/') ld.push({ '@context': 'https://schema.org', '@type': 'WebSite', name: 'x' });
  return `<!doctype html><html lang="ru"><head><meta name="viewport" content="width=device-width"><title>${title}</title>
<meta name="description" content="${description}"><meta name="robots" content="${robots}"><link rel="canonical" href="${canonical}">
<meta property="og:title" content="t"><meta property="og:description" content="d"><meta property="og:url" content="${canonical}"><meta property="og:image" content="${SITE}/og.jpg">
${jsonld ? ld.map((d) => `<script type="application/ld+json">${JSON.stringify(d)}</script>`).join('') : ''}</head>
<body><nav class="breadcrumbs"></nav>${Array.from({ length: h1 }, () => '<h1>H</h1>').join('')}${body}</body></html>`;
}

function makeDist(pages, { robots, sitemap } = {}) {
  const dir = mkdtempSync(join(tmpdir(), 'seo-'));
  const write = (rel, content) => {
    mkdirSync(dirname(join(dir, rel)), { recursive: true });
    writeFileSync(join(dir, rel), content);
  };
  write('og.jpg', 'x');
  write('404.html', page('/404.html', { robots: 'noindex' }));
  write('500.html', page('/500.html', { robots: 'noindex' }));
  for (const [path, html] of Object.entries(pages)) write(path === '/' ? 'index.html' : `${path.slice(1)}index.html`, html);
  const paths = Object.keys(pages);
  write('sitemap.xml', sitemap ?? `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${paths.map((p) => `<url><loc>${SITE}${p}</loc><lastmod>2026-10-01</lastmod></url>`).join('')}</urlset>`);
  write('robots.txt', robots ?? `User-agent: *\nDisallow: /account/\nDisallow: /cart/\nDisallow: /checkout/\n\nSitemap: ${SITE}/sitemap.xml\n`);
  return dir;
}

function runSeoCheck(dir) {
  return spawnSync(process.execPath, [join(here, 'seo-check.mjs')], { env: { ...process.env, SEO_DIST: dir }, encoding: 'utf8' });
}

describe('seo-check', () => {
  it('проходит на корректной сборке', () => {
    const res = runSeoCheck(makeDist({ '/': page('/'), '/kontakty/': page('/kontakty/', { body: '<a href="/">Главная</a><img src="/og.jpg" alt="x" width="1" height="1">' }) }));
    expect(res.stderr).toBe('');
    expect(res.status).toBe(0);
    expect(res.stdout).toContain('ошибок нет');
  });

  it('ловит дубли title, лишний h1, чужой canonical, битые ссылки и служебные пути', () => {
    const dir = makeDist({
      '/': page('/'),
      '/a/': page('/a/', { title: 'Одинаковый', h1: 2 }),
      '/b/': page('/b/', { title: 'Одинаковый', canonical: `${SITE}/other/`, body: '<a href="/nope/">x</a><a href="/admin/">x</a><img src="/og.jpg">' }),
    });
    const res = runSeoCheck(dir);
    expect(res.status).toBe(1);
    for (const msg of ['дубль title', 'h1: 2', 'canonical', 'битая внутренняя ссылка /nope/', 'ссылка на служебный раздел /admin/', 'img без alt']) {
      expect(res.stderr).toContain(msg);
    }
  });

  it('ловит старый домен, отсутствие JSON-LD и длинный title', () => {
    const res = runSeoCheck(makeDist({ '/': page('/'), '/x/': page('/x/', { title: 'Т'.repeat(70), jsonld: false, body: 'zefiripryanik.ru' }) }));
    expect(res.status).toBe(1);
    expect(res.stderr).toContain('старый домен');
    expect(res.stderr).toContain('нет JSON-LD');
    expect(res.stderr).toContain('title длиннее 60');
  });

  it('ловит staging-robots и неполный sitemap', () => {
    const res = runSeoCheck(makeDist({ '/': page('/'), '/x/': page('/x/') }, {
      robots: 'User-agent: *\nDisallow: /\n',
      sitemap: `<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>${SITE}/</loc></url></urlset>`,
    }));
    expect(res.status).toBe(1);
    expect(res.stderr).toContain('закрывает весь сайт');
    expect(res.stderr).toContain('не в sitemap: /x/');
    expect(res.stderr).toContain('без lastmod');
  });

  it('noindex на главной — ошибка (не production-сборка)', () => {
    const res = runSeoCheck(makeDist({ '/': page('/', { robots: 'noindex,follow' }) }));
    expect(res.status).toBe(1);
    expect(res.stderr).toContain('noindex на главной');
  });

  it('без сборки сообщает об этом', () => {
    const res = runSeoCheck(join(tmpdir(), 'nonexistent-dist-xyz'));
    expect(res.status).toBe(1);
    expect(res.stderr).toContain('сначала соберите');
  });
});

describe('serve-dist', () => {
  it('скрипт синтаксически корректен', () => {
    expect(() => execFileSync(process.execPath, ['--check', join(here, 'serve-dist.mjs')])).not.toThrow();
    expect(() => execFileSync(process.execPath, ['--check', join(here, 'postbuild.mjs')])).not.toThrow();
  });
});
