import { describe, expect, it } from 'vitest';
import { catalog, productBySlug } from '../data/catalog';
import { breadcrumbs, faqPage, itemList, organization, product, website } from './jsonld';

describe('JSON-LD', () => {
  it('организация: кириллический домен, NAP, часы, sameAs каналов', () => {
    const org = organization();
    expect(org['@type']).toEqual(['Organization', 'Bakery']);
    expect(org.url).toBe('https://зефир-и-пряник.рф/');
    expect(org.telephone).toBe('+79684791520');
    expect(org.address).toMatchObject({ streetAddress: 'ул. Базовская, 17', addressLocality: 'Москва' });
    expect(org.openingHoursSpecification).toEqual([expect.objectContaining({ opens: '10:00', closes: '21:00' })]);
    expect(org.sameAs).toContain('https://flowwow.com/shop/zefir-i-pryanik');
    expect(JSON.stringify(org)).not.toContain('zefiripryanik');
  });

  it('сайт с поиском', () => {
    expect(website().potentialAction).toMatchObject({ target: { urlTemplate: 'https://зефир-и-пряник.рф/poisk/?q={search_term_string}' } });
  });

  it('товар: Offer с ценой в рублях и наличием, без рейтинга при отсутствии отзывов', () => {
    const p = product(productBySlug('kofe')!, []);
    expect(p.offers).toMatchObject({ '@type': 'Offer', price: '450.00', priceCurrency: 'RUB', availability: 'https://schema.org/InStock' });
    expect(p.aggregateRating).toBeUndefined();
  });

  it('товар с несколькими вариантами — AggregateOffer, цена «от» — PriceSpecification', () => {
    const base = productBySlug('nabor-4')!;
    const multi = { ...base, variants: [...base.variants, { ...base.variants[0], id: 999, sku: 'X', price: 120000, inStock: false }] };
    const p = product(multi, []);
    expect(p.offers).toMatchObject({ '@type': 'AggregateOffer', lowPrice: '900.00', highPrice: '1200.00', offerCount: 2 });
    const offers = (p.offers as { offers: Record<string, unknown>[] }).offers;
    expect(offers[0].priceSpecification).toBeDefined();
    expect(offers[1].availability).toBe('https://schema.org/OutOfStock');
  });

  it('рейтинг только из отзывов сайта', () => {
    const base = productBySlug('kofe')!;
    const withRating = { ...base, rating: { value: 4.5, count: 2 } };
    expect(product(withRating, []).aggregateRating).toBeUndefined();
    const p = product(withRating, [{ author: 'Аня', rating: 5, text: 'Вкусно', createdAt: '2026-10-01T10:00:00Z' }]);
    expect(p.aggregateRating).toMatchObject({ ratingValue: '4.5', reviewCount: 2 });
    expect(p.review).toEqual([expect.objectContaining({ datePublished: '2026-10-01' })]);
  });

  it('видео-товар получает постер как изображение', () => {
    expect(product(productBySlug('pp-malina')!, []).image).toEqual(['https://зефир-и-пряник.рф/media/pp-malina-poster.webp']);
  });

  it('крошки, список и FAQ', () => {
    expect(breadcrumbs([{ name: 'Главная', path: '/' }]).itemListElement).toEqual([{ '@type': 'ListItem', position: 1, name: 'Главная', item: 'https://зефир-и-пряник.рф/' }]);
    expect((itemList('Вкусы', catalog.products.slice(0, 2)).itemListElement as unknown[])).toHaveLength(2);
    expect(faqPage([{ q: 'В?', a: 'О.' }]).mainEntity).toEqual([{ '@type': 'Question', name: 'В?', acceptedAnswer: { '@type': 'Answer', text: 'О.' } }]);
  });
});
