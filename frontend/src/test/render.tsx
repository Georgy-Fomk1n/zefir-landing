import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, type RenderOptions } from '@testing-library/react';
import type { ReactElement } from 'react';
import { HelmetProvider } from 'react-helmet-async';
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router-dom';
import { vi } from 'vitest';
import { CartProvider } from '../lib/cart';

/**
 * Рендер страницы/компонента в окружении приложения: роутер в памяти, React Query без ретраев,
 * корзина и HelmetProvider (нужен для <Head> из vite-react-ssg).
 */
export function renderApp(ui: ReactElement, { path = '/', route = '/', routes = [] as RouteObject[], ...options }: { path?: string; route?: string; routes?: RouteObject[] } & RenderOptions = {}) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 }, mutations: { retry: false } } });
  const router = createMemoryRouter([
    { path, element: <CartProvider>{ui}</CartProvider> },
    ...routes,
    { path: '*', element: <p>другая страница</p> },
  ], { initialEntries: [route] });
  const helmetContext: { helmet?: unknown } = {};
  const utils = render(
    <HelmetProvider context={helmetContext}>
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>
    </HelmetProvider>,
    options,
  );
  return { ...utils, router, queryClient, helmetContext };
}

type Handler = (url: string, init: RequestInit) => unknown;

/** Подменяет fetch: обработчики по «METHOD /path»; неописанный запрос — 404. */
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

export function jsonResponse(status: number, body: unknown) {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}
