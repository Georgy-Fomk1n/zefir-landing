import type { ReactElement } from 'react';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { catalog, productBySlug } from '../data/catalog';
import { jsonResponse, mockApi, renderApp } from '../test/render';
import Cart from './Cart';
import { CatalogIndex, CategoryPage, ProductPage } from './catalog';
import Checkout, { checkoutSchema } from './Checkout';
import { ContactsPage, CorporatePage, DeliveryPage, FaqPage, FounderPage, IndividualOrderPage, LegalPage, ProductionPage, ReviewsPage } from './content';
import Home from './Home';
import NotFound from './NotFound';
import Search from './Search';
import Track from './Track';

const kofe = productBySlug('kofe')!;
const variantId = kofe.variants[0].id;

describe('главная и каталог', () => {
  it('главная: один h1, вкусы, ПП, наборы, канонические ссылки', () => {
    renderApp(<Home />);
    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('Корпусные пирожные, которые хочется разломать.');
    for (const name of ['Кофе', 'Маракуйя', 'Фисташка', 'Черника', 'ПП-банан', 'ПП-малина', 'Набор 4 шт.', 'Набор 10 шт.']) {
      expect(screen.getByRole('heading', { name })).toBeInTheDocument();
    }
    expect(screen.getByRole('link', { name: 'Все вкусы' })).toHaveAttribute('href', '/catalog/vkusy/');
    expect(screen.getByRole('img', { name: 'Корпусные пирожные ручной работы в шоколадном корпусе' })).toHaveAttribute('fetchpriority', 'high');
  });

  it('каталог: плитки категорий', () => {
    renderApp(<CatalogIndex />);
    expect(screen.getByRole('heading', { level: 1, name: 'Каталог пирожных' })).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: /ПП без сахара/ })[0]).toHaveAttribute('href', '/catalog/pp-bez-sahara/');
  });

  it('категория: сортировка по цене и фильтр ПП меняют список и URL', () => {
    const { router } = renderApp(<CategoryPage />, { path: '/catalog/:category/', route: '/catalog/vkusy/' });
    const names = () => screen.getAllByRole('heading', { level: 2 }).map((h) => h.textContent).filter((t) => ['Кофе', 'Маракуйя', 'Фисташка', 'Черника'].includes(t!));
    expect(names()).toEqual(['Кофе', 'Маракуйя', 'Фисташка', 'Черника']);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'price_asc' } });
    expect(names()[0]).toBe('Фисташка');
    expect(router.state.location.search).toBe('?sort=price_asc');
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'default' } });
    expect(router.state.location.search).toBe('');
  });

  it('категория со смешанными товарами показывает фильтр «только ПП»', () => {
    const vkusy = catalog.categories.find((c) => c.slug === 'vkusy')!;
    const original = [...vkusy.crossListed];
    vkusy.crossListed.push('pp-banan');
    const { router } = renderApp(<CategoryPage />, { path: '/catalog/:category/', route: '/catalog/vkusy/' });
    fireEvent.click(screen.getByRole('checkbox'));
    expect(router.state.location.search).toBe('?pp=1');
    expect(screen.getByRole('heading', { level: 2, name: 'ПП-банан' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { level: 2, name: 'Кофе' })).toBeNull();
    fireEvent.click(screen.getByRole('checkbox'));
    expect(router.state.location.search).toBe('');
    vkusy.crossListed.splice(0, vkusy.crossListed.length, ...original);
  });

  it('«Другие десерты»: список позиций и каналы заказа', () => {
    renderApp(<CategoryPage />, { path: '/catalog/:category/', route: '/catalog/drugie-deserty/' });
    for (const name of ['Зефир', 'Эклеры', 'Бенто-торты', 'Картошка']) expect(screen.getByRole('heading', { level: 2, name })).toBeInTheDocument();
  });

  it('несуществующая категория и страница пагинации за пределами — 404-страница', () => {
    renderApp(<CategoryPage />, { path: '/catalog/:category/', route: '/catalog/net/' });
    expect(screen.getByRole('heading', { level: 1, name: 'Страница не найдена' })).toBeInTheDocument();
  });

  it('пагинация вне диапазона — 404', () => {
    renderApp(<CategoryPage />, { path: '/catalog/:category/page/:page/', route: '/catalog/vkusy/page/9/' });
    expect(screen.getByRole('heading', { name: 'Страница не найдена' })).toBeInTheDocument();
  });
});

