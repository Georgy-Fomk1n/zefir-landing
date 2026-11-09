import { createApiClient, storageTokenStore } from '@zefir/shared';
import { config } from '../config';

// Токен покупателя в localStorage основного домена. Компромисс: токен доступен JS страницы, поэтому
// защита от XSS — строгий CSP в .htaccess и отсутствие стороннего JS на страницах кабинета, кроме
// отложенной Метрики. Срок жизни токена ограничен на сервере (SANCTUM_CUSTOMER_TTL), при выходе токен
// отзывается запросом POST /api/auth/logout. Подробнее: docs/security.md.
export const tokens = storageTokenStore('zp_token', () => (typeof window === 'undefined' ? undefined : window.localStorage));

export const api = createApiClient({ baseUrl: config.apiUrl, tokens });

/** Web Push покупателя: подписка хранится в области customer */
export const customerPushApi = {
  publicKey: () => api.get<{ publicKey: string | null }>('/push/key').then((r) => r.publicKey),
  save: (subscription: PushSubscriptionJSON) => api.post('/account/push', subscription),
  remove: (endpoint: string) => api.delete(`/account/push?endpoint=${encodeURIComponent(endpoint)}`),
};
