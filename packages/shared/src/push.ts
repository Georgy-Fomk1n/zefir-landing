// Web Push на клиенте: общий код для магазина и штата.
// iPhone/iPad: push работает только в Safari iOS 16.4+ и только для сайта, добавленного на экран «Домой»
// (запуск как веб-приложение, display: standalone). В обычной вкладке Safari PushManager недоступен.

export type PushSupport = 'supported' | 'ios-install' | 'ios-too-old' | 'denied' | 'unsupported';

interface Env {
  userAgent: string;
  maxTouchPoints: number;
  standalone: boolean;
  hasServiceWorker: boolean;
  hasPushManager: boolean;
  hasNotification: boolean;
  permission: NotificationPermission | 'unsupported';
}

export function readEnv(): Env {
  const nav = typeof navigator !== 'undefined' ? navigator : undefined;
  const win = typeof window !== 'undefined' ? window : undefined;
  return {
    userAgent: nav?.userAgent ?? '',
    maxTouchPoints: nav?.maxTouchPoints ?? 0,
    standalone: Boolean(win?.matchMedia?.('(display-mode: standalone)').matches || (nav as Navigator & { standalone?: boolean } | undefined)?.standalone),
    hasServiceWorker: Boolean(nav && 'serviceWorker' in nav),
    hasPushManager: Boolean(win && 'PushManager' in win),
    hasNotification: Boolean(win && 'Notification' in win),
    permission: win && 'Notification' in win ? Notification.permission : 'unsupported',
  };
}

/** iPhone, iPod или iPad (iPadOS представляется как Mac с сенсорным экраном) */
export function isIos(env: Pick<Env, 'userAgent' | 'maxTouchPoints'>): boolean {
  return /iPhone|iPad|iPod/.test(env.userAgent) || (/Macintosh/.test(env.userAgent) && env.maxTouchPoints > 1);
}

/** Версия iOS из User-Agent: «OS 17_2» → 17.2; null, если не iOS */
export function iosVersion(userAgent: string): number | null {
  const m = userAgent.match(/OS (\d+)[_.](\d+)/);
  return m && /iPhone|iPad|iPod/.test(userAgent) ? Number(`${m[1]}.${m[2]}`) : null;
}

export function pushSupport(env: Env = readEnv()): PushSupport {
  // На iPhone сначала версия и установка: во вкладке Safari разрешения нет вовсе, и подсказка «запрещено» сбила бы с толку
  if (isIos(env)) {
    const v = iosVersion(env.userAgent);
    if (v !== null && v < 16.4) return 'ios-too-old';
    if (!env.standalone) return 'ios-install';
  }
  if (env.permission === 'denied') return 'denied';
  return env.hasServiceWorker && env.hasPushManager && env.hasNotification ? 'supported' : 'unsupported';
}

export const pushSupportText: Record<Exclude<PushSupport, 'supported'>, string> = {
  'ios-install': 'Чтобы получать уведомления на iPhone, добавьте сайт на экран «Домой»: кнопка «Поделиться» → «На экран „Домой“», затем откройте его оттуда и включите уведомления.',
  'ios-too-old': 'Уведомления на iPhone работают начиная с iOS 16.4. Обновите систему или следите за статусом в личном кабинете.',
  denied: 'Уведомления запрещены в настройках браузера. Разрешите их для этого сайта и обновите страницу.',
  unsupported: 'Этот браузер не поддерживает push-уведомления.',
};

/** VAPID-ключ base64url → Uint8Array для PushManager.subscribe */
export function urlBase64ToUint8Array(base64: string): Uint8Array {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4);
  const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

export interface PushApi {
  publicKey(): Promise<string | null>;
  save(subscription: PushSubscriptionJSON): Promise<unknown>;
  remove(endpoint: string): Promise<unknown>;
}

/**
 * Включить уведомления: регистрация service worker, запрос разрешения (вызывать из обработчика нажатия —
 * Safari иначе не покажет запрос), подписка и сохранение на сервере.
 */
export async function enablePush(api: PushApi, swUrl = '/sw.js'): Promise<'enabled' | 'denied' | 'no-key'> {
  const key = await api.publicKey();
  if (!key) return 'no-key';
  const permission = await Notification.requestPermission();
  if (permission !== 'granted') return 'denied';
  const registration = await navigator.serviceWorker.register(swUrl);
  await navigator.serviceWorker.ready;
  const existing = await registration.pushManager.getSubscription();
  const subscription = existing ?? await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(key) as BufferSource,
  });
  await api.save(subscription.toJSON());
  return 'enabled';
}

export async function disablePush(api: PushApi): Promise<void> {
  const registration = await navigator.serviceWorker.getRegistration();
  const subscription = await registration?.pushManager.getSubscription();
  if (subscription) {
    await api.remove(subscription.endpoint);
    await subscription.unsubscribe();
  }
}

export async function currentPushEndpoint(): Promise<string | null> {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return null;
  const registration = await navigator.serviceWorker.getRegistration();
  return (await registration?.pushManager.getSubscription())?.endpoint ?? null;
}

/** Данные push-сообщения (совпадает с toPush() на бэкенде) */
export interface PushMessage {
  title: string;
  body: string;
  url?: string;
  tag?: string;
}

/** Разбор полезной нагрузки в service worker: битые данные не должны ронять показ уведомления */
export function parsePushPayload(text: string | null | undefined, fallbackUrl = '/'): Required<PushMessage> {
  let data: Partial<PushMessage> = {};
  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    data = { body: text ?? '' };
  }
  return {
    title: data.title || 'Зефир и пряник',
    body: data.body || '',
    url: data.url || fallbackUrl,
    tag: data.tag || 'zefir',
  };
}