describe('карточка товара', () => {
  it('добавляет в корзину, обновляет живую цену из API, показывает связанные товары', async () => {
    mockApi({ 'GET /catalog/products/kofe': { data: { ...kofe, variants: [{ ...kofe.variants[0], price: 47000 }] } } });
    const { container } = renderApp(<ProductPage />, { path: '/catalog/:category/:product/', route: '/catalog/vkusy/kofe/' });
    expect(screen.getByRole('heading', { level: 1, name: 'Кофе' })).toBeInTheDocument();
    await waitFor(() => expect(container.querySelector('.price--lg')?.textContent).toBe('470 ₽'));
    fireEvent.click(screen.getByRole('button', { name: 'В корзину' }));
    expect(JSON.parse(window.localStorage.getItem('zp_cart')!)).toEqual([{ variantId, qty: 1 }]);
    expect(screen.getByRole('link', { name: 'Перейти в корзину' })).toHaveAttribute('href', '/cart/');
    expect(screen.getByRole('heading', { name: 'С этим покупают' })).toBeInTheDocument();
    expect(screen.getByText(/Отзыв можно оставить/)).toBeInTheDocument();
  });

  it('товар не из этой категории — 404 (без дублей URL)', () => {
    renderApp(<ProductPage />, { path: '/catalog/:category/:product/', route: '/catalog/podarki/nabor-4/' });
    expect(screen.getByRole('heading', { name: 'Страница не найдена' })).toBeInTheDocument();
  });

  it('при недоступном API цена остаётся из статики', async () => {
    mockApi({});
    const { container } = renderApp(<ProductPage />, { path: '/catalog/:category/:product/', route: '/catalog/vkusy/kofe/' });
    await new Promise((r) => setTimeout(r, 20));
    expect(container.querySelector('.price--lg')?.textContent).toBe('450 ₽');
  });

  it('выбор варианта у товара с несколькими вариантами', () => {
    const nabor = productBySlug('nabor-4')!;
    const second = { ...nabor.variants[0], id: 9001, sku: 'X', name: 'ПП, 4 шт.', price: 110000, priceFrom: false };
    nabor.variants.push(second);
    mockApi({});
    const { container } = renderApp(<ProductPage />, { path: '/catalog/:category/:product/', route: '/catalog/nabory/nabor-4/' });
    fireEvent.click(screen.getByRole('radio', { name: /ПП, 4 шт./ }));
    expect(container.querySelector('.price--lg')?.textContent).toBe('1 100 ₽');
    nabor.variants.pop();
  });
});

describe('корзина', () => {
  it('меняет количество, удаляет, считает итог', () => {
    window.localStorage.setItem('zp_cart', JSON.stringify([{ variantId, qty: 2 }, { variantId: 99999, qty: 1 }]));
    const { container } = renderApp(<Cart />, { path: '/cart/', route: '/cart/' });
    expect(container.querySelector('.cart__total strong')?.textContent).toBe('900 ₽');
    fireEvent.click(screen.getByRole('button', { name: 'Больше' }));
    expect(container.querySelector('.cart__total strong')?.textContent).toBe('1 350 ₽');
    fireEvent.click(screen.getByRole('button', { name: 'Меньше' }));
    fireEvent.click(screen.getByRole('button', { name: 'Меньше' }));
    fireEvent.click(screen.getByRole('button', { name: 'Меньше' }));
    expect(screen.getByText('Корзина пуста.')).toBeInTheDocument();
  });

  it('удаление позиции', () => {
    window.localStorage.setItem('zp_cart', JSON.stringify([{ variantId, qty: 1 }]));
    renderApp(<Cart />);
    fireEvent.click(screen.getByRole('button', { name: 'Удалить' }));
    expect(JSON.parse(window.localStorage.getItem('zp_cart')!)).toEqual([]);
  });
});

