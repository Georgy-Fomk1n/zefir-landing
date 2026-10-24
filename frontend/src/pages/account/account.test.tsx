import type { Order, User } from '@zefir/shared';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { jsonResponse, mockApi, renderApp } from '../../test/render';
import Account from './Account';

const user: User = { id: 1, name: 'Мария', email: 'm@example.test', phone: '+79991234567', roles: ['customer'], marketingConsent: false, twoFactorEnabled: false };

const order: Order = {
  id: 5, number: 'ZP-5', status: 'completed', fulfilment: 'courier', subtotal: 90000, discount: 0, pointsSpent: 0, deliveryCost: 30000, total: 120000,
  paymentStatus: 'paid', slot: { date: '2026-10-27', from: '12:00', to: '13:00' },
  delivery: { status: 'delivered', address: 'Базовская, 15', history: [{ from: 'in_transit', to: 'delivered', at: '2026-10-27T10:30:00Z', comment: null }] },
  history: [{ from: null, to: 'pending_payment', at: '2026-10-27T08:00:00Z', comment: null }, { from: 'ready', to: 'completed', at: '2026-10-27T10:31:00Z', comment: 'Доставлен' }],
  items: [{ variantId: 1, productId: 1, productName: 'Кофе', variantName: '1 шт.', price: 45000, qty: 2, reviewable: true }],
  trackingUrl: 'https://зефир-и-пряник.рф/track/?t=x', createdAt: '2026-10-27T08:00:00Z',
};

function loggedIn(extra: Record<string, unknown> = {}) {
  window.localStorage.setItem('zp_token', 'tok');
  return mockApi({ 'GET /auth/me': { data: user }, 'GET /account/orders': { data: [order] }, ...extra });
}

