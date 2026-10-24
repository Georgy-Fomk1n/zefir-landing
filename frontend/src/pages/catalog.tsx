import { formatPrice, type Product } from '@zefir/shared';
import { useMemo, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { Breadcrumbs, CARD_SIZES, ChannelButtons, crumbs, ProductCard, useLiveProduct } from '../components/common';
import { pages } from '../content/pages';
import { catalog, categories, categoryBySlug, categoryUrl, minPrice, productBySlug, productsInCategory, productUrl, reviewsFor } from '../data/catalog';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useCart } from '../lib/cart';
import { LazyVideo, Picture, preloadSrcSet } from '../media';
import { itemList, product as productLd } from '../seo/jsonld';
import { Seo } from '../seo/Seo';
import NotFound from './NotFound';

export const PAGE_SIZE = 24;

type Sort = 'default' | 'price_asc' | 'price_desc';

function sortProducts(list: Product[], sort: Sort): Product[] {
  if (sort === 'default') return list;
  const dir = sort === 'price_asc' ? 1 : -1;
  return [...list].sort((a, b) => (minPrice(a).price - minPrice(b).price) * dir);
}

export function CatalogIndex() {
  const meta = pages.catalog;
  const bc = crumbs({ name: 'Каталог', path: meta.path });
  return (
    <>
      <Seo title={meta.title} description={meta.description} path={meta.path} og={meta.og}
        jsonLd={[bc.jsonLd, itemList(meta.h1, catalog.products)]} />
      <div className="container page">
        <Breadcrumbs items={bc.items} />
        <h1>{meta.h1}</h1>
        <div className="category-tiles">
          {categories.map((c) => (
            <Link key={c.slug} to={categoryUrl(c.slug)} className="tile">
              <span className="tile__title">{c.name}</span>
              <span className="tile__text">{c.intro}</span>
            </Link>
          ))}
        </div>
        {categories.filter((c) => productsInCategory(c.slug).length > 0).map((c) => (
          <section key={c.slug} className="catalog-block">
            <h2><Link to={categoryUrl(c.slug)}>{c.name}</Link></h2>
            <div className="grid grid--3">
              {productsInCategory(c.slug).slice(0, 3).map((p) => <ProductCard key={`${c.slug}-${p.slug}`} product={p} />)}
            </div>
          </section>
        ))}
      </div>
    </>
  );
}