describe('оформление заказа', () => {
  const base = { name: 'Мария', phone: '+7 999 123-45-67', email: '', fulfilment: 'pickup', slotId: '1', consent: true };

  it('схема: слот для самовывоза и курьера, адрес для доставки, согласие', () => {
    expect(checkoutSchema.safeParse(base).success).toBe(true);
    expect(checkoutSchema.safeParse({ ...base, slotId: undefined }).error?.issues[0].path).toEqual(['slotId']);
    expect(checkoutSchema.safeParse({ ...base, fulfilment: 'courier' }).error?.issues[0].path).toEqual(['address']);
    expect(checkoutSchema.safeParse({ ...base, fulfilment: 'cdek', slotId: undefined, address: 'Казань' }).success).toBe(true);
    expect(checkoutSchema.safeParse({ ...base, consent: false }).success).toBe(false);
    expect(checkoutSchema.safeParse({ ...base, phone: '123' }).success).toBe(false);
    expect(checkoutSchema.safeParse({ ...base, email: 'bad' }).success).toBe(false);
  });

  it('пустая корзина — ссылка в каталог', () => {
    renderApp(<Checkout />);
    expect(screen.getByText(/Корзина пуста/)).toBeInTheDocument();
  });

  it('показывает ошибки, считает сумму на сервере и отправляет заказ на оплату', async () => {
    window.localStorage.setItem('zp_cart', JSON.stringify([{ variantId, qty: 2 }]));
    const assign = vi.fn();
    Object.defineProperty(window, 'location', { configurable: true, value: { ...window.location, assign } });
    const { calls } = mockApi({
      'GET /slots': { data: [{ id: 7, date: '2026-10-27', from: '12:00', to: '13:00', available: 3 }, { id: 8, date: '2026-10-27', from: '13:00', to: '14:00', available: 0 }] },
      'POST /cart/quote': { data: { subtotal: 90000, discount: 0, pointsSpent: 0, deliveryCost: 0, total: 90000, promoError: null, delivery: null, maxPoints: 0 } },
      'POST /orders': { data: { number: 'ZP-1' }, payment: { url: 'https://pay.test/1' } },
    });
    renderApp(<Checkout />);
    await screen.findByText('К оплате');
    fireEvent.click(screen.getByRole('button', { name: 'Перейти к оплате' }));
    expect(await screen.findByText('Укажите имя')).toBeInTheDocument();
    expect(screen.getByText('Нужно согласие на обработку персональных данных')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Имя'), { target: { value: 'Мария' } });
    fireEvent.change(screen.getByLabelText('Телефон'), { target: { value: '+79991234567' } });
    await waitFor(() => expect(screen.getAllByRole('option').length).toBe(3));
    expect(screen.getByRole('option', { name: /13:00–14:00 — занято/ })).toBeDisabled();
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '7' } });
    fireEvent.click(screen.getByRole('checkbox'));
    fireEvent.click(screen.getByRole('button', { name: 'Перейти к оплате' }));

    await waitFor(() => expect(assign).toHaveBeenCalledWith('https://pay.test/1'));
    const order = calls.find((c) => c.method === 'POST' && c.path === '/orders')!.body as Record<string, unknown>;
    expect(order).toMatchObject({ name: 'Мария', fulfilment: 'pickup', slotId: 7, address: null, lines: [{ variantId, qty: 2 }] });
    expect(window.localStorage.getItem('zp_cart')).toBe('[]');
  });

  it('доставка курьером: подсказки адреса, интервал, блокировка вне зоны', async () => {
    window.localStorage.setItem('zp_cart', JSON.stringify([{ variantId, qty: 1 }]));
    const { calls } = mockApi({
      'GET /slots': { data: [] },
      'GET /geo/suggest': { data: [{ text: 'Москва, Тверская, 1', lat: 55.75, lng: 37.61 }] },
      'POST /cart/quote': (_u: string, init: RequestInit) => {
        const body = JSON.parse(String(init.body));
        const outside = body.address?.lat === 55.75;
        return { data: { subtotal: 45000, discount: 0, pointsSpent: 0, deliveryCost: 0, total: 45000, promoError: 'Промокод не найден', maxPoints: 0,
          delivery: { provider: 'courier', available: !outside, cost: 0, etaText: '', zoneId: null, message: outside ? 'Адрес вне зоны курьерской доставки.' : null } } };
      },
    });
    renderApp(<Checkout />);
    fireEvent.click(screen.getByRole('radio', { name: 'Курьером по Москве' }));
    fireEvent.change(screen.getByLabelText('Адрес'), { target: { value: 'Тверская 1' } });
    fireEvent.click(await screen.findByRole('button', { name: 'Москва, Тверская, 1' }));
    expect(await screen.findByText('Адрес вне зоны курьерской доставки.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Перейти к оплате' })).toBeDisabled();
    expect(screen.getByLabelText('Подъезд')).toBeInTheDocument();
    expect(screen.getByText('Промокод не найден')).toBeInTheDocument();
    expect(calls.some((c) => c.path.startsWith('/slots?type=courier'))).toBe(true);
  });

  it('ошибка сервера при оформлении показывается пользователю', async () => {
    window.localStorage.setItem('zp_cart', JSON.stringify([{ variantId, qty: 1 }]));
    mockApi({
      'GET /slots': { data: [{ id: 7, date: '2026-10-27', from: '12:00', to: '13:00', available: 3 }] },
      'POST /cart/quote': { data: { subtotal: 45000, discount: 0, pointsSpent: 0, deliveryCost: 0, total: 45000, promoError: null, delivery: null, maxPoints: 0 } },
      'POST /orders': () => jsonResponse(422, { message: 'Это время недоступно, выберите другое', errors: { slotId: ['Это время недоступно, выберите другое'] } }),
    });
    renderApp(<Checkout />);
    fireEvent.change(screen.getByLabelText('Имя'), { target: { value: 'Мария' } });
    fireEvent.change(screen.getByLabelText('Телефон'), { target: { value: '+79991234567' } });
    await waitFor(() => expect(screen.getAllByRole('option').length).toBe(2));
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '7' } });
    fireEvent.click(screen.getByRole('checkbox'));
    fireEvent.click(screen.getByRole('button', { name: 'Перейти к оплате' }));
    expect((await screen.findAllByText('Это время недоступно, выберите другое')).length).toBeGreaterThan(0);
  });
});

