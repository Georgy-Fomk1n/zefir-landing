import { createApiClient, type TokenStore } from '@zefir/shared';

const env = import.meta.env;
export const API_URL = (env.VITE_API_URL as string | undefined) ?? 'http://localhost:8000';

const KEY = 'zp_staff_token';

function safe<T>(fn: () => T, fallback: T): T {
  try {
    return fn();
  } catch {
    return fallback;
  }
}

/**
 * Служебный токен живёт только на штат.зефир-и-пряник.рф. По умолчанию — в sessionStorage вкладки:
 * закрыли вкладку — нужен повторный вход. Исключение — курьер без других ролей: PWA на телефоне
 * держит токен в localStorage (до истечения SANCTUM_STAFF_TTL на сервере), чтобы смена не прерывалась.
 */
export const tokens: TokenStore & { setPersistent(token: string, persistent: boolean): void } = {
  get: () => safe(() => window.sessionStorage.getItem(KEY) ?? window.localStorage.getItem(KEY), null),
  set(token) {
    safe(() => {
      if (token) window.sessionStorage.setItem(KEY, token);
      else {
        window.sessionStorage.removeItem(KEY);
        window.localStorage.removeItem(KEY);
      }
    }, undefined);
  },
  setPersistent(token, persistent) {
    safe(() => {
      window.sessionStorage.removeItem(KEY);
      window.localStorage.removeItem(KEY);
      (persistent ? window.localStorage : window.sessionStorage).setItem(KEY, token);
    }, undefined);
  },
};

export const api = createApiClient({
  baseUrl: API_URL,
  tokens,
  onUnauthorized: () => {
    if (!window.location.pathname.startsWith('/login')) window.location.assign(`/login?next=${encodeURIComponent(window.location.pathname)}`);
  },
});

/** Web Push сотрудника: новые заказы (продавец), назначенные доставки (курьер) */
export const staffPushApi = {
  publicKey: () => api.get<{ publicKey: string | null }>('/push/key').then((r) => r.publicKey),
  save: (subscription: PushSubscriptionJSON) => api.post('/staff/push', subscription),
  remove: (endpoint: string) => api.delete(`/staff/push?endpoint=${encodeURIComponent(endpoint)}`),
};
