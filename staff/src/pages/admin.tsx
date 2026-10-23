import { type Product } from '@zefir/shared';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { NavLink, Route, Routes } from 'react-router-dom';
import { api, API_URL, tokens } from '../lib/api';
import { errorText, JsonEditor, rub, Section, useList, useSave } from '../lib/ui';
import { DeliveryBoard } from './seller';

type AdminProduct = Product & { isActive: boolean; categoryId: number };

interface Category { id: number; slug: string; name: string; intro: string; sort: number; is_active: boolean; seo_title: string | null; seo_description: string | null; seo_h1: string | null; seo_text: string | null; noindex: boolean }

function ProductEditor({ product, categories, onDone }: { product: AdminProduct | null; categories: Category[]; onDone: () => void }) {
  const save = useSave(['admin-products'], (body: Record<string, unknown>) =>
    product ? api.put(`/staff/admin/products/${product.id}`, body) : api.post('/staff/admin/products', body));
  const initial = product
    ? {
      category_id: product.categoryId, slug: product.slug, name: product.name, description: product.description, is_pp: product.isPp,
      is_active: product.isActive, related: product.related, seo_title: product.seo.title, seo_description: product.seo.description,
      seo_h1: product.seo.h1, seo_text: product.seo.text, noindex: product.seo.noindex, media: product.media,
      variants: product.variants.map((v) => ({ id: v.id, sku: v.sku, name: v.name, pack_size: v.packSize, price: v.price, price_from: v.priceFrom, stock: v.stock, is_active: true })),
    }
    : { category_id: categories[0]?.id, slug: '', name: '', description: '', is_pp: false, is_active: true, related: [], seo_title: '', seo_description: '', seo_h1: '', seo_text: '', noindex: false, media: [], variants: [{ sku: '', name: '1 шт.', pack_size: 1, price: 0, price_from: false, stock: 0 }] };
  return (
    <div className="stack">
      <p className="muted">Цены — в копейках. Slug — латиницей через дефис. SEO: title до 60 символов, description до 160. После сохранения публичный сайт пересоберётся автоматически (смена цены — сразу, прочее — через 5 минут).</p>
      <JsonEditor value={initial} busy={save.isPending} error={save.error} onSave={(v) => save.mutate(v as Record<string, unknown>, { onSuccess: onDone })} />
    </div>
  );
}

function Products() {
  const products = useList<AdminProduct>(['admin-products'], '/staff/admin/products');
  const categories = useList<Category>(['admin-categories'], '/staff/admin/categories');
  const [editing, setEditing] = useState<AdminProduct | 'new' | null>(null);
  const variant = useSave(['admin-products'], ({ id, ...body }: { id: number; price?: number; stock?: number }) => api.patch(`/staff/admin/variants/${id}`, body));
  return (
    <Section title="Каталог" actions={<button type="button" className="btn btn-sm" onClick={() => setEditing('new')}>Добавить товар</button>}>
      {editing && <ProductEditor product={editing === 'new' ? null : editing} categories={categories.data ?? []} onDone={() => setEditing(null)} />}
      <table className="table">
        <thead><tr><th>Товар</th><th>Категория</th><th>Вариант</th><th>Цена, ₽</th><th>Остаток</th><th /></tr></thead>
        <tbody>
          {(products.data ?? []).flatMap((p) => p.variants.map((v, i) => (
            <tr key={v.id} className={p.isActive ? '' : 'muted'}>
              <td>{i === 0 ? p.name : ''}</td>
              <td>{i === 0 ? p.categorySlug : ''}</td>
              <td>{v.name} <code>{v.sku}</code></td>
              <td><input type="number" defaultValue={v.price / 100} min={0} step={10} onBlur={(e) => {
                const price = Math.round(Number(e.target.value) * 100);
                if (price !== v.price) variant.mutate({ id: v.id, price });
              }} /></td>
              <td><input type="number" defaultValue={v.stock} min={0} onBlur={(e) => {
                const stock = Number(e.target.value);
                if (stock !== v.stock) variant.mutate({ id: v.id, stock });
              }} /></td>
              <td>{i === 0 && <button type="button" className="link" onClick={() => setEditing(p)}>Изменить</button>}</td>
            </tr>
          )))}
        </tbody>
      </table>
      {variant.error ? <p className="error">{errorText(variant.error)}</p> : null}
    </Section>
  );
}

