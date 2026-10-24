// Офлайн-очередь действий курьера. При плохой связи смена статуса сохраняется локально
// (со временем и геометкой на момент нажатия) и отправляется, когда сеть вернётся.
// Сервер идемпотентен по clientActionId, поэтому повторная отправка безопасна.

export interface QueuedAction {
  clientActionId: string;
  deliveryId: number;
  status: string;
  reason?: string;
  happenedAt: string;
  lat?: number;
  lng?: number;
  codMethod?: 'cash' | 'card';
  codAmount?: number;
  /** Фото в data URL (только для «доставлена»); при синхронизации превращается в файл */
  photo?: string;
}

const KEY = 'zp_courier_queue';

export function readQueue(): QueuedAction[] {
  try {
    return JSON.parse(window.localStorage.getItem(KEY) ?? '[]') as QueuedAction[];
  } catch {
    return [];
  }
}

function writeQueue(q: QueuedAction[]) {
  try {
    window.localStorage.setItem(KEY, JSON.stringify(q));
  } catch {
    // переполнение хранилища: фото не помещается — отправим без него
    window.localStorage.setItem(KEY, JSON.stringify(q.map(({ photo: _p, ...rest }) => rest)));
  }
}

export function enqueue(action: QueuedAction) {
  writeQueue([...readQueue(), action]);
}

export function newActionId(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function dataUrlToBlob(dataUrl: string): Blob {
  const [head, body] = dataUrl.split(',');
  const mime = head.match(/:(.*?);/)?.[1] ?? 'image/jpeg';
  const bin = atob(body);
  const arr = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
  return new Blob([arr], { type: mime });
}

export function toFormData(a: QueuedAction): FormData {
  const f = new FormData();
  f.set('status', a.status);
  f.set('clientActionId', a.clientActionId);
  f.set('happenedAt', a.happenedAt);
  if (a.reason) f.set('reason', a.reason);
  if (a.lat !== undefined) f.set('lat', String(a.lat));
  if (a.lng !== undefined) f.set('lng', String(a.lng));
  if (a.codMethod) f.set('codMethod', a.codMethod);
  if (a.codAmount !== undefined) f.set('codAmount', String(a.codAmount));
  if (a.photo) f.set('photo', dataUrlToBlob(a.photo), 'proof.jpg');
  return f;
}

/**
 * Отправить очередь по порядку. Сетевые ошибки оставляют действие в очереди;
 * ответ сервера 4xx (например, недопустимый переход) убирает его и возвращается в errors.
 */
export async function flushQueue(send: (a: QueuedAction) => Promise<void>): Promise<{ sent: number; errors: string[] }> {
  let queue = readQueue();
  let sent = 0;
  const errors: string[] = [];
  while (queue.length > 0) {
    const action = queue[0];
    try {
      await send(action);
      sent++;
    } catch (e) {
      const status = (e as { status?: number }).status;
      if (status === undefined || status >= 500 || status === 401) break;
      errors.push((e as Error).message);
    }
    queue = queue.slice(1);
    writeQueue(queue);
  }
  return { sent, errors };
}

/** Геометка на момент действия; без разрешения или по таймауту — без координат. */
export function currentPosition(timeoutMs = 4000): Promise<{ lat: number; lng: number } | null> {
  return new Promise((resolve) => {
    if (!('geolocation' in navigator)) return resolve(null);
    navigator.geolocation.getCurrentPosition(
      (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 60_000 },
    );
  });
}