describe('кабинет: вход и регистрация', () => {
  it('регистрирует с согласием, сохраняет токен и сливает корзину', async () => {
    window.localStorage.setItem('zp_cart', JSON.stringify([{ variantId: 3, qty: 1 }]));
    const { calls } = mockApi({
      'POST /auth/register': { token: 'new-token', user },
      'POST /cart/merge': { data: [{ variantId: 3, qty: 2 }] },
      'GET /account/orders': { data: [] },
    });
    renderApp(<Account />, { path: '/account/*', route: '/account/' });
    fireEvent.click(await screen.findByRole('tab', { name: 'Регистрация' }));
    fireEvent.change(screen.getByLabelText('Имя'), { target: { value: 'Мария' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'm@example.test' } });
    fireEvent.change(screen.getByLabelText('Пароль'), { target: { value: 'password-123' } });
    fireEvent.click(screen.getByRole('checkbox', { name: /политикой/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Зарегистрироваться' }));
    expect(await screen.findByRole('navigation', { name: 'Разделы кабинета' })).toBeInTheDocument();
    expect(window.localStorage.getItem('zp_token')).toBe('new-token');
    expect(JSON.parse(window.localStorage.getItem('zp_cart')!)).toEqual([{ variantId: 3, qty: 2 }]);
    expect(calls.find((c) => c.path === '/auth/register')!.body).toMatchObject({ consent: true, marketingConsent: false });
  });

  it('показывает ошибку входа', async () => {
    mockApi({ 'POST /auth/login': () => jsonResponse(422, { message: 'x', errors: { email: ['Неверный email или пароль'] } }) });
    renderApp(<Account />, { path: '/account/*', route: '/account/' });
    fireEvent.change(await screen.findByLabelText('Email'), { target: { value: 'm@example.test' } });
    fireEvent.change(screen.getByLabelText('Пароль'), { target: { value: 'wrong-pass' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByText('Неверный email или пароль')).toBeInTheDocument();
  });

  it('сетевая ошибка при входе', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    renderApp(<Account />, { path: '/account/*', route: '/account/' });
    fireEvent.change(await screen.findByLabelText('Email'), { target: { value: 'm@example.test' } });
    fireEvent.change(screen.getByLabelText('Пароль'), { target: { value: 'password-1' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByText(/Проверьте соединение/)).toBeInTheDocument();
  });
});

describe('кабинет: разделы', () => {
  it('заказы и детали заказа с историей и отзывом', async () => {
    const { calls } = loggedIn({
      'GET /account/orders/5': { data: order },
      'POST /account/orders/5/reviews': { data: { id: 1, status: 'pending' } },
    });
    const { router } = renderApp(<Account />, { path: '/account/*', route: '/account/' });
    fireEvent.click(await screen.findByRole('link', { name: '№ ZP-5' }));
    await waitFor(() => expect(router.state.location.pathname).toBe('/account/orders/5'));
    expect(await screen.findByRole('heading', { name: 'Заказ № ZP-5' })).toBeInTheDocument();
    expect(screen.getByText(/Доставка: Доставлена · Базовская, 15/)).toBeInTheDocument();
    // История заказа и доставки в одной ленте, по времени
    const timeline = document.querySelector('.timeline')!;
    expect([...timeline.querySelectorAll('li')].map((li) => li.textContent?.split(' — ')[1])).toEqual(['Ожидает оплаты', 'Доставлена', 'Выдан (Доставлен)']);
    fireEvent.change(screen.getByLabelText('Текст отзыва'), { target: { value: 'Очень вкусно, спасибо!' } });
    fireEvent.change(screen.getByLabelText('Оценка'), { target: { value: '4' } });
    fireEvent.click(screen.getByRole('button', { name: 'Отправить' }));
    expect(await screen.findByText(/появится после модерации/)).toBeInTheDocument();
    expect(calls.find((c) => c.path === '/account/orders/5/reviews')!.body).toEqual({ productId: 1, rating: 4, text: 'Очень вкусно, спасибо!' });
  });

  it('неоплаченный заказ можно оплатить повторно', async () => {
    const assign = vi.fn();
    Object.defineProperty(window, 'location', { configurable: true, value: { ...window.location, assign } });
    loggedIn({
      'GET /account/orders/6': { data: { ...order, id: 6, status: 'pending_payment', paymentStatus: 'failed', trackingToken: 'a'.repeat(40) } },
      'POST /track/*': { payment: { url: 'https://pay.test/again' } },
    });
    renderApp(<Account />, { path: '/account/*', route: '/account/orders/6' });
    fireEvent.click(await screen.findByRole('button', { name: 'Оплатить заказ' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('https://pay.test/again'));
  });

  it('карта лояльности: баланс, QR и операции', async () => {
    loggedIn({ 'GET /account/loyalty': { data: { balance: 120, maxSpendShare: 0.3, qrToken: 'qr-token', qrExpiresAt: '', transactions: [{ id: 1, type: 'earn', amount: 45, comment: 'Заказ № ZP-5', createdAt: '2026-10-27T10:00:00Z' }] } } });
    renderApp(<Account />, { path: '/account/*', route: '/account/loyalty' });
    expect(await screen.findByText('120 баллов')).toBeInTheDocument();
    expect(screen.getByText(/до 30% заказа/)).toBeInTheDocument();
    expect(screen.getByText(/\+45 · Заказ № ZP-5/)).toBeInTheDocument();
    await waitFor(() => expect(screen.getByRole('img', { name: /QR-код карты лояльности/ }).innerHTML).toContain('<svg'));
  });

  it('абонементы: покупка ведёт на оплату, отмена автопродления', async () => {
    const assign = vi.fn();
    Object.defineProperty(window, 'location', { configurable: true, value: { ...window.location, assign } });
    const plan = { id: 1, name: 'Пирожное в день', description: 'Одно пирожное ежедневно', price: 300000, periodDays: 30, dailyLimit: 1, totalLimit: null };
    const { calls } = loggedIn({
      'GET /subscription-plans': { data: [plan] },
      'GET /account/subscriptions': { data: [{ id: 9, plan, status: 'active', currentPeriodEnd: '2026-11-27T00:00:00Z', autoRenew: true, usedToday: 0, qrToken: 'q', renewalMode: 'recurring' }] },
      'POST /account/subscriptions': { payment: { url: 'https://pay.test/sub' } },
      'POST /account/subscriptions/9/cancel': () => new Response(null, { status: 204 }),
    });
    renderApp(<Account />, { path: '/account/*', route: '/account/subscriptions' });
    expect(await screen.findByText(/Активен · до/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Отключить автопродление' }));
    await waitFor(() => expect(calls.some((c) => c.path === '/account/subscriptions/9/cancel')).toBe(true));
    fireEvent.click(screen.getByRole('button', { name: 'Оформить' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('https://pay.test/sub'));
  });

  it('настройки: профиль, пароль, карты, уведомления, удаление аккаунта и выход', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const { calls } = loggedIn({
      'GET /account/payment-methods': { data: [{ id: 3, title: 'Карта •• 4242' }] },
      'DELETE /account/payment-methods/3': () => new Response(null, { status: 204 }),
      'PUT /account/profile': { data: user },
      'PUT /account/password': () => new Response(null, { status: 204 }),
      'POST /account/delete': () => new Response(null, { status: 204 }),
      'POST /auth/logout': () => new Response(null, { status: 204 }),
    });
    renderApp(<Account />, { path: '/account/*', route: '/account/settings' });
    const profile = (await screen.findByRole('heading', { name: 'Профиль' })).closest('form')!;
    fireEvent.change(within(profile).getByLabelText('Имя'), { target: { value: 'Мария Иванова' } });
    fireEvent.click(within(profile).getByRole('button', { name: 'Сохранить' }));
    expect(await screen.findByText('Сохранено')).toBeInTheDocument();
    expect(calls.find((c) => c.path === '/account/profile')!.body).toMatchObject({ name: 'Мария Иванова', marketingConsent: false });

    const pwd = screen.getByRole('heading', { name: 'Смена пароля' }).closest('form')!;
    fireEvent.change(within(pwd).getByLabelText('Текущий пароль'), { target: { value: 'old-password' } });
    fireEvent.change(within(pwd).getByLabelText('Новый пароль'), { target: { value: 'new-password' } });
    fireEvent.change(within(pwd).getByLabelText('Повторите'), { target: { value: 'new-password' } });
    fireEvent.click(within(pwd).getByRole('button', { name: 'Изменить' }));
    expect(await screen.findByText('Пароль изменён')).toBeInTheDocument();

    fireEvent.click(await screen.findByRole('button', { name: 'Отвязать' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'DELETE' && c.path === '/account/payment-methods/3')).toBe(true));

    // В jsdom нет PushManager — показывается пояснение вместо кнопки
    expect(screen.getByTestId('push-toggle')).toHaveTextContent('не поддерживает push');

    const del = screen.getByRole('heading', { name: 'Удаление аккаунта' }).closest('form')!;
    fireEvent.change(within(del).getByLabelText('Пароль'), { target: { value: 'password' } });
    fireEvent.click(within(del).getByRole('button', { name: 'Удалить аккаунт' }));
    await waitFor(() => expect(calls.some((c) => c.path === '/auth/logout')).toBe(true));
    expect(window.localStorage.getItem('zp_token')).toBeNull();
  });

  it('избранное и выход', async () => {
    const { calls } = loggedIn({ 'GET /account/favorites': { data: [{ id: 1, slug: 'kofe' }] }, 'POST /auth/logout': () => new Response(null, { status: 204 }) });
    renderApp(<Account />, { path: '/account/*', route: '/account/favorites' });
    expect(await screen.findByRole('heading', { name: 'Кофе' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Выйти' }));
    await waitFor(() => expect(calls.some((c) => c.path === '/auth/logout')).toBe(true));
  });

  it('пустые списки', async () => {
    loggedIn({ 'GET /account/orders': { data: [] }, 'GET /account/favorites': { data: [] }, 'GET /subscription-plans': { data: [] }, 'GET /account/subscriptions': { data: [] } });
    renderApp(<Account />, { path: '/account/*', route: '/account/' });
    expect(await screen.findByText(/Заказов пока нет/)).toBeInTheDocument();
  });
});
