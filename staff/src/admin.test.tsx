import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import App, { queryClient } from './App';
import { mockApi, noContent, signedIn, users } from './test/mock';

beforeEach(() => window.sessionStorage.clear());
afterEach(() => {
  queryClient.clear();
  window.history.pushState({}, '', '/');
});

const product = {
  id: 1, slug: 'kofe', categorySlug: 'vkusy', categoryId: 1, name: 'Кофе', description: 'Маскарпоне', isPp: false, isActive: true,
  media: [], related: [], rating: null, updatedAt: '', seo: { title: null, description: null, h1: null, text: null, noindex: false },
  variants: [{ id: 7, sku: 'ZP-KOFE-1', name: '1 шт.', price: 45000, priceFrom: false, inStock: true, stock: 20, packSize: 1 }],
};

function adminApi(extra: Record<string, unknown> = {}) {
  return mockApi({
    'GET /staff/auth/me': { data: users.admin },
    'GET /staff/admin/products': { data: [product] },
    'GET /staff/admin/categories': { data: [{ id: 1, slug: 'vkusy', name: 'Вкусы', intro: '', sort: 10, is_active: true, seo_title: null, seo_description: null, seo_h1: null, seo_text: null, noindex: true }] },
    ...extra,
  });
}

/** Заменяет содержимое JSON-редактора и сохраняет */
function saveJson(value: unknown, container: HTMLElement = document.body) {
  fireEvent.change(container.querySelector('textarea')!, { target: { value: typeof value === 'string' ? value : JSON.stringify(value) } });
  fireEvent.click(within(container).getByRole('button', { name: 'Сохранить' }));
}

