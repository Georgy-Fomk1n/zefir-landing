import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import App, { queryClient } from './App';
import { tokens } from './lib/api';
import { readQueue } from './lib/offlineQueue';
import { json, mockApi, noContent, signedIn, users } from './test/mock';

beforeEach(() => {
  window.sessionStorage.clear();
  Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
});
afterEach(() => {
  queryClient.clear();
  window.history.pushState({}, '', '/');
});

const order = (over: Record<string, unknown> = {}) => ({
  id: 10, number: 'ZP-10', status: 'paid', fulfilment: 'pickup', total: 90000, paymentStatus: 'paid',
  slot: { date: '2026-10-27', from: '12:00', to: '13:00' }, delivery: null, history: [],
  items: [{ variantId: 1, productId: 1, productName: 'Кофе', variantName: '1 шт.', price: 45000, qty: 2, reviewable: false }],
  contact: { name: 'Ольга', phone: '+79991112233', email: null }, address: null, comment: 'Без надписи', courier: null, zone: null,
  ...over,
});

describe('вход сотрудников', () => {
  it('без токена отправляет на страницу входа', async () => {
    window.history.pushState({}, '', '/seller');
    mockApi({});
    render(<App />);
    expect(await screen.findByRole('heading', { name: 'Вход для сотрудников' })).toBeInTheDocument();
    expect(window.location.search).toBe('?next=%2Fseller');
  });

  it('продавец входит и попадает в очередь; токен — только в sessionStorage', async () => {
    window.history.pushState({}, '', '/login');
    mockApi({ 'POST /staff/auth/login': { token: 'seller-token', user: users.seller }, 'GET /staff/seller/queue': { data: [] }, 'GET /push/key': { publicKey: null } });
    render(<App />);
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 's@example.test' } });
    fireEvent.change(screen.getByLabelText('Пароль'), { target: { value: 'password-123' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByRole('heading', { name: 'Очередь заказов ко времени' })).toBeInTheDocument();
    expect(window.location.pathname).toBe('/seller');
    expect(window.sessionStorage.getItem('zp_staff_token')).toBe('seller-token');
    expect(window.localStorage.getItem('zp_staff_token')).toBeNull();
  });

  it('курьер (только роль courier) держит токен в localStorage для PWA', async () => {
    window.history.pushState({}, '', '/login');
    mockApi({ 'POST /staff/auth/login': { token: 'courier-token', user: users.courier }, 'GET /staff/courier/today': { data: { deliveries: [], shift: null, cashOnDelivery: false } } });
    render(<App />);
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'c@example.test' } });
    fireEvent.change(screen.getByLabelText('Пароль'), { target: { value: 'password-123' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByRole('button', { name: 'Начать смену' })).toBeInTheDocument();
    expect(window.localStorage.getItem('zp_staff_token')).toBe('courier-token');
    expect(tokens.get()).toBe('courier-token');
  });

  it('администратор: настройка 2FA по QR, затем ввод кода', async () => {
    window.history.pushState({}, '', '/login');
    const { calls } = mockApi({
      'POST /staff/auth/login': (_u: string, init: RequestInit) => {
        const body = JSON.parse(String(init.body));
        if (!body.code) return json(202, { twoFactor: 'setup', secret: 'ABCDEF', otpauth: 'otpauth://totp/x?secret=ABCDEF' });
        return body.code === '123456' ? { token: 'admin-token', user: users.admin } : json(422, { message: 'x', errors: { code: ['Неверный код'] } });
      },
      'GET /staff/admin/products': { data: [] },
      'GET /staff/admin/categories': { data: [] },
    });
    render(<App />);
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@example.test' } });
    fireEvent.change(screen.getByLabelText('Пароль'), { target: { value: 'password-123' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByText('ABCDEF')).toBeInTheDocument();
    await waitFor(() => expect(document.querySelector('.qr svg')).not.toBeNull());
    fireEvent.change(screen.getByLabelText('Код из приложения'), { target: { value: '000000' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByText('Неверный код')).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Код из приложения'), { target: { value: '123456' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByRole('heading', { name: 'Каталог' })).toBeInTheDocument();
    expect(calls.filter((c) => c.path === '/staff/auth/login').at(-1)!.body).toEqual({ email: 'a@example.test', password: 'password-123', code: '123456' });
  });

  it('2FA уже настроена — сразу запрос кода; сетевая ошибка показывается', async () => {
    window.history.pushState({}, '', '/login');
    mockApi({ 'POST /staff/auth/login': json(202, { twoFactor: 'required' }) });
    render(<App />);
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@example.test' } });
    fireEvent.change(screen.getByLabelText('Пароль'), { target: { value: 'password-123' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByLabelText('Код из приложения')).toBeInTheDocument();
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('offline')));
    fireEvent.change(screen.getByLabelText('Код из приложения'), { target: { value: '123456' } });
    fireEvent.click(screen.getByRole('button', { name: 'Войти' }));
    expect(await screen.findByText('Нет связи с сервером')).toBeInTheDocument();
  });

  it('роль без доступа перенаправляется в свою панель, выход очищает токен', async () => {
    signedIn('courier', '/admin');
    const { calls } = mockApi({
      'GET /staff/auth/me': { data: users.courier },
      'GET /staff/courier/today': { data: { deliveries: [], shift: null, cashOnDelivery: false } },
      'POST /staff/auth/logout': noContent,
    });
    render(<App />);
    expect(await screen.findByRole('button', { name: 'Начать смену' })).toBeInTheDocument();
    expect(window.location.pathname).toBe('/courier');
    fireEvent.click(screen.getByRole('button', { name: 'Выйти' }));
    await waitFor(() => expect(window.location.pathname).toBe('/login'));
    expect(tokens.get()).toBeNull();
    expect(calls.some((c) => c.path === '/staff/auth/logout')).toBe(true);
  });

  it('корневой адрес ведёт в панель по роли', async () => {
    signedIn('seller', '/');
    mockApi({ 'GET /staff/auth/me': { data: users.seller }, 'GET /staff/seller/queue': { data: [] } });
    render(<App />);
    await waitFor(() => expect(window.location.pathname).toBe('/seller'));
  });
});

describe('продавец', () => {
  it('очередь по слотам: следующий статус, отмена с причиной', async () => {
    signedIn('seller', '/seller');
    vi.spyOn(window, 'prompt').mockReturnValue('Клиент передумал');
    let status = 'paid';
    const { calls } = mockApi({
      'GET /staff/auth/me': { data: users.seller },
      'GET /staff/seller/queue': () => ({ data: [order({ status }), order({ id: 11, number: 'ZP-11', status: 'ready', fulfilment: 'cdek', slot: null })] }),
      'POST /staff/seller/orders/10/status': () => {
        status = 'accepted';
        return { data: order({ status }) };
      },
      'POST /staff/seller/orders/11/cancel': { data: order({ id: 11, status: 'cancelled' }) },
    });
    render(<App />);
    const slot = (await screen.findByRole('heading', { name: /12:00–13:00/ })).closest('.slot') as HTMLElement;
    expect(within(slot).getByText('Без надписи', { exact: false })).toBeInTheDocument();
    fireEvent.click(within(slot).getByRole('button', { name: '→ Принят' }));
    expect(await within(slot).findByRole('button', { name: '→ Готовится' })).toBeInTheDocument();
    expect(calls.find((c) => c.path === '/staff/seller/orders/10/status')!.body).toEqual({ status: 'accepted' });

    const noSlot = screen.getByRole('heading', { name: /Без времени/ }).closest('.slot') as HTMLElement;
    expect(within(noSlot).getByRole('button', { name: '→ Отправлен' })).toBeInTheDocument();
    fireEvent.click(within(noSlot).getByRole('button', { name: 'Отменить' }));
    await waitFor(() => expect(calls.find((c) => c.path === '/staff/seller/orders/11/cancel')!.body).toEqual({ reason: 'Клиент передумал' }));
  });

  it('сканер QR: баланс, проведение покупки и погашение абонемента', async () => {
    signedIn('seller', '/seller/scan');
    const { calls } = mockApi({
      'GET /staff/auth/me': { data: users.seller },
      'POST /staff/seller/scan/loyalty': (_u: string, init: RequestInit) => {
        const body = JSON.parse(String(init.body));
        return body.purchase === undefined
          ? { data: { name: 'Мария', balance: 500 } }
          : { data: { name: 'Мария', earned: 35, spent: 300, balance: 235, duplicate: false } };
      },
      'POST /staff/seller/scan/subscription': { data: { plan: 'Пирожное в день', usedToday: 1, dailyLimit: 1, customer: 'Мария' } },
    });
    render(<App />);
    fireEvent.change(await screen.findByLabelText('Код из QR'), { target: { value: 'qr-token' } });
    fireEvent.click(screen.getByRole('button', { name: 'Проверить' }));
    expect(await screen.findByText(/баланс 500 баллов/)).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Сумма покупки, ₽'), { target: { value: '1000' } });
    fireEvent.change(screen.getByLabelText('Списать баллов'), { target: { value: '300' } });
    fireEvent.click(screen.getByRole('button', { name: 'Провести' }));
    expect(await screen.findByText('Начислено 35, списано 300. Баланс: 235')).toBeInTheDocument();
    const pos = calls.filter((c) => c.path === '/staff/seller/scan/loyalty').at(-1)!.body as Record<string, unknown>;
    expect(pos).toMatchObject({ token: 'qr-token', purchase: 100000, spend: 300 });
    expect(pos.operationId).toMatch(/^[0-9a-f-]{36}$/);

    fireEvent.click(screen.getByRole('button', { name: 'Абонемент' }));
    fireEvent.change(screen.getByLabelText('Код из QR'), { target: { value: 'sub-token' } });
    fireEvent.click(screen.getByRole('button', { name: 'Проверить' }));
    expect(await screen.findByText('Мария: «Пирожное в день» погашен, сегодня 1 из 1')).toBeInTheDocument();
  });

  it('недействительный QR показывает ошибку сервера', async () => {
    signedIn('seller', '/seller/scan');
    mockApi({ 'GET /staff/auth/me': { data: users.seller }, 'POST /staff/seller/scan/loyalty': json(422, { message: 'x', errors: { token: ['QR-код устарел, попросите обновить экран'] } }) });
    render(<App />);
    fireEvent.change(await screen.findByLabelText('Код из QR'), { target: { value: 'old' } });
    fireEvent.click(screen.getByRole('button', { name: 'Проверить' }));
    expect(await screen.findByText('QR-код устарел, попросите обновить экран')).toBeInTheDocument();
  });

  it('доска доставок: назначение, автоназначение, возврат в точку', async () => {
    signedIn('seller', '/seller/deliveries');
    const { calls } = mockApi({
      'GET /staff/auth/me': { data: users.seller },
      'GET /staff/seller/deliveries': {
        data: {
          orders: [
            order({ status: 'ready', fulfilment: 'courier', address: { text: 'Базовская 15' }, zone: { id: 1, name: 'Север' } }),
            order({ id: 12, number: 'ZP-12', status: 'handed_to_courier', fulfilment: 'courier', address: { text: 'Дегунинская 1' }, zone: { id: 1, name: 'Север' }, delivery: { status: 'failed', address: null, history: [] }, courier: { id: 3, name: 'Курьер' } }),
          ],
          couriers: [{ id: 3, name: 'Курьер', onShift: true, active: 1 }],
        },
      },
      'POST /staff/seller/orders/10/assign': { data: order() },
      'POST /staff/seller/orders/12/returned': { data: order() },
    });
    render(<App />);
    expect(await screen.findByText(/Курьер \(на смене\) — 1/)).toBeInTheDocument();
    const first = screen.getByText(/Базовская 15/).closest('article') as HTMLElement;
    fireEvent.change(within(first).getByRole('combobox'), { target: { value: '3' } });
    fireEvent.click(within(first).getByRole('button', { name: 'Автоназначение' }));
    fireEvent.click(screen.getByRole('button', { name: 'Заказ вернулся в точку' }));
    await waitFor(() => expect(calls.filter((c) => c.method === 'POST').map((c) => [c.path, c.body])).toEqual([
      ['/staff/seller/orders/10/assign', { courierId: 3 }],
      ['/staff/seller/orders/10/assign', { auto: true }],
      ['/staff/seller/orders/12/returned', undefined],
    ]));
  });
});

describe('курьер (PWA)', () => {
  const delivery = (over: Record<string, unknown> = {}) => ({
    id: 5, orderNumber: 'ZP-5', status: 'assigned', routePosition: 1, clientName: 'Ольга', phone: null, address: 'Москва, Базовская, 15',
    entrance: '2', floor: '5', intercom: '25К', lat: 55.88, lng: 37.51, comment: 'Позвонить за 10 минут', interval: { date: '2026-10-27', from: '15:00', to: '16:00' },
    total: 210000, codAmount: 0, paid: true, failReason: null, ...over,
  });

  function courierApi(state: { status: string }, extra: Record<string, unknown> = {}) {
    return mockApi({
      'GET /staff/auth/me': { data: users.courier },
      'GET /staff/courier/today': () => ({ data: { deliveries: [delivery({ status: state.status, phone: state.status === 'assigned' ? null : '+79991112233' })], shift: { id: 1, startedAt: '2026-10-27T07:00:00Z', cash: 0, card: 0 }, cashOnDelivery: false } }),
      'POST /staff/courier/deliveries/5/status': (_u: string, init: RequestInit) => {
        state.status = String((init.body as FormData).get('status'));
        return { data: delivery({ status: state.status }) };
      },
      'GET /push/key': { publicKey: null },
      ...extra,
    });
  }

  it('показывает остановку с подъездом, этажом, домофоном и ссылками на карты', async () => {
    signedIn('courier', '/courier');
    courierApi({ status: 'assigned' });
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: /Москва, Базовская, 15/ }));
    expect(screen.getByText('2 / 5 / 25К')).toBeInTheDocument();
    expect(screen.getByText('Позвонить за 10 минут')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Яндекс Карты' })).toHaveAttribute('href', 'https://yandex.ru/maps/?rtext=~55.88,37.51&rtt=auto');
    expect(screen.getByRole('link', { name: 'Навигатор' })).toHaveAttribute('href', 'yandexnavi://build_route_on_map?lat_to=55.88&lon_to=37.51');
    // Телефон до принятия заказа скрыт
    expect(screen.queryByRole('link', { name: 'Позвонить' })).toBeNull();
  });

  it('смена статуса в сети уходит сразу, с временем и clientActionId', async () => {
    signedIn('courier', '/courier');
    const state = { status: 'assigned' };
    const { calls } = courierApi(state);
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: /Москва, Базовская, 15/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Принять' }));
    await waitFor(() => expect(state.status).toBe('accepted'));
    const fd = calls.find((c) => c.path === '/staff/courier/deliveries/5/status')!.body as FormData;
    expect(fd.get('clientActionId')).toBeTruthy();
    expect(fd.get('happenedAt')).toMatch(/^\d{4}-\d\d-\d\dT/);
    expect(await screen.findByRole('link', { name: 'Позвонить' })).toHaveAttribute('href', 'tel:+79991112233');
    expect(readQueue()).toEqual([]);
  });

  it('без сети действие копится в очереди и отправляется, когда сеть вернулась', async () => {
    signedIn('courier', '/courier');
    const state = { status: 'accepted' };
    courierApi(state);
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: /Москва, Базовская, 15/ }));
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: false });
    act(() => {
      window.dispatchEvent(new Event('offline'));
    });
    expect(screen.getByText(/Нет сети/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Забрал заказ' }));
    await waitFor(() => expect(readQueue()).toHaveLength(1));
    expect(screen.getByText(/в очереди: 1/)).toBeInTheDocument();
    expect(state.status).toBe('accepted'); // ещё не отправлено

    Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
    await act(async () => {
      window.dispatchEvent(new Event('online'));
    });
    await waitFor(() => expect(state.status).toBe('picked_up'));
    expect(readQueue()).toEqual([]);
  });

  it('«не удалось вручить» требует причину', async () => {
    signedIn('courier', '/courier');
    const state = { status: 'in_transit' };
    const prompt = vi.spyOn(window, 'prompt').mockReturnValueOnce('').mockReturnValueOnce('Не открыли дверь');
    const { calls } = courierApi(state);
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: /Москва, Базовская, 15/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Не удалось вручить' }));
    expect(calls.filter((c) => c.method === 'POST')).toHaveLength(0);
    fireEvent.click(screen.getByRole('button', { name: 'Не удалось вручить' }));
    await waitFor(() => expect(state.status).toBe('failed'));
    expect(prompt).toHaveBeenCalledTimes(2);
    const fd = calls.find((c) => c.method === 'POST')!.body as FormData;
    expect(fd.get('reason')).toBe('Не открыли дверь');
  });

  it('ошибка сервера убирает действие из очереди и показывается курьеру', async () => {
    signedIn('courier', '/courier');
    courierApi({ status: 'assigned' }, { 'POST /staff/courier/deliveries/5/status': json(422, { message: 'Переход assigned → picked_up недопустим' }) });
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: /Москва, Базовская, 15/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Принять' }));
    expect(await screen.findByText('Переход assigned → picked_up недопустим')).toBeInTheDocument();
    expect(readQueue()).toEqual([]);
  });

  it('начало и конец смены с итогом дня', async () => {
    signedIn('courier', '/courier');
    let shift: unknown = null;
    mockApi({
      'GET /staff/auth/me': { data: users.courier },
      'GET /staff/courier/today': () => ({ data: { deliveries: [delivery({ status: 'delivered' })], shift, cashOnDelivery: true } }),
      'POST /staff/courier/shift/start': () => {
        shift = { id: 1, startedAt: '2026-10-27T07:00:00Z', cash: 0, card: 0 };
        return { data: shift };
      },
      'POST /staff/courier/shift/end': () => {
        shift = null;
        return { data: { delivered: 4, failed: 1, cash: 210000, card: 0 } };
      },
    });
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: 'Начать смену' }));
    expect(await screen.findByText(/Смена с/)).toBeInTheDocument();
    expect(screen.getByText(/Завершённые · 1/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Закончить смену' }));
    expect(await screen.findByText(/Итог смены: доставлено 4, не вручено 1, наличные 2\s100\s₽/)).toBeInTheDocument();
  });

  it('неоплаченный заказ: выбор способа оплаты при получении', async () => {
    signedIn('courier', '/courier');
    const { calls } = mockApi({
      'GET /staff/auth/me': { data: users.courier },
      'GET /staff/courier/today': { data: { deliveries: [delivery({ status: 'in_transit', paid: false, codAmount: 210000, phone: '+79991112233' })], shift: { id: 1, startedAt: '2026-10-27T07:00:00Z', cash: 0, card: 0 }, cashOnDelivery: true } },
      'POST /staff/courier/deliveries/5/status': { data: delivery({ status: 'delivered' }) },
    });
    render(<App />);
    fireEvent.click(await screen.findByRole('button', { name: /Москва, Базовская, 15/ }));
    fireEvent.click(screen.getByRole('radio', { name: 'карта' }));
    fireEvent.click(screen.getByRole('button', { name: 'Доставлено' }));
    await waitFor(() => expect(calls.some((c) => c.method === 'POST')).toBe(true));
    const fd = calls.find((c) => c.method === 'POST')!.body as FormData;
    expect([fd.get('codMethod'), fd.get('codAmount')]).toEqual(['card', '210000']);
  });
});
