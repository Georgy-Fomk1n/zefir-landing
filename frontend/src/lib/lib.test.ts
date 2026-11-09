import { absoluteUrl, formatPrice, isOpenAt, schemaPrice } from '@zefir/shared';
import { describe, expect, it } from 'vitest';
import { categoryBySlug, catalogPaths, productsInCategory, productUrl } from '../data/catalog';
import { serializeJsonLd } from '../seo/Seo';
import { mergeLines } from './cart';

describe('деньги', () => {
  it('форматирует копейки в рубли с неразрывными пробелами', () => {
    expect(formatPrice(220000, true)).toBe('от 2 200 ₽');
    expect(formatPrice(45000)).toBe('450 ₽');
    expect(schemaPrice(45000)).toBe('450.00');
  });
});

describe('домен', () => {
  it('строит абсолютные URL с кириллическим хостом без перевода в punycode', () => {
    expect(absoluteUrl('/catalog/')).toBe('https://зефир-и-пряник.рф/catalog/');
  });
});

describe('часы работы (Москва)', () => {
  it('понедельник — выходной', () => {
    expect(isOpenAt(new Date('2026-10-26T12:00:00+03:00'))).toBe(false);
  });
  it('вторник 10:00–21:00', () => {
    expect(isOpenAt(new Date('2026-10-27T09:59:00+03:00'))).toBe(false);
    expect(isOpenAt(new Date('2026-10-27T10:00:00+03:00'))).toBe(true);
    expect(isOpenAt(new Date('2026-10-27T20:59:00+03:00'))).toBe(true);
    expect(isOpenAt(new Date('2026-10-27T21:00:00+03:00'))).toBe(false);
  });
});

describe('корзина', () => {
  it('слияние гостевой и серверной корзины не удваивает товар', () => {
    expect(mergeLines([{ variantId: 1, qty: 2 }, { variantId: 2, qty: 1 }], [{ variantId: 1, qty: 3 }]))
      .toEqual([{ variantId: 1, qty: 3 }, { variantId: 2, qty: 1 }]);
  });
});

describe('каталог', () => {
  it('подарки показывают наборы с каноническим URL категории «Наборы»', () => {
    const gifts = productsInCategory('podarki');
    expect(gifts.map((p) => p.slug)).toEqual(['nabor-4', 'nabor-10']);
    expect(productUrl(gifts[0])).toBe('/catalog/nabory/nabor-4/');
  });
  it('все пути каталога со слэшем на конце и уникальны', () => {
    const paths = catalogPaths();
    expect(new Set(paths).size).toBe(paths.length);
    expect(paths.every((p) => p.endsWith('/'))).toBe(true);
    expect(categoryBySlug('vkusy')?.name).toBe('Вкусы');
  });
});

describe('JSON-LD', () => {
  it('экранирует </script> внутри данных', () => {
    expect(serializeJsonLd({ a: '</script><b>' })).not.toContain('</script>');
  });
});
