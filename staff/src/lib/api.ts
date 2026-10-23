import { createApiClient, storageTokenStore } from '@zefir/shared';

const env = import.meta.env;
export const API_URL = (env.VITE_API_URL as string | undefined) ?? 'http://localhost:8000';

// Служебный токен живёт только на штат.зефир-и-пряник.рф и только в sessionStorage вкладки:
// закрыли вкладку — нужен повторный вход. На сервере срок жизни ограничен SANCTUM_STAFF_TTL.
// Исключение — курьер: PWA на телефоне держит токен в localStorage до конца смены (до истечения TTL).
const storage = () => {
  if (typeof window === 'undefined') return undefined;
  return window.location.pathname.startsWith('/courier') ? window.localStorage : window.sessionStorage;
};

export const tokens = storageTokenStore('zp_staff_token', storage);

export const api = createApiClient({
  baseUrl: API_URL,
  tokens,
  onUnauthorized: () => {
    if (!window.location.pathname.startsWith('/login')) window.location.assign(`/login?next=${encodeURIComponent(window.location.pathname)}`);
  },
});