export function CategoryPage() {
  const { category: slug = '', page: pageParam } = useParams();
  const [params, setParams] = useSearchParams();
  const category = categoryBySlug(slug);
  const sort = (params.get('sort') as Sort | null) ?? 'default';
  const ppOnly = params.get('pp') === '1';
  const all = useMemo(() => productsInCategory(slug), [slug]);
  const filtered = useMemo(() => sortProducts(ppOnly ? all.filter((p) => p.isPp) : all, sort), [all, ppOnly, sort]);

  if (!category) return <NotFound />;
  const page = Math.max(1, Number(pageParam ?? 1));
  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  if (page > pageCount) return <NotFound />;
  const visible = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  const path = categoryUrl(category.slug);
  const h1 = category.seo.h1 ?? category.name;
  const bc = crumbs({ name: 'Каталог', path: '/catalog/' }, { name: category.name, path });
  // Параметры сортировки/фильтра и страницы пагинации: canonical на основную категорию, noindex,follow
  const isVariant = params.toString() !== '' || page > 1;
  const firstImage = visible[0]?.media.find((m) => m.type === 'image')?.name;

  return (
    <>
      <Seo
        title={category.seo.title ?? `${category.name} — купить в Москве`}
        description={category.seo.description ?? category.intro}
        path={path}
        og={all[0]?.media.find((m) => m.type === 'image')?.name}
        noindex={category.seo.noindex || isVariant}
        jsonLd={[bc.jsonLd, itemList(h1, all)]}
        preload={firstImage ? { srcSet: preloadSrcSet(firstImage), sizes: CARD_SIZES } : undefined}
      />
      <div className="container page">
        <Breadcrumbs items={bc.items} />
        <h1>{h1}</h1>
        <p className="lead">{category.intro}</p>

        {all.length > 1 && (
          <div className="toolbar" role="group" aria-label="Сортировка и фильтры">
            <label>
              Сортировка{' '}
              <select value={sort} onChange={(e) => {
                const next = new URLSearchParams(params);
                if (e.target.value === 'default') next.delete('sort');
                else next.set('sort', e.target.value);
                setParams(next, { replace: true });
              }}>
                <option value="default">по умолчанию</option>
                <option value="price_asc">сначала дешевле</option>
                <option value="price_desc">сначала дороже</option>
              </select>
            </label>
            {all.some((p) => p.isPp) && all.some((p) => !p.isPp) && (
              <label>
                <input type="checkbox" checked={ppOnly} onChange={(e) => {
                  const next = new URLSearchParams(params);
                  if (e.target.checked) next.set('pp', '1');
                  else next.delete('pp');
                  setParams(next, { replace: true });
                }} /> только ПП
              </label>
            )}
          </div>
        )}

        {visible.length > 0 && (
          <div className="grid grid--3">
            {visible.map((p, i) => <ProductCard key={p.slug} product={p} headingLevel={2} priority={i === 0} />)}
          </div>
        )}

        {category.items.length > 0 && (
          <div className="other">
            <LazyVideo name="bento" label="Бенто-торт кондитерской Зефир и пряник" className="other__video" />
            <ul className="other__list">
              {category.items.map((i) => <li key={i}><h2>{i}</h2></li>)}
            </ul>
            <p>Уточнить наличие и заказать: <Link to={pages.contacts.path}>контакты</Link>.</p>
            <ChannelButtons compact />
          </div>
        )}

        {pageCount > 1 && (
          <nav className="pagination" aria-label="Страницы">
            {Array.from({ length: pageCount }, (_, i) => i + 1).map((n) => (
              <Link key={n} to={n === 1 ? path : `${path}page/${n}/`} aria-current={n === page ? 'page' : undefined}>{n}</Link>
            ))}
          </nav>
        )}

        {category.seo.text && <div className="seo-text">{category.seo.text.split('\n\n').map((t) => <p key={t}>{t}</p>)}</div>}

        <nav className="related-categories" aria-label="Другие разделы">
          <h2>Другие разделы</h2>
          <ul>
            {categories.filter((c) => c.slug !== category.slug).map((c) => <li key={c.slug}><Link to={categoryUrl(c.slug)}>{c.name}</Link></li>)}
            <li><Link to={pages.individual.path}>{pages.individual.label}</Link></li>
          </ul>
        </nav>
      </div>
    </>
  );
}

export function ProductPage() {
  const { category: categorySlug = '', product: slug = '' } = useParams();
  const staticProduct = productBySlug(slug);
  if (!staticProduct || staticProduct.categorySlug !== categorySlug) return <NotFound />;
  return <ProductView key={slug} staticProduct={staticProduct} />;
}

