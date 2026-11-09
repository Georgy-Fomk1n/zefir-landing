import type { Paginated, Product } from '@zefir/shared';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ProductCard } from '../components/common';
import { catalog } from '../data/catalog';
import { api } from '../lib/api';
import { Seo } from '../seo/Seo';

/** Поиск (цель SearchAction). Закрыт от индексации, результаты — из API (MySQL FULLTEXT). */
export default function Search() {
  const [params, setParams] = useSearchParams();
  // Запрос из URL известен только в браузере: до монтирования рендерим как пререндер (пустой запрос)
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);
  const q = mounted ? params.get('q')?.trim() ?? '' : '';
  const result = useQuery({
    queryKey: ['search', q],
    enabled: q.length >= 2,
    queryFn: () => api.get<Paginated<Product>>(`/catalog/products?q=${encodeURIComponent(q)}`),
    retry: false,
  });
  // Без API (или до ответа) ищем по снимку каталога, чтобы поиск работал всегда
  const local = catalog.products.filter((p) => `${p.name} ${p.description}`.toLowerCase().includes(q.toLowerCase()));
  const items = result.data?.data ?? (q.length >= 2 ? local : []);
  return (
    <>
      <Seo title="Поиск — Зефир и пряник" description="Поиск по каталогу пирожных" path="/poisk/" noindex />
      <div className="container page">
        <h1>Поиск</h1>
        <form role="search" onSubmit={(e) => {
          e.preventDefault();
          const value = new FormData(e.currentTarget).get('q');
          setParams(value ? { q: String(value) } : {});
        }}>
          <input key={q} type="search" name="q" defaultValue={q} placeholder="Например, фисташка" aria-label="Поисковый запрос" minLength={2} />
          <button type="submit" className="btn btn-primary btn-sm">Найти</button>
        </form>
        {q && items.length === 0 && <p className="muted">Ничего не нашлось по запросу «{q}».</p>}
        <div className="grid grid--3">{items.map((p) => <ProductCard key={p.slug} product={p} headingLevel={2} />)}</div>
      </div>
    </>
  );
}
