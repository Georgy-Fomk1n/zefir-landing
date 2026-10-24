import { expect, test, type Page } from '@playwright/test';
import { customerToken } from '../support/api';

// Адаптив под iPhone: проекты iphone-13 (390×664) и iphone-se (320/375), плюс отдельная проверка самой узкой ширины 320.
const PAGES = ['/', '/catalog/', '/catalog/vkusy/', '/catalog/vkusy/kofe/', '/cart/', '/checkout/', '/dostavka-i-oplata/', '/kontakty/', '/otzyvy/', '/account/'];

async function noHorizontalScroll(page: Page) {
  const { scroll, width } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, width: window.innerWidth }));
  expect(scroll, `горизонтальная прокрутка на ${page.url()}`).toBeLessThanOrEqual(width);
}

test.beforeEach(async ({ page }) => {
  // Баннер cookie закрываем заранее, чтобы он не перекрывал элементы
  await page.addInitScript(() => {
    try { localStorage.setItem('zp_cookie_consent', 'no'); } catch { /* приватный режим */ }
  });
});

test('мета-теги веб-приложения для iOS: viewport-fit, «На экран Домой», иконка', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('meta[name="viewport"]')).toHaveAttribute('content', /viewport-fit=cover/);
  await expect(page.locator('meta[name="apple-mobile-web-app-capable"]')).toHaveAttribute('content', 'yes');
  await expect(page.locator('meta[name="apple-mobile-web-app-title"]')).toHaveAttribute('content', 'Зефир и пряник');
  const icon = await page.locator('link[rel="apple-touch-icon"]').getAttribute('href');
  expect((await page.request.get(icon!)).status()).toBe(200);
  const manifest = await page.locator('link[rel="manifest"]').getAttribute('href');
  const json = await (await page.request.get(manifest!)).json();
  expect(json.display).toBe('standalone');
});

for (const path of PAGES) {
  test(`без горизонтальной прокрутки: ${path}`, async ({ page }) => {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await noHorizontalScroll(page);
  });
}

test('самая узкая ширина 320 px (iPhone SE 1-го поколения)', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 568 });
  for (const path of ['/', '/catalog/vkusy/kofe/', '/checkout/']) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await noHorizontalScroll(page);
  }
});

test('поля ввода не меньше 16 px — Safari не увеличивает страницу при фокусе', async ({ page }) => {
  await page.goto('/catalog/vkusy/kofe/');
  await page.getByRole('button', { name: 'В корзину' }).tap();
  for (const path of ['/checkout/', '/account/']) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    const sizes = await page.locator('input:visible, select:visible, textarea:visible').evaluateAll((els) =>
      els.map((el) => ({ name: (el as HTMLInputElement).name || el.tagName, size: parseFloat(getComputedStyle(el).fontSize) })));
    expect(sizes.length).toBeGreaterThan(0);
    for (const s of sizes) expect(s.size, `${path} ${s.name}`).toBeGreaterThanOrEqual(16);
  }
});

test('зоны нажатия кнопок и меню — от 44 px', async ({ page }) => {
  await page.goto('/catalog/vkusy/kofe/');
  const targets = page.locator('header button:visible, header a.cart-link:visible, main .btn:visible');
  const boxes = await targets.evaluateAll((els) => els.map((el) => {
    const r = el.getBoundingClientRect();
    return { text: el.textContent?.trim() || el.getAttribute('aria-label'), h: r.height, w: r.width };
  }));
  expect(boxes.length).toBeGreaterThan(1);
  for (const b of boxes) {
    expect(b.h, `высота «${b.text}»`).toBeGreaterThanOrEqual(44);
    expect(b.w, `ширина «${b.text}»`).toBeGreaterThanOrEqual(44);
  }
});

test('бургер-меню открывается, ведёт в раздел и закрывается', async ({ page }) => {
  await page.goto('/');
  const burger = page.getByRole('button', { name: 'Меню' });
  await expect(burger).toBeVisible();
  await expect(burger).toHaveAttribute('aria-expanded', 'false');
  await burger.tap();
  await expect(burger).toHaveAttribute('aria-expanded', 'true');
  const nav = page.getByRole('navigation', { name: 'Основное меню' });
  await expect(nav).toBeVisible();
  await nav.getByRole('link', { name: 'Доставка' }).first().tap();
  await expect(page).toHaveURL(/\/dostavka-i-oplata\/$/);
  await expect(burger).toHaveAttribute('aria-expanded', 'false');
  await noHorizontalScroll(page);
});

test('покупка пальцем: товар → корзина без промахов', async ({ page }) => {
  await page.goto('/catalog/vkusy/kofe/');
  await page.getByRole('button', { name: 'В корзину' }).tap();
  await page.getByRole('link', { name: 'Перейти в корзину' }).tap();
  await expect(page).toHaveURL(/\/cart\/$/);
  await expect(page.locator('.cart__total strong')).toBeVisible();
  await noHorizontalScroll(page);
});

test.describe('push на iPhone', () => {
  // В профилях Playwright стоит iOS 15 — для сценария с поддержкой push подставляем iOS 17
  const ios = (v: string) => `Mozilla/5.0 (iPhone; CPU iPhone OS ${v} like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/${v.replace('_', '.')} Mobile/15E148 Safari/604.1`;

  async function openSettings(page: Page) {
    const token = await customerToken();
    await page.addInitScript((t) => localStorage.setItem('zp_token', t), token);
    await page.goto('/account/settings');
    const toggle = page.getByTestId('push-toggle');
    await expect(toggle).toBeVisible();
    return toggle;
  }

  test.describe('iOS 17 во вкладке Safari', () => {
    test.use({ userAgent: ios('17_2') });
    test('инструкция «На экран Домой» вместо кнопки', async ({ page }) => {
      const toggle = await openSettings(page);
      await expect(toggle.getByRole('note')).toContainText('На экран „Домой“');
      await expect(toggle.getByRole('button', { name: 'Включить уведомления' })).toHaveCount(0);
      await noHorizontalScroll(page);
    });
  });

  test.describe('iOS 15', () => {
    test.use({ userAgent: ios('15_7') });
    test('сообщение о версии iOS 16.4+', async ({ page }) => {
      const toggle = await openSettings(page);
      await expect(toggle.getByRole('note')).toContainText('начиная с iOS 16.4');
    });
  });
});