function Categories() {
  const categories = useList<Category>(['admin-categories'], '/staff/admin/categories');
  const [editing, setEditing] = useState<Category | null>(null);
  const save = useSave(['admin-categories'], (c: Category) => (c.id ? api.put(`/staff/admin/categories/${c.id}`, c) : api.post('/staff/admin/categories', c)));
  return (
    <Section title="Категории и SEO">
      <ul className="list">
        {(categories.data ?? []).map((c) => (
          <li key={c.id}><button type="button" className="link" onClick={() => setEditing(c)}>{c.name}</button> <code>/catalog/{c.slug}/</code> {c.noindex && <span className="badge">noindex</span>}</li>
        ))}
      </ul>
      {editing && <JsonEditor key={editing.id} value={editing} busy={save.isPending} error={save.error} onSave={(v) => save.mutate(v as Category, { onSuccess: () => setEditing(null) })} />}
    </Section>
  );
}

function GenericJsonList<T extends { id?: number }>({ title, path, savePath, empty, render }: { title: string; path: string; savePath: string; empty: T; render: (item: T) => string }) {
  const key = ['admin', path];
  const list = useList<T>(key, path);
  const [editing, setEditing] = useState<T | null>(null);
  const save = useSave(key, (item: T) => (item.id ? api.put(`${savePath}/${item.id}`, item) : api.post(savePath, item)));
  return (
    <Section title={title} actions={<button type="button" className="btn btn-sm" onClick={() => setEditing(empty)}>Добавить</button>}>
      <ul className="list">
        {(list.data ?? []).map((item) => <li key={item.id}><button type="button" className="link" onClick={() => setEditing(item)}>{render(item)}</button></li>)}
      </ul>
      {editing && <JsonEditor key={editing.id ?? 'new'} value={editing} busy={save.isPending} error={save.error} onSave={(v) => save.mutate(v as T, { onSuccess: () => setEditing(null) })} />}
    </Section>
  );
}

interface Promo { id?: number; code: string; type: string; value: number; used_count?: number; max_uses: number | null; is_active: boolean }
interface Zone { id?: number; name: string; price: number; min_order: number; free_from: number | null; eta_minutes: number; polygon: number[][] }
interface Plan { id?: number; name: string; price: number; period_days: number; daily_limit: number }
interface StaffUser { id?: number; name: string; email: string; roles: string[]; isActive?: boolean; password?: string }

function Users() {
  const key = ['admin-users'];
  const list = useList<StaffUser>(key, '/staff/admin/users?staff=1');
  const [editing, setEditing] = useState<StaffUser | null>(null);
  const save = useSave(key, (u: StaffUser) => (u.id ? api.put(`/staff/admin/users/${u.id}`, u) : api.post('/staff/admin/users', u)));
  const reset2fa = useSave(key, (id: number) => api.post(`/staff/admin/users/${id}/reset-2fa`));
  return (
    <Section title="Сотрудники и роли" actions={<button type="button" className="btn btn-sm" onClick={() => setEditing({ name: '', email: '', roles: ['seller'], password: '' })}>Добавить сотрудника</button>}>
      <p className="muted">Саморегистрации нет: продавцов и курьеров заводит администратор. Роли можно совмещать (seller + courier). Смена ролей завершает служебный сеанс сотрудника.</p>
      <table className="table">
        <thead><tr><th>Имя</th><th>Email</th><th>Роли</th><th /></tr></thead>
        <tbody>{(list.data ?? []).map((u) => (
          <tr key={u.id} className={u.isActive === false ? 'muted' : ''}>
            <td>{u.name}</td><td>{u.email}</td><td>{u.roles.join(', ')}</td>
            <td><button type="button" className="link" onClick={() => setEditing({ ...u, password: '' })}>Изменить</button>
              {u.roles.includes('admin') && <button type="button" className="link" onClick={() => u.id && reset2fa.mutate(u.id)}>Сбросить 2FA</button>}</td>
          </tr>
        ))}</tbody>
      </table>
      {editing && <JsonEditor key={editing.id ?? 'new'} value={{ ...editing, is_active: editing.isActive ?? true, isActive: undefined }} busy={save.isPending} error={save.error}
        onSave={(v) => save.mutate(v as StaffUser, { onSuccess: () => setEditing(null) })} />}
    </Section>
  );
}

