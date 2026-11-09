import { API } from '../env';

/** Прямые вызовы API для подготовки данных (заказы, оплата) — быстрее, чем проходить UI в каждом тесте. */
export async function api<T = unknown>(method: string, path: string, body?: unknown, token?: string): Promise<T> {
  const res = await fetch(`${API}/api${path}`, {
    method,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = res.status === 204 ? undefined : await res.json();
  if (!res.ok) throw new Error(`${method} ${path} → ${res.status}: ${JSON.stringify(data)}`);
  return data as T;
}

interface Catalog { products: { slug: string; variants: { id: number }[] }[] }
interface Slot { id: number; available: number; date: string }

export async function variantId(slug: string): Promise<number> {
  const catalog = await api<Catalog>('GET', '/catalog/export');
  return catalog.products.find((p) => p.slug === slug)!.variants[0].id;
}

/** Заказ на имя покупателя; для курьера — адрес внутри демо-зоны (около ул. Базовская). */
export async function placeOrder(fulfilment: 'pickup' | 'courier', name = 'Е2Е Покупатель', token?: string) {
  const slots = await api<{ data: Slot[] }>('GET', `/slots?type=${fulfilment}`);
  const slot = slots.data.find((s) => s.available > 0)!;
  const res = await api<{ data: { id: number; number: string }; payment: { url: string } }>('POST', '/orders', {
    lines: [{ variantId: await variantId('nabor-4'), qty: 2 }],
    name, phone: '+79990001122', fulfilment, slotId: slot.id, consent: true,
    address: fulfilment === 'courier' ? { text: 'Москва, ул. Базовская, 15', lat: 55.881, lng: 37.512, entrance: '2', floor: '5', intercom: '25К' } : null,
  }, token);
  return { id: res.data.id, number: res.data.number, paymentUrl: res.payment.url, slotDate: slot.date };
}

/** «Оплатить» на тестовой странице оплаты (подписанная ссылка) */
export async function pay(paymentUrl: string) {
  const res = await fetch(`${paymentUrl}&action=pay`, { redirect: 'manual' });
  if (res.status !== 302) throw new Error(`Оплата не прошла: ${res.status}`);
}

export async function staffToken(email: string): Promise<string> {
  return (await api<{ token: string }>('POST', '/staff/auth/login', { email, password: 'password-123' })).token;
}

export async function advance(orderId: number, statuses: string[], token: string) {
  for (const status of statuses) await api('POST', `/staff/seller/orders/${orderId}/status`, { status }, token);
}

/** Новый покупатель через API: токен кладём в localStorage, чтобы не проходить форму в каждом тесте. */
export async function customerToken(name = 'Е2Е Покупатель'): Promise<string> {
  const email = `e2e-${Date.now()}-${Math.round(Math.random() * 1e6)}@example.test`;
  return (await api<{ token: string }>('POST', '/auth/register', { name, email, password: 'password-123', consent: true })).token;
}
