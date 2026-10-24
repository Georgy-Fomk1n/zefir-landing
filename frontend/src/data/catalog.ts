import type { Category, Product, Review, Seo } from '@zefir/shared';
import raw from './catalog.generated.json';

export interface DeliveryZone {
  id: number;
  name: string;
  price: number;
  minOrder: number;
  freeFrom: number | null;
  etaText: string;
}

export interface CatalogCategory extends Category {
  items: string[];
}

interface BuildCatalog {
  generatedAt: string;
  categories: CatalogCategory[];
  products: Product[];
  deliveryZones: DeliveryZone[];
  reviews: (Review & { productSlug: string })[];
  /** Переопределения SEO контентных страниц из админки (пустое поле — значение из кода) */
  seoPages?: ({ path: string } & Seo)[];
}

// Снимок каталога на момент сборки. Живые цена и остаток уточняются запросом к API после гидратации.
export const catalog = raw as unknown as BuildCatalog;

export const categories = [...catalog.categories].sort((a, b) => a.sort - b.sort);

export function categoryBySlug(slug: string): CatalogCategory | undefined {
  return catalog.categories.find((c) => c.slug === slug);
}

export function productBySlug(slug: string): Product | undefined {
  return catalog.products.find((p) => p.slug === slug);
}

export function productsInCategory(slug: string): Product[] {
  const category = categoryBySlug(slug);
  const own = catalog.products.filter((p) => p.categorySlug === slug);
  const cross = (category?.crossListed ?? []).map(productBySlug).filter((p): p is Product => Boolean(p));
  return [...own, ...cross];
}

export function productUrl(p: Pick<Product, 'slug' | 'categorySlug'>): string {
  return `/catalog/${p.categorySlug}/${p.slug}/`;
}

export function categoryUrl(slug: string): string {
  return `/catalog/${slug}/`;
}

export function minPrice(p: Product): { price: number; from: boolean } {
  const prices = p.variants.map((v) => v.price);
  const price = Math.min(...prices);
  return { price, from: p.variants.length > 1 || p.variants.some((v) => v.priceFrom) };
}

export function reviewsFor(slug: string) {
  return catalog.reviews.filter((r) => r.productSlug === slug);
}

/** Все индексируемые URL каталога — для getStaticPaths, sitemap и SEO-проверок. */
export function catalogPaths(): string[] {
  return [
    ...categories.filter((c) => !c.seo.noindex).map((c) => categoryUrl(c.slug)),
    ...catalog.products.filter((p) => !p.seo.noindex).map(productUrl),
  ];
}

export function seoOverride(path: string): Partial<Seo> {
  const o = catalog.seoPages?.find((p) => p.path === path);
  if (!o) return {};
  return Object.fromEntries(Object.entries(o).filter(([k, v]) => k !== 'path' && v !== null && v !== '')) as Partial<Seo>;
}