describe('отслеживание, поиск, контентные страницы', () => {
  it('отслеживание по токену и повторная оплата', async () => {
    const assign = vi.fn();
    Object.defineProperty(window, 'location', { configurable: true, value: { ...window.location, assign } });
    mockApi({
      'GET /track/*': { data: { number: 'ZP-7', status: 'pending_payment', paymentStatus: 'failed', slot: { date: '2026-10-27', from: '12:00', to: '13:00' }, delivery: null, history: [] } },
      'POST /track/*': { payment: { url: 'https://pay.test/retry' } },
    });
    renderApp(<Track />, { path: '/track/', route: '/track/?t=' + 'a'.repeat(40) });
    expect(await screen.findByText(/Заказ № ZP-7: Ожидает оплаты/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Оплатить заказ' }));
    await waitFor(() => expect(assign).toHaveBeenCalledWith('https://pay.test/retry'));
  });

  it('отслеживание без токена и с недействительным токеном', async () => {
    mockApi({});
    renderApp(<Track />, { path: '/track/', route: '/track/' });
    expect(screen.getByText(/Откройте ссылку из уведомления/)).toBeInTheDocument();
    renderApp(<Track />, { path: '/track/', route: '/track/?t=bad' });
    expect(await screen.findByText(/недействительна/)).toBeInTheDocument();
  });

  it('поиск: результаты из API, при ошибке — по снимку каталога', async () => {
    mockApi({ 'GET /catalog/products': { data: [kofe], meta: {} } });
    const { router } = renderApp(<Search />, { path: '/poisk/', route: '/poisk/?q=кофе' });
    expect(await screen.findByRole('heading', { level: 2, name: 'Кофе' })).toBeInTheDocument();
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'фисташ' } });
    fireEvent.submit(screen.getByRole('search'));
    expect(router.state.location.search).toBe('?q=%D1%84%D0%B8%D1%81%D1%82%D0%B0%D1%88');

    mockApi({});
    renderApp(<Search />, { path: '/poisk/', route: '/poisk/?q=черни' });
    expect(await screen.findByRole('heading', { level: 2, name: 'Черника' })).toBeInTheDocument();
    renderApp(<Search />, { path: '/poisk/', route: '/poisk/?q=несуществующее' });
    expect(await screen.findByText(/Ничего не нашлось/)).toBeInTheDocument();
  });

  it('контентные страницы: один h1 и тексты со старого сайта', () => {
    const cases: [ReactElement, string, string][] = [
      [<IndividualOrderPage />, 'Подарок и индивидуальный заказ', 'Индивидуальная надпись'],
      [<CorporatePage />, 'Для компаний и мероприятий', 'Брендированное оформление'],
      [<FounderPage />, 'Красольда Тихоновна', 'Основатель кондитерской'],
      [<ProductionPage />, 'Производство', 'Ручная работа на профессиональной кухне'],
      [<ReviewsPage />, 'Отзывы', 'Что говорят наши клиенты'],
      [<DeliveryPage />, 'Доставка и оплата', 'По предварительному заказу и из наличия'],
      [<ContactsPage />, 'Где заказать', '+7 (968) 479-15-20'],
      [<FaqPage />, 'Частые вопросы', 'Рекомендуем оформлять заказ минимум за 3 дня. Для корпоративных заказов — заранее.'],
      [<LegalPage kind="privacy" />, 'Политика обработки персональных данных', 'ИНН 772442265466'],
      [<LegalPage kind="offer" />, 'Публичная оферта', 'ОГРНИП 323774600785438'],
      [<NotFound code={500} />, 'Ошибка на сервере', 'Мы уже чиним'],
    ];
    for (const [ui, h1, text] of cases) {
      const { unmount, container } = renderApp(ui);
      expect(screen.getAllByRole('heading', { level: 1 }).map((h) => h.textContent)).toEqual([h1]);
      expect(container.textContent).toContain(text);
      unmount();
    }
  });

  it('доставка: таблица зон, если они есть', () => {
    catalog.deliveryZones.push({ id: 1, name: 'Север', price: 30000, minOrder: 100000, freeFrom: null, etaText: 'от 1 ч 30 мин' });
    renderApp(<DeliveryPage />);
    const row = screen.getByRole('row', { name: /Север/ });
    expect(within(row).getByText('300 ₽', { normalizer: (s) => s })).toBeInTheDocument();
    catalog.deliveryZones.pop();
  });
});
