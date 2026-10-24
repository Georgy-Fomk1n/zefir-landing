import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, createApiClient, memoryTokenStore, storageTokenStore } from './api';
import { deliveryStatusLabels, fulfilmentLabels, orderStatusLabels, subscriptionStatusLabels } from './statuses';
import {
  disablePush, enablePush, iosVersion, isIos, parsePushPayload, pushSupport, urlBase64ToUint8Array, currentPushEndpoint,
} from './push';

const IPHONE_17 = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';
const IPHONE_15 = 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.7 Mobile/15E148 Safari/604.1';
const IPAD_OS = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15';
const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';

const env = (over: Partial<Parameters<typeof pushSupport>[0]> = {}) => ({
  userAgent: ANDROID, maxTouchPoints: 5, standalone: false, hasServiceWorker: true, hasPushManager: true,
  hasNotification: true, permission: 'default' as const, ...over,
});

describe('клиент API', () => {
  const json = (status: number, body: unknown) => new Response(status === 204 ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

  it('передаёт Bearer-токен и JSON, отдаёт тело ответа', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(json(200, { data: 1 }));
    const tokens = memoryTokenStore();
    tokens.set('t1');
    const api = createApiClient({ baseUrl: 'https://api.test/', tokens, fetchImpl });
    await expect(api.post('/orders', { a: 1 })).resolves.toEqual({ data: 1 });
    const [url, init] = fetchImpl.mock.calls[0];
    expect(url).toBe('https://api.test/api/orders');
    expect(init.headers.Authorization).toBe('Bearer t1');
    expect(init.headers['Content-Type']).toBe('application/json');
    expect(init.body).toBe('{"a":1}');
  });

  it('отправляет FormData без Content-Type и понимает 204', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(json(204, null));
    const api = createApiClient({ baseUrl: 'https://api.test', tokens: memoryTokenStore(), fetchImpl });
    const fd = new FormData();
    fd.set('status', 'delivered');
    await expect(api.put('/x', fd)).resolves.toBeUndefined();
    expect(fetchImpl.mock.calls[0][1].headers['Content-Type']).toBeUndefined();
    await api.get('/x');
    await api.patch('/x', {});
    await api.delete('/x');
    expect(fetchImpl.mock.calls.map((c) => c[1].method)).toEqual(['PUT', 'GET', 'PATCH', 'DELETE']);
  });

  it('при 401 сбрасывает токен и сообщает приложению', async () => {
    const tokens = memoryTokenStore();
    tokens.set('old');
    const onUnauthorized = vi.fn();
    const api = createApiClient({ baseUrl: 'https://api.test', tokens, onUnauthorized, fetchImpl: vi.fn().mockResolvedValue(json(401, { message: 'Unauthenticated.' })) });
    await expect(api.get('/auth/me')).rejects.toMatchObject({ status: 401, message: 'Unauthenticated.' });
    expect(tokens.get()).toBeNull();
    expect(onUnauthorized).toHaveBeenCalledOnce();
  });

  it('возвращает ошибки валидации полями', async () => {
    const api = createApiClient({ baseUrl: 'https://api.test', tokens: memoryTokenStore(), fetchImpl: vi.fn().mockResolvedValue(json(422, { message: 'bad', errors: { phone: ['Телефон'] } })) });
    const err = (await api.post('/orders', {}).catch((e: unknown) => e)) as ApiError;
    expect(err).toBeInstanceOf(ApiError);
    expect(err.errors.phone).toEqual(['Телефон']);
  });

  it('переживает ответ без JSON', async () => {
    const api = createApiClient({ baseUrl: 'https://api.test', tokens: memoryTokenStore(), fetchImpl: vi.fn().mockResolvedValue(new Response('oops', { status: 500 })) });
    await expect(api.get('/x')).rejects.toMatchObject({ status: 500, message: 'HTTP 500' });
  });

  it('хранилище токена в Storage устойчиво к запрету доступа', () => {
    const store = storageTokenStore('k', () => window.localStorage);
    store.set('abc');
    expect(store.get()).toBe('abc');
    store.set(null);
    expect(store.get()).toBeNull();
    const broken = storageTokenStore('k', () => { throw new Error('SecurityError'); });
    expect(broken.get()).toBeNull();
    expect(() => broken.set('x')).not.toThrow();
  });
});

describe('подписи статусов', () => {
  it('есть для всех статусов', () => {
    expect(Object.values(orderStatusLabels).every(Boolean)).toBe(true);
    expect(deliveryStatusLabels.in_transit).toBe('В пути');
    expect(fulfilmentLabels.courier).toBe('Курьером по Москве');
    expect(subscriptionStatusLabels.grace).toBe('Льготный период');
  });
});

