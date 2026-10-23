import { brand, channels, formatPrice, isOpenAt, type Product } from '@zefir/shared';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { minPrice, productUrl } from '../data/catalog';
import { api } from '../lib/api';
import { reachGoal } from '../lib/metrika';
import { Picture } from '../media';
import { breadcrumbs as breadcrumbsLd, type Crumb } from '../seo/jsonld';
import { ChannelIcon } from './icons';

/** Хлебные крошки: HTML-разметка + BreadcrumbList (JSON-LD отдаёт страница через <Seo>). */
export function Breadcrumbs({ items }: { items: Crumb[] }) {
  return (
    <nav aria-label="Хлебные крошки" className="breadcrumbs">
      <ol>
        {items.map((c, i) => (
          <li key={c.path}>
            {i < items.length - 1 ? <Link to={c.path}>{c.name}</Link> : <span aria-current="page">{c.name}</span>}
          </li>
        ))}
      </ol>
    </nav>
  );
}

export function crumbs(...items: Crumb[]): { items: Crumb[]; jsonLd: Record<string, unknown> } {
  const all = [{ name: 'Главная', path: '/' }, ...items];
  return { items: all, jsonLd: breadcrumbsLd(all) };
}

/** Кнопки существующих каналов заказа — равноправно с корзиной. */
export function ChannelButtons({ compact = false }: { compact?: boolean }) {
  return (
    <ul className={compact ? 'channels channels--compact' : 'channels'}>
      {channels.map((c) => (
        <li key={c.id}>
          <a href={c.url} target="_blank" rel="noopener" onClick={() => reachGoal(c.goal)} className="channel" title={c.title}>
            <ChannelIcon id={c.id} size={compact ? 22 : 28} />
            <span>{c.title}</span>
          </a>
        </li>
      ))}
    </ul>
  );
}

/** iframe, который грузится только при приближении к экрану (виджеты Яндекс Карт). */
export function LazyIframe({ src, title, height }: { src: string; title: string; height: number }) {
  const ref = useRef<HTMLDivElement>(null);
  const [show, setShow] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const io = new IntersectionObserver(([e]) => {
      if (e.isIntersecting) {
        setShow(true);
        io.disconnect();
      }
    }, { rootMargin: '300px' });
    io.observe(el);
    return () => io.disconnect();
  }, []);
  return (
    <div ref={ref} className="lazy-frame" style={{ height }}>
      {show ? <iframe src={src} title={title} loading="lazy" style={{ width: '100%', height: '100%', border: 0 }} allowFullScreen /> : <span className="lazy-frame__ph">{title}</span>}
    </div>
  );
}

export function OpeningStatus() {
  const [open, setOpen] = useState<boolean | null>(null);
  useEffect(() => {
    const tick = () => setOpen(isOpenAt(new Date()));
    tick();
    const t = window.setInterval(tick, 60_000);
    return () => window.clearInterval(t);
  }, []);
  if (open === null) return null;
  return (
    <p className={`status ${open ? 'status--open' : 'status--closed'}`}>
      <span className="status__dot" />
      {open ? 'Сейчас открыто' : 'Сейчас закрыто'}
    </p>
  );
}

export function Hours() {
  return (
    <>
      <OpeningStatus />
      {brand.hours.lines.map((l) => (
        <p key={l.label}>{l.label}: {l.value}</p>
      ))}
    </>
  );
}

/** Живая цена/остаток: статика содержит цену на момент сборки, после гидратации уточняем у API. */
export function useLiveProduct(slug: string, fallback: Product) {
  return useQuery({
    queryKey: ['product-live', slug],
    queryFn: () => api.get<{ data: Product }>(`/catalog/products/${slug}`).then((r) => r.data),
    initialData: fallback,
    staleTime: 0,
    initialDataUpdatedAt: 0,
    retry: false,
  });
}

export function ProductCard({ product, headingLevel = 3 }: { product: Product; headingLevel?: 2 | 3 }) {
  const media = product.media[0];
  const { price, from } = minPrice(product);
  const Heading = `h${headingLevel}` as 'h2' | 'h3';
  return (
    <article className="card">
      <Link to={productUrl(product)} className="card__image">
        {media && <Picture name={media.name} alt={media.alt} sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" />}
      </Link>
      <div className="card__body">
        <Heading className="card__title"><Link to={productUrl(product)}>{product.name}</Link></Heading>
        <p className="card__text">{product.description}</p>
        <div className="card__footer">
          <span className="price">{formatPrice(price, from)}</span>
          <Link to={productUrl(product)} className="btn btn-primary btn-sm">Заказать</Link>
        </div>
      </div>
    </article>
  );
}

export function Section({ title, lead, children, id, as = 'h2' }: { title: string; lead?: string; children?: ReactNode; id?: string; as?: 'h1' | 'h2' }) {
  const H = as;
  return (
    <section className="section" id={id}>
      <div className="container">
        <header className="section-header">
          <H>{title}</H>
          {lead && <p>{lead}</p>}
        </header>
        {children}
      </div>
    </section>
  );
}

export function FaqList({ items }: { items: { q: string; a: string }[] }) {
  // <details> работает без JS: ответы есть в HTML и видны роботу
  return (
    <div className="faq">
      {items.map((i) => (
        <details key={i.q} className="faq__item">
          <summary>{i.q}</summary>
          <p>{i.a}</p>
        </details>
      ))}
    </div>
  );
}