function Reviews() {
  const key = ['admin-reviews'];
  const list = useList<{ id: number; author_name: string; rating: number; text: string; product: { name: string } }>(key, '/staff/admin/reviews?status=pending');
  const moderate = useSave(key, ({ id, status }: { id: number; status: string }) => api.post(`/staff/admin/reviews/${id}`, { status }));
  return (
    <Section title="Модерация отзывов">
      {(list.data ?? []).length === 0 && <p className="muted">Новых отзывов нет.</p>}
      <ul className="list">
        {(list.data ?? []).map((r) => (
          <li key={r.id} className="stack">
            <p><strong>{r.product.name}</strong> · {'★'.repeat(r.rating)} · {r.author_name}</p>
            <p>{r.text}</p>
            <div className="row">
              <button type="button" className="btn btn-sm" onClick={() => moderate.mutate({ id: r.id, status: 'approved' })}>Опубликовать</button>
              <button type="button" className="btn btn-sm btn-ghost" onClick={() => moderate.mutate({ id: r.id, status: 'rejected' })}>Отклонить</button>
            </div>
          </li>
        ))}
      </ul>
    </Section>
  );
}

function Slots() {
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const key = ['admin-slots', date];
  const list = useList<{ id: number; type: string; starts_at: string; ends_at: string; capacity: number; booked: number; is_active: boolean }>(key, `/staff/admin/slots?date=${date}`);
  const update = useSave(key, ({ id, ...body }: { id: number; capacity?: number; is_active?: boolean }) => api.patch(`/staff/admin/slots/${id}`, body));
  const generate = useSave(key, () => api.post('/staff/admin/slots/generate'));
  return (
    <Section title="Слоты и интервалы" actions={<button type="button" className="btn btn-sm" onClick={() => generate.mutate(undefined)}>Создать на неделю</button>}>
      <input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
      <table className="table">
        <thead><tr><th>Тип</th><th>Время</th><th>Занято</th><th>Лимит</th><th>Активен</th></tr></thead>
        <tbody>{(list.data ?? []).map((s) => (
          <tr key={s.id}>
            <td>{s.type === 'pickup' ? 'Самовывоз' : 'Курьер'}</td>
            <td>{s.starts_at.slice(0, 5)}–{s.ends_at.slice(0, 5)}</td>
            <td>{s.booked}</td>
            <td><input type="number" min={0} defaultValue={s.capacity} onBlur={(e) => Number(e.target.value) !== s.capacity && update.mutate({ id: s.id, capacity: Number(e.target.value) })} /></td>
            <td><input type="checkbox" checked={s.is_active} onChange={(e) => update.mutate({ id: s.id, is_active: e.target.checked })} /></td>
          </tr>
        ))}</tbody>
      </table>
    </Section>
  );
}

function SeoPages() {
  const key = ['admin-seo'];
  const list = useList<{ id: number; path: string; seo_title: string | null }>(key, '/staff/admin/seo-pages');
  const [editing, setEditing] = useState<Record<string, unknown> | null>(null);
  const save = useSave(key, (p: Record<string, unknown>) => api.post('/staff/admin/seo-pages', p));
  return (
    <Section title="SEO контентных страниц" actions={<button type="button" className="btn btn-sm" onClick={() => setEditing({ path: '/dostavka-i-oplata/', seo_title: '', seo_description: '', seo_h1: '', seo_text: '', noindex: false })}>Добавить</button>}>
      <p className="muted">Переопределяет title, description, H1 и SEO-текст страницы из docs/seo.md. Пустые поля — значения по умолчанию из кода.</p>
      <ul className="list">{(list.data ?? []).map((p) => <li key={p.id}><button type="button" className="link" onClick={() => setEditing(p)}>{p.path}</button> {p.seo_title}</li>)}</ul>
      {editing && <JsonEditor key={String(editing.path)} value={editing} busy={save.isPending} error={save.error} onSave={(v) => save.mutate(v as Record<string, unknown>, { onSuccess: () => setEditing(null) })} />}
    </Section>
  );
}

