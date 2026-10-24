#!/usr/bin/env node
// Готовит данные каталога для пререндера: src/data/catalog.generated.json.
// CATALOG_API_URL задан (CI деплоя) → GET {CATALOG_API_URL}/api/catalog/export, при ошибке сборка падает
// (чтобы не выложить устаревшую статику). Не задан (PR, локально) → берём backend/database/data/catalog-seed.json.
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const target = join(root, 'src', 'data', 'catalog.generated.json');
const seedPath = join(root, '..', 'backend', 'database', 'data', 'catalog-seed.json');

const seo = (s = {}) => ({
  title: s.title ?? null,
  description: s.description ?? null,
  h1: s.h1 ?? null,
  text: s.text ?? null,
  noindex: Boolean(s.noindex),
});

export function fromSeed(seed, now = new Date().toISOString()) {
  let variantId = 0;
  return {
    generatedAt: now,
    categories: seed.categories.map((c, i) => ({
      id: i + 1,
      slug: c.slug,
      name: c.name,
      intro: c.intro,
      sort: c.sort,
      seo: seo(c.seo),
      crossListed: c.cross_listed ?? [],
      items: c.items ?? [],
      updatedAt: now,
    })),
    products: seed.products.map((p, i) => ({
      id: i + 1,
      slug: p.slug,
      categorySlug: p.category,
      name: p.name,
      description: p.description,
      isPp: p.is_pp,
      media: p.media,
      variants: p.variants.map((v) => ({
        id: ++variantId,
        sku: v.sku,
        name: v.name,
        price: v.price,
        priceFrom: v.price_from,
        inStock: v.stock > 0,
        stock: v.stock,
        packSize: v.pack_size,
      })),
      rating: null,
      related: p.related ?? [],
      seo: seo(p.seo),
      updatedAt: now,
    })),
    deliveryZones: seed.delivery_zones ?? [],
    seoPages: [],
    reviews: [],
  };
}

async function main() {
  const api = process.env.CATALOG_API_URL;
  let catalog;
  if (api) {
    const url = `${api.replace(/\/$/, '')}/api/catalog/export`;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`catalog: ${url} → HTTP ${res.status}`);
    catalog = await res.json();
    console.log(`catalog: из API ${url}`);
  } else {
    catalog = fromSeed(JSON.parse(readFileSync(seedPath, 'utf8')), '2026-01-01T00:00:00.000Z');
    console.log('catalog: из seed (CATALOG_API_URL не задан)');
  }
  writeFileSync(target, JSON.stringify(catalog, null, 2) + '\n');
  console.log(`catalog: ${catalog.categories.length} категорий, ${catalog.products.length} товаров`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((e) => {
    console.error(e.message);
    process.exit(1);
  });
}
