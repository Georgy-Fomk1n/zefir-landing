import { expect, test } from '@playwright/test';
import { STAFF } from '../env';
import { api, pay, placeOrder, staffToken } from '../support/api';

// Продавец ведёт заказ по очереди и назначает курьера; курьер (в проекте iphone-13 — на iPhone) работает без сети.
test('заказ с доставкой: очередь продавца → доска доставок → курьер офлайн → синхронизация', async ({ browser, page }) => {
  const order = await placeOrder('courier', 'Е2Е Доставка');
  await pay(order.paymentUrl);

  // ── Продавец (десктоп) ──
  const sellerCtx = await browser.newContext({ baseURL: STAFF, viewport: { width: 1280, height: 900 } });
  const seller = await sellerCtx.newPage();
  await seller.goto('/login');
  await seller.getByLabel('Email').fill('seller@example.test');
  await seller.getByLabel('Пароль').fill('password-123');
  await seller.getByRole('button', { name: 'Войти' }).click();
  await expect(seller).toHaveURL(/\/seller$/);
  await seller.locator('main input[type="date"]').fill(order.slotDate);

  const card = seller.locator('article.order', { hasText: `№ ${order.number}` });
  await expect(card.locator('.badge')).toHaveText('Оплачен');
  for (const [step, label] of [['→ Принят', 'Принят'], ['→ Готовится', 'Готовится'], ['→ Готов', 'Готов']]) {
    await card.getByRole('button', { name: step }).click();
    await expect(card.locator('.badge')).toHaveText(label);
  }

  await seller.getByRole('link', { name: 'Доставки' }).click();
  await seller.locator('main input[type="date"]').fill(order.slotDate);
  const boardCard = seller.locator('article.order', { hasText: `№ ${order.number}` });
  await expect(boardCard).toContainText('ул. Базовская, 15');
  // «Курьер» или «Курьер (не на смене)» — смотря по тому, открыта ли смена с прошлого прогона
  const courierOption = await boardCard.locator('option', { hasText: /^Курьер( \(не на смене\))?$/ }).getAttribute('value');
  await boardCard.getByRole('combobox').selectOption(courierOption!);
  await expect(boardCard.locator('.badge').nth(1)).toHaveText('Назначена');
  await sellerCtx.close();

  // ── Курьер (контекст проекта: в iphone-13 — iPhone) ──
  await page.goto(`${STAFF}/login`);
  await page.getByLabel('Email').fill('courier@example.test');
  await page.getByLabel('Пароль').fill('password-123');
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(/\/courier$/);
  await expect(page.getByRole('button', { name: /^(Начать|Закончить) смену$/ })).toBeVisible();
  const startShift = page.getByRole('button', { name: 'Начать смену' });
  if (await startShift.isVisible()) await startShift.click();
  await expect(page.getByRole('button', { name: 'Закончить смену' })).toBeVisible();

  const stop = page.locator('li.stop', { hasText: `№ ${order.number}` });
  await stop.locator('.stop__head').click();
  await expect(stop).toContainText('2 / 5 / 25К');
  await expect(stop.getByRole('link', { name: 'Яндекс Карты' })).toHaveAttribute('href', /rtext=~55\.881,37\.512/);
  await stop.getByRole('button', { name: 'Принять' }).click();
  await expect(stop).toContainText('Принята курьером');
  await expect(page.getByText(/в очереди/)).toHaveCount(0);

  // Нет сети: действие ложится в очередь и показывается сразу
  await page.context().setOffline(true);
  await expect(page.getByText(/Нет сети/)).toBeVisible();
  await stop.getByRole('button', { name: 'Забрал заказ' }).click();
  await expect(page.getByText(/в очереди: 1/)).toBeVisible();
  await expect(stop).toContainText('Курьер забрал заказ');

  // Пока сети нет, сервер о действии не знает
  const token = await staffToken('seller@example.test');
  const before = await api<{ data: { orders: { number: string; delivery: { status: string } | null }[] } }>('GET', `/staff/seller/deliveries?date=${order.slotDate}`, undefined, token);
  expect(before.data.orders.find((o) => o.number === order.number)?.delivery?.status).toBe('accepted');

  // Сеть вернулась — очередь уходит на сервер
  await page.context().setOffline(false);
  await expect(page.getByText('В сети')).toBeVisible();
  await expect(page.getByText(/в очереди/)).toHaveCount(0);
  await expect.poll(async () => {
    const after = await api<{ data: { orders: { number: string; delivery: { status: string } | null }[] } }>('GET', `/staff/seller/deliveries?date=${order.slotDate}`, undefined, token);
    return after.data.orders.find((o) => o.number === order.number)?.delivery?.status;
  }).toBe('picked_up');

  await stop.getByRole('button', { name: 'В пути' }).click();
  await expect(stop).toContainText('В пути');
  await stop.getByRole('button', { name: 'Доставлено' }).click();
  await expect(page.getByText(/Завершённые · \d+/)).toBeVisible();
  await expect(page.locator('li.stop', { hasText: `№ ${order.number}` })).toHaveCount(0);

  await page.getByRole('button', { name: 'Закончить смену' }).click();
  await expect(page.getByText(/Итог смены: доставлено \d+/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Начать смену' })).toBeVisible();
});
