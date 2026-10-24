import { expect, test } from '@playwright/test';
import { API } from '../env';

test.describe('публичный сайт', () => {
  test('пререндер: контент, мета и JSON-LD есть в HTML без JavaScript', async ({ browser }) => {
    const ctx = await browser.newContext({ javaScriptEnabled: false });
    const page = await ctx.newPage();
    const res = await page.goto('/catalog/vkusy/kofe/');
    expect(res?.status()).toBe(200);
    await expect(page.locator('h1')).toHaveText('Кофе');
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', 'https://зефир-и-пряник.рф/catalog/vkusy/kofe/');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'index,follow');
    const ld = await page.locator('script[type="application/ld+json"]').allTextContents();
    expect(ld.map((s) => JSON.parse(s)['@type'])).toEqual(expect.arrayContaining(['BreadcrumbList', 'Product']));
    await expect(page.getByText('Маскарпоне, печенье Савоярди, эспрессо зерновой, хрустящий шоколадный корпус')).toBeVisible();
    await ctx.close();
  });

  test('настоящие 404 и закрытые служебные пути', async ({ request }) => {
    expect((await request.get('/net-takoj-stranicy/')).status()).toBe(404);
    expect((await request.get('/admin/')).status()).toBe(404);
    expect((await request.get('/courier/')).status()).toBe(404);
    expect((await request.get('/catalog/vkusy/net/')).status()).toBe(404);
    const robots = await (await request.get('/robots.txt')).text();
    expect(robots).toContain('Disallow: /account/');
    expect(robots).not.toMatch(/admin|courier|seller/);
    expect((await request.get('/sitemap.xml')).status()).toBe(200);
    expect((await request.get('/sw.js')).status()).toBe(200);
  });

  test('гость: товар → корзина → оформление самовывоза → тестовая оплата → статус «Оплачен»', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'Только необходимые' }).click();
    await page.getByRole('link', { name: 'Все вкусы' }).click();
    await expect(page).toHaveURL(/\/catalog\/vkusy\/$/);
    await page.getByRole('heading', { name: 'Фисташка' }).getByRole('link').click();
    await page.getByRole('button', { name: 'В корзину' }).click();
    await page.getByRole('link', { name: 'Перейти в корзину' }).click();
    await page.getByRole('button', { name: 'Больше' }).click();
    await expect(page.locator('.cart__total strong')).toHaveText(/800/);
    await page.getByRole('link', { name: 'Оформить заказ' }).click();

    await page.getByLabel('Имя').fill('Е2Е Гость');
    await page.getByLabel('Телефон').fill('+79990001122');
    const slot = page.locator('select[name="slotId"]');
    await expect(slot.locator('option')).not.toHaveCount(1);
    await slot.selectOption({ index: 1 });
    await page.locator('input[name="consent"]').check();
    await expect(page.locator('.summary')).toContainText('К оплате');
    await page.getByRole('button', { name: 'Перейти к оплате' }).click();

    await expect(page).toHaveURL(new RegExp(`^${API}/api/payments/fake/`));
    await page.getByRole('link', { name: 'Оплатить' }).click();
    await expect(page).toHaveURL(/\/track\/\?t=.+&paid=1/);
    await expect(page.locator('.lead')).toContainText('Оплачен');
  });

  test('покупатель: регистрация, заказ, отказ банка и повторная оплата из кабинета', async ({ page }) => {
    await page.goto('/account/');
    await page.getByRole('button', { name: 'Только необходимые' }).click();
    await page.getByRole('tab', { name: 'Регистрация' }).click();
    await page.getByLabel('Имя').fill('Е2Е Покупатель');
    await page.getByLabel('Email').fill(`e2e-${Date.now()}@example.test`);
    await page.getByLabel('Пароль').fill('password-123');
    await page.locator('input[name="consent"]').check();
    await page.getByRole('button', { name: 'Зарегистрироваться' }).click();
    await expect(page.getByRole('navigation', { name: 'Разделы кабинета' })).toBeVisible();

    await page.goto('/catalog/pp-bez-sahara/pp-banan/');
    await page.getByRole('button', { name: 'В корзину' }).click();
    await page.goto('/checkout/');
    await expect(page.getByLabel('Имя')).toHaveValue('Е2Е Покупатель');
    await page.getByLabel('Телефон').fill('+79990001122');
    await page.locator('select[name="slotId"]').selectOption({ index: 1 });
    await page.locator('input[name="consent"]').check();
    await page.getByRole('button', { name: 'Перейти к оплате' }).click();
    await page.getByRole('link', { name: 'Отклонить' }).click();

    await expect(page).toHaveURL(/\/account\/orders\/\d+$/);
    await page.getByRole('button', { name: 'Оплатить заказ' }).click();
    await page.getByRole('link', { name: 'Оплатить' }).click();
    await expect(page).toHaveURL(/\/account\/orders\/\d+\?paid=1$/);
    await expect(page.getByText(/^Оплачен · Самовывоз/)).toBeVisible();
    await page.getByRole('link', { name: 'Карта лояльности' }).click();
    await expect(page.locator('.qr svg')).toBeVisible();
  });
});
