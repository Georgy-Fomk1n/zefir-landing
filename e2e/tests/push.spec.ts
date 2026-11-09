import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { SITE } from '../env';
import { customerToken } from '../support/api';

// Web Push в Chromium: подписка из кабинета → сохранение на сервере → SW показывает уведомление по push-сообщению.
// Push-сервис браузера (FCM) из CI недоступен, поэтому PushManager.subscribe подменяем подпиской с настоящими
// ключами P-256, а само push-сообщение доставляем в SW через CDP (ServiceWorker.deliverPushMessage).
test.skip(({ browserName }) => browserName !== 'chromium', 'CDP есть только в Chromium');

function stubPushService() {
  const KEY = 'e2e_push_sub';
  const make = (json: PushSubscriptionJSON) => ({
    endpoint: json.endpoint!, toJSON: () => json,
    unsubscribe: async () => { localStorage.removeItem(KEY); return true; },
  });
  PushManager.prototype.subscribe = async function (options?: PushSubscriptionOptionsInit) {
    if (!options?.applicationServerKey) throw new Error('нет applicationServerKey');
    const pair = await crypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveBits']);
    const raw = new Uint8Array(await crypto.subtle.exportKey('raw', pair.publicKey));
    const b64 = (b: Uint8Array) => btoa(String.fromCharCode(...b)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const json = { endpoint: `https://push.e2e.test/${crypto.randomUUID()}`, keys: { p256dh: b64(raw), auth: b64(crypto.getRandomValues(new Uint8Array(16))) } };
    localStorage.setItem(KEY, JSON.stringify(json));
    return make(json) as unknown as PushSubscription;
  };
  PushManager.prototype.getSubscription = async function () {
    const saved = localStorage.getItem(KEY);
    return (saved ? make(JSON.parse(saved)) : null) as unknown as PushSubscription | null;
  };
}

async function swRegistration(context: BrowserContext, page: Page) {
  const cdp = await context.newCDPSession(page);
  const ids: string[] = [];
  cdp.on('ServiceWorker.workerRegistrationUpdated', (e) => {
    for (const r of e.registrations) if (r.scopeURL.startsWith(SITE) && !r.isDeleted) ids.push(r.registrationId);
  });
  await cdp.send('ServiceWorker.enable');
  await expect.poll(() => ids.length).toBeGreaterThan(0);
  return { cdp, registrationId: ids[0] };
}

test('включение уведомлений, показ push и выключение', async ({ context, page }) => {
  await context.grantPermissions(['notifications'], { origin: SITE });
  const token = await customerToken();
  await page.addInitScript((t) => {
    localStorage.setItem('zp_token', t);
    localStorage.setItem('zp_cookie_consent', 'no');
  }, token);
  await page.addInitScript(stubPushService);
  await page.goto('/account/settings');

  const toggle = page.getByTestId('push-toggle');
  const saved = page.waitForResponse((r) => new URL(r.url()).pathname === '/api/account/push' && r.request().method() === 'POST');
  await toggle.getByRole('button', { name: 'Включить уведомления' }).click();
  const res = await saved;
  expect(res.status()).toBeLessThan(300);
  const body = res.request().postDataJSON();
  expect(body.endpoint).toMatch(/^https:\/\//);
  expect(body.keys.p256dh).toBeTruthy();
  expect(body.keys.auth).toBeTruthy();
  await expect(toggle.getByRole('button', { name: 'Выключить уведомления' })).toHaveAttribute('aria-pressed', 'true');

  // SW получает push и показывает уведомление с заголовком, текстом и ссылкой на заказ
  const { cdp, registrationId } = await swRegistration(context, page);
  await cdp.send('ServiceWorker.deliverPushMessage', {
    origin: SITE, registrationId,
    data: JSON.stringify({ title: 'Заказ № 1001', body: 'Курьер в пути', url: '/account/orders/1', tag: 'order-1' }),
  });
  await expect.poll(() => page.evaluate(async () => {
    const reg = await navigator.serviceWorker.ready;
    return (await reg.getNotifications()).map((n) => ({ title: n.title, body: n.body, tag: n.tag, url: (n.data as { url: string }).url }));
  })).toEqual([{ title: 'Заказ № 1001', body: 'Курьер в пути', tag: 'order-1', url: '/account/orders/1' }]);

  // После перезагрузки состояние берётся из текущей подписки
  await page.reload();
  await expect(toggle.getByRole('button', { name: 'Выключить уведомления' })).toBeVisible();

  const removed = page.waitForResponse((r) => new URL(r.url()).pathname === '/api/account/push' && r.request().method() === 'DELETE');
  await toggle.getByRole('button', { name: 'Выключить уведомления' }).click();
  expect((await removed).status()).toBeLessThan(300);
  await expect(toggle.getByRole('button', { name: 'Включить уведомления' })).toHaveAttribute('aria-pressed', 'false');
});

test('запрет уведомлений в браузере: понятная подсказка вместо кнопки', async ({ browser }) => {
  const context = await browser.newContext({ permissions: [] });
  const page = await context.newPage();
  const token = await customerToken();
  await page.addInitScript((t) => {
    localStorage.setItem('zp_token', t);
    localStorage.setItem('zp_cookie_consent', 'no');
  }, token);
  // Эмулируем «Блокировать» в настройках сайта
  const cdp = await context.newCDPSession(page);
  await cdp.send('Browser.setPermission', { permission: { name: 'notifications' }, setting: 'denied', origin: SITE });
  await page.goto(`${SITE}/account/settings`);
  await expect(page.getByTestId('push-toggle').getByRole('note')).toContainText('Уведомления запрещены');
  await context.close();
});