describe('push: поддержка устройств', () => {
  it('распознаёт iPhone и iPadOS (Mac с сенсорным экраном)', () => {
    expect(isIos({ userAgent: IPHONE_17, maxTouchPoints: 5 })).toBe(true);
    expect(isIos({ userAgent: IPAD_OS, maxTouchPoints: 5 })).toBe(true);
    expect(isIos({ userAgent: IPAD_OS, maxTouchPoints: 0 })).toBe(false); // настоящий Mac
    expect(isIos({ userAgent: ANDROID, maxTouchPoints: 5 })).toBe(false);
    expect(iosVersion(IPHONE_17)).toBe(17.2);
    expect(iosVersion(ANDROID)).toBeNull();
  });

  it('на iPhone требует установки на экран «Домой» и iOS 16.4+', () => {
    expect(pushSupport(env({ userAgent: IPHONE_17, hasPushManager: false }))).toBe('ios-install');
    expect(pushSupport(env({ userAgent: IPHONE_17, standalone: true }))).toBe('supported');
    expect(pushSupport(env({ userAgent: IPHONE_15, standalone: true }))).toBe('ios-too-old');
    expect(pushSupport(env({ userAgent: IPAD_OS, standalone: false }))).toBe('ios-install');
  });

  it('учитывает запрет и отсутствие API', () => {
    expect(pushSupport(env({ permission: 'denied' }))).toBe('denied');
    expect(pushSupport(env())).toBe('supported');
    expect(pushSupport(env({ hasPushManager: false }))).toBe('unsupported');
    // Без аргумента читает текущее окружение (jsdom: PushManager нет)
    expect(['unsupported', 'denied']).toContain(pushSupport());
  });

  it('декодирует VAPID-ключ base64url', () => {
    const bytes = urlBase64ToUint8Array('BAEC_-8');
    expect(Array.from(bytes)).toEqual([4, 1, 2, 255, 239]);
  });

  it('разбирает полезную нагрузку push, не падая на мусоре', () => {
    expect(parsePushPayload('{"title":"Заказ","body":"Готов","url":"/account/orders/1","tag":"o1"}')).toEqual({ title: 'Заказ', body: 'Готов', url: '/account/orders/1', tag: 'o1' });
    expect(parsePushPayload('просто текст')).toMatchObject({ title: 'Зефир и пряник', body: 'просто текст', url: '/' });
    expect(parsePushPayload(null, '/account/')).toMatchObject({ body: '', url: '/account/' });
  });
});

describe('push: подписка', () => {
  const subscription = { endpoint: 'https://push.test/1', toJSON: () => ({ endpoint: 'https://push.test/1', keys: { p256dh: 'p', auth: 'a' } }), unsubscribe: vi.fn().mockResolvedValue(true) };
  let pushManager: { getSubscription: ReturnType<typeof vi.fn>; subscribe: ReturnType<typeof vi.fn> };
  let requestPermission: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    pushManager = { getSubscription: vi.fn().mockResolvedValue(null), subscribe: vi.fn().mockResolvedValue(subscription) };
    const registration = { pushManager };
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: { register: vi.fn().mockResolvedValue(registration), ready: Promise.resolve(registration), getRegistration: vi.fn().mockResolvedValue(registration) },
    });
    requestPermission = vi.fn().mockResolvedValue('granted');
    vi.stubGlobal('Notification', { requestPermission, permission: 'default' });
  });
  afterEach(() => vi.unstubAllGlobals());

  const api = () => ({ publicKey: vi.fn().mockResolvedValue('BAEC'), save: vi.fn().mockResolvedValue({}), remove: vi.fn().mockResolvedValue({}) });

  it('регистрирует SW, запрашивает разрешение, подписывается и сохраняет подписку', async () => {
    const a = api();
    await expect(enablePush(a, '/sw.js')).resolves.toBe('enabled');
    expect(navigator.serviceWorker.register).toHaveBeenCalledWith('/sw.js');
    expect(pushManager.subscribe).toHaveBeenCalledWith(expect.objectContaining({ userVisibleOnly: true }));
    expect(a.save).toHaveBeenCalledWith({ endpoint: 'https://push.test/1', keys: { p256dh: 'p', auth: 'a' } });
  });

  it('использует существующую подписку и не подписывается повторно', async () => {
    pushManager.getSubscription.mockResolvedValue(subscription);
    await enablePush(api());
    expect(pushManager.subscribe).not.toHaveBeenCalled();
    await expect(currentPushEndpoint()).resolves.toBe('https://push.test/1');
  });

  it('без ключа или без разрешения не подписывается', async () => {
    const noKey = api();
    noKey.publicKey.mockResolvedValue(null);
    await expect(enablePush(noKey)).resolves.toBe('no-key');
    requestPermission.mockResolvedValue('denied');
    await expect(enablePush(api())).resolves.toBe('denied');
    expect(pushManager.subscribe).not.toHaveBeenCalled();
  });

  it('отписывается и удаляет подписку на сервере', async () => {
    pushManager.getSubscription.mockResolvedValue(subscription);
    const a = api();
    await disablePush(a);
    expect(a.remove).toHaveBeenCalledWith('https://push.test/1');
    expect(subscription.unsubscribe).toHaveBeenCalled();
  });
});