describe('админка', () => {
  it('каталог: правка цены и остатка в таблице, редактирование и добавление товара', async () => {
    signedIn('admin', '/admin');
    const { calls } = adminApi({ 'PATCH /staff/admin/variants/7': { data: {} }, 'PUT /staff/admin/products/1': { data: product }, 'POST /staff/admin/products': { data: product } });
    render(<App />);
    const row = (await screen.findByText('Кофе')).closest('tr')!;
    const [price, stock] = within(row).getAllByRole('spinbutton');
    fireEvent.blur(price, { target: { value: '470' } });
    fireEvent.blur(stock, { target: { value: '15' } });
    fireEvent.blur(stock, { target: { value: '20' } }); // без изменений — без запроса
    await waitFor(() => expect(calls.filter((c) => c.method === 'PATCH').map((c) => c.body)).toEqual([{ price: 47000 }, { stock: 15 }]));

    fireEvent.click(within(row).getByRole('button', { name: 'Изменить' }));
    const text = (document.querySelector('textarea') as HTMLTextAreaElement).value;
    expect(JSON.parse(text)).toMatchObject({ slug: 'kofe', variants: [{ id: 7, price: 45000 }] });
    saveJson('{ не json');
    expect(screen.getByText('Некорректный JSON')).toBeInTheDocument();
    saveJson({ ...JSON.parse(text), name: 'Кофе+' });
    await waitFor(() => expect(calls.find((c) => c.method === 'PUT')!.body).toMatchObject({ name: 'Кофе+' }));

    fireEvent.click(screen.getByRole('button', { name: 'Добавить товар' }));
    saveJson({ category_id: 1, slug: 'mango', name: 'Манго', description: 'x', variants: [] });
    await waitFor(() => expect(calls.some((c) => c.method === 'POST' && c.path === '/staff/admin/products')).toBe(true));
  });

  it('категории: noindex отмечен, правка SEO', async () => {
    signedIn('admin', '/admin/categories');
    const { calls } = adminApi({ 'PUT /staff/admin/categories/1': { data: {} } });
    render(<App />);
    expect(await screen.findByText('noindex')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Вкусы' }));
    saveJson({ id: 1, slug: 'vkusy', name: 'Вкусы', seo_title: 'Вкусы пирожных' });
    await waitFor(() => expect(calls.find((c) => c.method === 'PUT')!.body).toMatchObject({ seo_title: 'Вкусы пирожных' }));
  });

  it('промокоды, зоны и тарифы — через общий редактор списка', async () => {
    signedIn('admin', '/admin/promo');
    const { calls } = adminApi({
      'GET /staff/admin/promo-codes': { data: [{ id: 3, code: 'ОСЕНЬ', type: 'percent', value: 10, used_count: 2, max_uses: null, is_active: true }] },
      'PUT /staff/admin/promo-codes/3': { data: {} },
      'POST /staff/admin/promo-codes': { data: {} },
      'GET /staff/admin/zones': { data: [] },
      'POST /staff/admin/zones': { data: {} },
      'GET /staff/admin/plans': { data: [{ id: 1, name: 'Кофе', price: 300000, period_days: 30, daily_limit: 1 }] },
    });
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: /ОСЕНЬ — 10% \(использован 2\)/ }));
    saveJson({ id: 3, code: 'ОСЕНЬ', type: 'percent', value: 15 });
    await waitFor(() => expect(document.querySelector('textarea')).toBeNull());
    fireEvent.click(screen.getByRole('button', { name: 'Добавить' }));
    saveJson({ code: 'ЗИМА', type: 'fixed', value: 20000 });
    await waitFor(() => expect(calls.filter((c) => c.path.startsWith('/staff/admin/promo-codes') && c.method !== 'GET').map((c) => c.method)).toEqual(['PUT', 'POST']));

    fireEvent.click(screen.getByRole('link', { name: 'Зоны доставки' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Добавить' }));
    const draft = JSON.parse((document.querySelector('textarea') as HTMLTextAreaElement).value);
    expect(draft.polygon).toHaveLength(4);
    saveJson({ ...draft, name: 'Север' });
    await waitFor(() => expect(calls.find((c) => c.path === '/staff/admin/zones' && c.method === 'POST')!.body).toMatchObject({ name: 'Север' }));

    fireEvent.click(screen.getByRole('link', { name: 'Абонементы' }));
    expect(await screen.findByText(/Кофе: 3\s000\s₽ \/ 30 дн., 1 в день/)).toBeInTheDocument();
  });

  it('сотрудники: добавление, смена ролей, сброс 2FA', async () => {
    signedIn('admin', '/admin/users');
    const { calls } = adminApi({
      'GET /staff/admin/users': { data: [{ ...users.admin, isActive: true }, { ...users.courier, isActive: false }] },
      'POST /staff/admin/users': { data: {} },
      'PUT /staff/admin/users/3': { data: {} },
      'POST /staff/admin/users/1/reset-2fa': noContent,
    });
    render(<App />);
    const courierRow = (await screen.findByText('c@example.test')).closest('tr')!;
    expect(courierRow).toHaveClass('muted');
    fireEvent.click(within(courierRow).getByRole('button', { name: 'Изменить' }));
    saveJson({ id: 3, name: 'Курьер', email: 'c@example.test', roles: ['courier', 'seller'], is_active: true });
    await waitFor(() => expect(document.querySelector('textarea')).toBeNull());
    fireEvent.click(screen.getByRole('button', { name: 'Сбросить 2FA' }));
    fireEvent.click(screen.getByRole('button', { name: 'Добавить сотрудника' }));
    saveJson({ name: 'Новый', email: 'n@example.test', roles: ['seller'], password: 'long-password-1' });
    await waitFor(() => expect(calls.filter((c) => c.method !== 'GET').map((c) => `${c.method} ${c.path}`)).toEqual([
      'PUT /staff/admin/users/3', 'POST /staff/admin/users/1/reset-2fa', 'POST /staff/admin/users',
    ]));
  });

  it('модерация отзывов', async () => {
    signedIn('admin', '/admin/reviews');
    const { calls } = adminApi({
      'GET /staff/admin/reviews': { data: [{ id: 4, author_name: 'Аня', rating: 5, text: 'Очень вкусно', product: { name: 'Кофе' } }, { id: 5, author_name: 'Б', rating: 1, text: 'Спам', product: { name: 'Кофе' } }] },
      'POST /staff/admin/reviews/*': { data: {} },
    });
    render(<App />);
    fireEvent.click((await screen.findAllByRole('button', { name: 'Опубликовать' }))[0]);
    fireEvent.click(screen.getAllByRole('button', { name: 'Отклонить' })[1]);
    await waitFor(() => expect(calls.filter((c) => c.method === 'POST').map((c) => [c.path, c.body])).toEqual([
      ['/staff/admin/reviews/4', { status: 'approved' }], ['/staff/admin/reviews/5', { status: 'rejected' }],
    ]));
  });

  it('слоты: лимит, отключение, генерация', async () => {
    signedIn('admin', '/admin/slots');
    const { calls } = adminApi({
      'GET /staff/admin/slots': { data: [{ id: 2, type: 'courier', starts_at: '12:00:00', ends_at: '13:00:00', capacity: 4, booked: 1, is_active: true }] },
      'PATCH /staff/admin/slots/2': { data: {} },
      'POST /staff/admin/slots/generate': { created: 0 },
    });
    render(<App />);
    const row = (await screen.findByText('12:00–13:00')).closest('tr')!;
    fireEvent.blur(within(row).getByRole('spinbutton'), { target: { value: '6' } });
    fireEvent.click(within(row).getByRole('checkbox'));
    fireEvent.click(screen.getByRole('button', { name: 'Создать на неделю' }));
    fireEvent.change(document.querySelector('input[type="date"]')!, { target: { value: '2026-10-28' } });
    await waitFor(() => expect(calls.filter((c) => c.method !== 'GET').map((c) => c.body)).toEqual([{ capacity: 6 }, { is_active: false }, undefined]));
    await waitFor(() => expect(calls.some((c) => c.path === '/staff/admin/slots?date=2026-10-28')).toBe(true));
  });

  it('SEO страниц', async () => {
    signedIn('admin', '/admin/seo');
    const { calls } = adminApi({ 'GET /staff/admin/seo-pages': { data: [{ id: 1, path: '/kontakty/', seo_title: 'Контакты' }] }, 'POST /staff/admin/seo-pages': { data: {} } });
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: '/kontakty/' }));
    saveJson({ path: '/kontakty/', seo_title: 'Контакты кондитерской' });
    await waitFor(() => expect(calls.find((c) => c.method === 'POST')!.body).toMatchObject({ seo_title: 'Контакты кондитерской' }));
  });

  it('отчёты: показатели и выгрузка CSV с токеном', async () => {
    signedIn('admin', '/admin/reports');
    const createObjectURL = vi.fn(() => 'blob:x');
    Object.assign(URL, { createObjectURL, revokeObjectURL: vi.fn() });
    const { calls } = adminApi({
      'GET /staff/admin/reports/summary': { data: { orders: 12, revenue: 1234500, avgCheck: 102875, byFulfilment: {}, deliveries: { total: 5, delivered: 4, failedShare: 0.2, avgDeliveryMinutes: 38, byCourier: [{ name: 'Курьер', deliveries: 5 }] } } },
      'GET /staff/admin/reports/orders.csv': () => new Response('Номер;Дата\n', { status: 200, headers: { 'Content-Type': 'text/csv' } }),
    });
    render(<App />);
    expect(await screen.findByText('12')).toBeInTheDocument();
    expect(screen.getByText('20%')).toBeInTheDocument();
    expect(screen.getByText('38 мин')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Экспорт CSV' }));
    await waitFor(() => expect(createObjectURL).toHaveBeenCalled());
    expect(calls.some((c) => c.path.startsWith('/staff/admin/reports/orders.csv?from='))).toBe(true);
  });

  it('доска доставок доступна администратору', async () => {
    signedIn('admin', '/admin/deliveries');
    adminApi({ 'GET /staff/seller/deliveries': { data: { orders: [], couriers: [] } } });
    render(<App />);
    expect(await screen.findByRole('heading', { name: 'Доставки по зонам и интервалам' })).toBeInTheDocument();
    expect(screen.getByText(/Курьеры: нет/)).toBeInTheDocument();
  });
});
