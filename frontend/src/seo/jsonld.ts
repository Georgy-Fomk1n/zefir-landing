import { absoluteUrl, brand, channels, schemaPrice, SITE_URL, yandex, type Product } from '@zefir/shared';
import { minPrice, productUrl } from '../data/catalog';
import { mediaUrl } from '../media';

type Json = Record<string, unknown>;

export const ORG_ID = `${SITE_URL}/#organization`;

export function organization(): Json {
  return {
    '@context': 'https://schema.org',
    '@type': ['Organization', 'Bakery'],
    '@id': ORG_ID,
    name: brand.name,
    description: 'Корпусные пирожные ручной работы в Москве. Шоколадный корпус, нежная начинка, ПП-варианты без сахара.',
    url: `${SITE_URL}/`,
    logo: absoluteUrl('/media/logo-160.png'),
    image: absoluteUrl(mediaUrl('banan-razrez', 1200)),
    telephone: brand.phone,
    priceRange: '₽₽',
    servesCuisine: 'Десерты',
    address: {
      '@type': 'PostalAddress',
      streetAddress: brand.address.street,
      addressLocality: brand.address.locality,
      addressCountry: brand.address.country,
    },
    geo: { '@type': 'GeoCoordinates', latitude: brand.geo.lat, longitude: brand.geo.lng },
    openingHoursSpecification: [{
      '@type': 'OpeningHoursSpecification',
      dayOfWeek: brand.hours.schemaDays,
      opens: brand.hours.opens,
      closes: brand.hours.closes,
    }],
    hasMap: yandex.orgUrl,
    sameAs: channels.map((c) => c.url),
  };
}

export function website(): Json {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    '@id': `${SITE_URL}/#website`,
    url: `${SITE_URL}/`,
    name: brand.name,
    inLanguage: 'ru-RU',
    publisher: { '@id': ORG_ID },
    potentialAction: {
      '@type': 'SearchAction',
      target: { '@type': 'EntryPoint', urlTemplate: `${SITE_URL}/poisk/?q={search_term_string}` },
      'query-input': 'required name=search_term_string',
    },
  };
}

export interface Crumb {
  name: string;
  path: string;
}

export function breadcrumbs(items: Crumb[]): Json {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((c, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: c.name,
      item: absoluteUrl(c.path),
    })),
  };
}

export function product(p: Product, reviews: { author: string; rating: number; text: string; createdAt: string }[]): Json {
  const image = p.media.map((m) => absoluteUrl(m.type === 'image' ? mediaUrl(m.name, 1200) : `/media/${m.name}-poster.webp`));
  const offers = p.variants.map((v) => ({
    '@type': 'Offer',
    sku: v.sku,
    name: v.name,
    price: schemaPrice(v.price),
    priceCurrency: 'RUB',
    availability: v.inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    url: absoluteUrl(productUrl(p)),
    seller: { '@id': ORG_ID },
    ...(v.priceFrom ? { priceSpecification: { '@type': 'PriceSpecification', minPrice: schemaPrice(v.price), priceCurrency: 'RUB' } } : {}),
  }));
  const json: Json = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: p.name,
    description: p.description,
    image,
    sku: p.variants[0]?.sku,
    brand: { '@type': 'Brand', name: brand.name },
    offers: offers.length === 1 ? offers[0] : {
      '@type': 'AggregateOffer',
      priceCurrency: 'RUB',
      lowPrice: schemaPrice(minPrice(p).price),
      highPrice: schemaPrice(Math.max(...p.variants.map((v) => v.price))),
      offerCount: offers.length,
      offers,
    },
  };
  // Только отзывы, оставленные на самом сайте. Отзывы из виджета Яндекс Карт в разметку не попадают.
  if (p.rating && p.rating.count > 0 && reviews.length > 0) {
    json.aggregateRating = { '@type': 'AggregateRating', ratingValue: p.rating.value.toFixed(1), reviewCount: p.rating.count };
    json.review = reviews.slice(0, 5).map((r) => ({
      '@type': 'Review',
      author: { '@type': 'Person', name: r.author },
      datePublished: r.createdAt.slice(0, 10),
      reviewBody: r.text,
      reviewRating: { '@type': 'Rating', ratingValue: r.rating, bestRating: 5 },
    }));
  }
  return json;
}

export function itemList(name: string, products: Product[]): Json {
  return {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    name,
    itemListElement: products.map((p, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      url: absoluteUrl(productUrl(p)),
      name: p.name,
    })),
  };
}

export function faqPage(items: { q: string; a: string }[]): Json {
  return {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: items.map((i) => ({
      '@type': 'Question',
      name: i.q,
      acceptedAnswer: { '@type': 'Answer', text: i.a },
    })),
  };
}