function Reports() {
  const [from, setFrom] = useState(() => new Date(Date.now() - 30 * 864e5).toISOString().slice(0, 10));
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10));
  const report = useQuery({
    queryKey: ['report', from, to],
    queryFn: () => api.get<{ data: { orders: number; revenue: number; avgCheck: number; byFulfilment: Record<string, number>; deliveries: { total: number; delivered: number; failedShare: number; avgDeliveryMinutes: number | null; byCourier: { name: string; deliveries: number }[] } } }>(`/staff/admin/reports/summary?from=${from}&to=${to}`).then((r) => r.data),
  });
  async function download() {
    const res = await fetch(`${API_URL}/api/staff/admin/reports/orders.csv?from=${from}&to=${to}`, { headers: { Authorization: `Bearer ${tokens.get()}` } });
    const url = URL.createObjectURL(await res.blob());
    const a = document.createElement('a');
    a.href = url;
    a.download = `orders-${from}-${to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }
  const r = report.data;
  return (
    <Section title="Отчёты" actions={<button type="button" className="btn btn-sm" onClick={download}>Экспорт CSV</button>}>
      <div className="row"><input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /><input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></div>
      {r && (
        <div className="kpis">
          <div><span>Заказы</span><strong>{r.orders}</strong></div>
          <div><span>Выручка</span><strong>{rub(r.revenue)}</strong></div>
          <div><span>Средний чек</span><strong>{rub(r.avgCheck)}</strong></div>
          <div><span>Доставлено</span><strong>{r.deliveries.delivered} / {r.deliveries.total}</strong></div>
          <div><span>Невручённые</span><strong>{Math.round(r.deliveries.failedShare * 100)}%</strong></div>
          <div><span>Время доставки</span><strong>{r.deliveries.avgDeliveryMinutes ?? '—'} мин</strong></div>
        </div>
      )}
      {r && r.deliveries.byCourier.length > 0 && (
        <table className="table"><thead><tr><th>Курьер</th><th>Доставок</th></tr></thead>
          <tbody>{r.deliveries.byCourier.map((c) => <tr key={c.name}><td>{c.name}</td><td>{c.deliveries}</td></tr>)}</tbody></table>
      )}
    </Section>
  );
}

export default function Admin() {
  const links: [string, string][] = [['', 'Каталог'], ['categories', 'Категории'], ['promo', 'Промокоды'], ['users', 'Сотрудники'], ['zones', 'Зоны доставки'],
    ['deliveries', 'Доставки'], ['slots', 'Слоты'], ['reviews', 'Отзывы'], ['plans', 'Абонементы'], ['seo', 'SEO'], ['reports', 'Отчёты']];
  return (
    <div className="layout">
      <nav className="sidenav">{links.map(([to, label]) => <NavLink key={to} to={`/admin/${to}`} end>{label}</NavLink>)}</nav>
      <main className="content">
        <Routes>
          <Route index element={<Products />} />
          <Route path="categories" element={<Categories />} />
          <Route path="promo" element={<GenericJsonList<Promo> title="Промокоды" path="/staff/admin/promo-codes" savePath="/staff/admin/promo-codes"
            empty={{ code: '', type: 'percent', value: 10, max_uses: null, is_active: true }} render={(p) => `${p.code} — ${p.type === 'percent' ? `${p.value}%` : rub(p.value)} (использован ${p.used_count ?? 0})`} />} />
          <Route path="users" element={<Users />} />
          <Route path="zones" element={<GenericJsonList<Zone> title="Зоны и тарифы доставки" path="/staff/admin/zones" savePath="/staff/admin/zones"
            empty={{ name: '', price: 30000, min_order: 100000, free_from: 300000, eta_minutes: 90, polygon: [[55.86, 37.47], [55.86, 37.56], [55.9, 37.56], [55.9, 37.47]] }}
            render={(z) => `${z.name}: ${rub(z.price)}, от ${rub(z.min_order)}, бесплатно от ${rub(z.free_from)}`} />} />
          <Route path="deliveries" element={<DeliveryBoard />} />
          <Route path="slots" element={<Slots />} />
          <Route path="reviews" element={<Reviews />} />
          <Route path="plans" element={<GenericJsonList<Plan> title="Тарифы абонементов" path="/staff/admin/plans" savePath="/staff/admin/plans"
            empty={{ name: '', price: 300000, period_days: 30, daily_limit: 1 }} render={(p) => `${p.name}: ${rub(p.price)} / ${p.period_days} дн., ${p.daily_limit} в день`} />} />
          <Route path="seo" element={<SeoPages />} />
          <Route path="reports" element={<Reports />} />
        </Routes>
      </main>
    </div>
  );
}
