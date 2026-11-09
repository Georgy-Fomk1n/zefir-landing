import { vi } from 'vitest';

type Handler = (url: string, init: RequestInit) => unknown;

/** Подмена fetch: «METHOD /path» (путь без /api, * в конце — префикс). Неописанный запрос — 404. */
export function mockApi(handlers: Record<string, Handler | unknown>) {
  const calls: { method: string; path: string; body: unknown }[] = [];
  const fetchMock = vi.fn(async (input: string, init: RequestInit = {}) => {
    const url = new URL(input);
    const method = (init.method ?? 'GET').toUpperCase();
    const path = url.pathname.replace(/^\/api/, '');
    const body = typeof init.body === 'string' ? JSON.parse(init.body) : init.body;
    calls.push({ method, path: path + url.search, body });
    const key = Object.keys(handlers).find((k) => {
      const [m, p] = k.split(' ');
      return m === method && (p === path || (p.endsWith('*') && path.startsWith(p.slice(0, -1))));
    });
    if (!key) return new Response(JSON.stringify({ message: 'not found' }), { status: 404 });
    const h = handlers[key];
    const result = typeof h === 'function' ? await (h as Handler)(input, init) : h;
    if (result instanceof Response) return result;
    return new Response(JSON.stringify(result), { status: 200, headers: { 'Content-Type': 'application/json' } });
  });
  vi.stubGlobal('fetch', fetchMock);
  return { fetchMock, calls };
}

export const noContent = () => new Response(null, { status: 204 });
export const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

export const users = {
  seller: { id: 2, name: 'Продавец', email: 's@example.test', phone: null, roles: ['seller'], marketingConsent: false, twoFactorEnabled: false },
  courier: { id: 3, name: 'Курьер', email: 'c@example.test', phone: null, roles: ['courier'], marketingConsent: false, twoFactorEnabled: false },
  admin: { id: 1, name: 'Админ', email: 'a@example.test', phone: null, roles: ['admin'], marketingConsent: false, twoFactorEnabled: true },
};

export function signedIn(role: keyof typeof users, path: string) {
  window.sessionStorage.setItem('zp_staff_token', 'tok');
  window.history.pushState({}, '', path);
  return users[role];
}