function ProductView({ staticProduct }: { staticProduct: Product }) {
  const { data: product } = useLiveProduct(staticProduct.slug, staticProduct);
  const p = product ?? staticProduct;
  const [variantId, setVariantId] = useState(p.variants[0]?.id);
  const variant = p.variants.find((v) => v.id === variantId) ?? p.variants[0];
  const cart = useCart();
  const [added, setAdded] = useState(false);
  const category = categoryBySlug(p.categorySlug)!;
  const path = productUrl(p);
  const reviews = reviewsFor(p.slug);
  const image = p.media.find((m) => m.type === 'image') ?? p.media[0];
  const video = p.media.find((m) => m.type === 'video');
  const related = p.related.map(productBySlug).filter((x): x is Product => Boolean(x));
  const bc = crumbs({ name: 'Каталог', path: '/catalog/' }, { name: category.name, path: categoryUrl(category.slug) }, { name: p.name, path });
  const sizes = '(min-width: 1024px) 50vw, 100vw';

  return (
    <>
      <Seo
        title={staticProduct.seo.title ?? `${p.name} — купить в Москве`}
        description={staticProduct.seo.description ?? `${p.name}: ${p.description}. ${formatPrice(minPrice(p).price, minPrice(p).from)}. Самовывоз с ул. Базовская, 17 и доставка по Москве.`}
        path={path}
        og={image?.type === 'image' ? image.name : undefined}
        ogType="product"
        noindex={staticProduct.seo.noindex}
        jsonLd={[bc.jsonLd, productLd(staticProduct, reviews)]}
        preload={image?.type === 'image' ? { srcSet: preloadSrcSet(image.name), sizes } : undefined}
      />
      <div className="container page">
        <Breadcrumbs items={bc.items} />
        <div className="product">
          <div className="product__media">
            {image && <Picture name={image.name} alt={image.alt} sizes={sizes} priority />}
            {video && <LazyVideo name={video.name} label={video.alt} className="product__video" />}
          </div>
          <div className="product__info">
            <h1>{staticProduct.seo.h1 ?? p.name}</h1>
            {p.isPp && <p className="badge">ПП · без сахара</p>}
            <p className="product__desc">{p.description}</p>

            {p.variants.length > 1 && (
              <fieldset className="variants">
                <legend>Вариант</legend>
                {p.variants.map((v) => (
                  <label key={v.id}>
                    <input type="radio" name="variant" value={v.id} checked={v.id === variant?.id} onChange={() => setVariantId(v.id)} />
                    {v.name} — {formatPrice(v.price, v.priceFrom)}
                  </label>
                ))}
              </fieldset>
            )}

            {variant && (
              <>
                <p className="price price--lg">{formatPrice(variant.price, variant.priceFrom)}</p>
                <p className="muted">{variant.name}{variant.inStock ? '' : ' · нет в наличии'}</p>
                <div className="btn-row">
                  <button type="button" className="btn btn-primary" disabled={!variant.inStock}
                    onClick={() => { cart.add(variant.id); setAdded(true); }}>
                    {added ? 'Добавлено' : 'В корзину'}
                  </button>
                  {added && <Link to="/cart/" className="btn btn-secondary">Перейти в корзину</Link>}
                </div>
              </>
            )}
            <FavoriteButton productId={p.id} />
            <p className="muted">Или закажите удобным способом:</p>
            <ChannelButtons compact />
            {staticProduct.seo.text && <div className="seo-text"><p>{staticProduct.seo.text}</p></div>}
          </div>
        </div>

        <section className="product-reviews">
          <h2>Отзывы</h2>
          {reviews.length === 0 ? (
            <p className="muted">Пока нет отзывов. Отзыв можно оставить в <Link to="/account/orders/">личном кабинете</Link> после получения заказа.</p>
          ) : (
            <ul className="reviews">
              {reviews.map((r) => (
                <li key={r.id}>
                  <p><strong>{r.author}</strong> · {'★'.repeat(r.rating)}</p>
                  <p>{r.text}</p>
                </li>
              ))}
            </ul>
          )}
        </section>

        {related.length > 0 && (
          <section>
            <h2>С этим покупают</h2>
            <div className="grid grid--3">{related.map((r) => <ProductCard key={r.slug} product={r} />)}</div>
          </section>
        )}
      </div>
    </>
  );
}

/** Избранное — только для вошедших покупателей; гостю кнопка не показывается. */
function FavoriteButton({ productId }: { productId: number }) {
  const { user } = useAuth();
  const qc = useQueryClient();
  const favs = useQuery({
    queryKey: ['favorites'],
    queryFn: () => api.get<{ data: { id: number }[] }>('/account/favorites').then((r) => r.data),
    enabled: Boolean(user),
  });
  const isFav = (favs.data ?? []).some((f) => f.id === productId);
  const toggle = useMutation({
    mutationFn: () => (isFav ? api.delete(`/account/favorites/${productId}`) : api.post(`/account/favorites/${productId}`)),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['favorites'] }),
  });
  if (!user) return null;
  return (
    <button type="button" className="link" aria-pressed={isFav} disabled={toggle.isPending} onClick={() => toggle.mutate()}>
      {isFav ? '♥ В избранном' : '♡ В избранное'}
    </button>
  );
}
