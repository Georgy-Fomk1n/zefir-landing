import { afterEach, describe, expect, it, vi } from 'vitest';

async function load(env: { appEnv: string; metrikaId: string }) {
  vi.resetModules();
  vi.doMock('../config', () => ({ config: { ...env, apiUrl: '', yandexVerification: '', googleVerification: '', ga4Id: '' }, indexable: env.appEnv === 'production' }));
  return import('./metrika');
}

afterEach(() => {
  vi.doUnmock('../config');
  delete window.ym;
  document.head.querySelectorAll('script[src*="mc.yandex.ru"]').forEach((s) => s.remove());
});

describe('Яндекс Метрика', () => {
  it('без согласия и вне production счётчик не грузится', async () => {
    const m = await load({ appEnv: 'staging', metrikaId: '123' });
    m.setConsent(true);
    m.scheduleMetrika();
    expect(document.querySelector('script[src*="mc.yandex.ru"]')).toBeNull();
    const prod = await load({ appEnv: 'production', metrikaId: '123' });
    window.localStorage.setItem('zp_cookie_consent', 'no');
    prod.scheduleMetrika();
    expect(document.querySelector('script[src*="mc.yandex.ru"]')).toBeNull();
    expect(prod.hasConsent()).toBe(false);
  });

  it('после согласия грузится отложенно — по первому взаимодействию', async () => {
    vi.useFakeTimers();
    const m = await load({ appEnv: 'production', metrikaId: '123' });
    m.setConsent(true);
    expect(document.querySelector('script[src*="mc.yandex.ru"]')).toBeNull();
    window.dispatchEvent(new Event('scroll'));
    expect(document.querySelector('script[src*="mc.yandex.ru"]')).not.toBeNull();
    expect(window.ym).toBeTypeOf('function');
    const ym = vi.fn();
    window.ym = ym as unknown as Window['ym'];
    m.reachGoal('add_to_cart', { variantId: 1 });
    expect(ym).toHaveBeenCalledWith(123, 'reachGoal', 'add_to_cart', { variantId: 1 });
    vi.useRealTimers();
  });

  it('или через 4 секунды простоя', async () => {
    vi.useFakeTimers();
    const m = await load({ appEnv: 'production', metrikaId: '77' });
    window.localStorage.setItem('zp_cookie_consent', 'yes');
    m.scheduleMetrika();
    vi.advanceTimersByTime(4000);
    expect(document.querySelector('script[src*="mc.yandex.ru"]')).not.toBeNull();
    vi.useRealTimers();
  });

  it('цели без счётчика молча игнорируются; хранилище может быть недоступно', async () => {
    const m = await load({ appEnv: 'production', metrikaId: '' });
    expect(() => m.reachGoal('x')).not.toThrow();
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('denied'); });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('denied'); });
    expect(m.hasConsent()).toBe(false);
    expect(() => m.setConsent(true)).not.toThrow();
  });
});
