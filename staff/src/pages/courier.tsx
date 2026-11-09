import { deliveryStatusLabels, type DeliveryStatus } from '@zefir/shared';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useState } from 'react';
import { PushToggle } from '@zefir/shared/ui';
import { api, staffPushApi } from '../lib/api';
import { currentPosition, enqueue, flushQueue, newActionId, readQueue, toFormData, type QueuedAction } from '../lib/offlineQueue';
import { rub } from '../lib/ui';

interface CourierDelivery {
  id: number;
  orderNumber: string;
  status: DeliveryStatus;
  routePosition: number | null;
  clientName: string | null;
  phone: string | null;
  address: string | null;
  entrance: string | null;
  floor: string | null;
  intercom: string | null;
  lat: number | null;
  lng: number | null;
  comment: string | null;
  interval: { date: string; from: string; to: string } | null;
  total: number;
  codAmount: number;
  paid: boolean;
  failReason: string | null;
}

interface Today {
  deliveries: CourierDelivery[];
  shift: { id: number; startedAt: string; cash: number; card: number } | null;
  cashOnDelivery: boolean;
}

const NEXT: Partial<Record<DeliveryStatus, { to: DeliveryStatus; label: string }>> = {
  assigned: { to: 'accepted', label: 'Принять' },
  accepted: { to: 'picked_up', label: 'Забрал заказ' },
  picked_up: { to: 'in_transit', label: 'В пути' },
  in_transit: { to: 'delivered', label: 'Доставлено' },
  failed: { to: 'in_transit', label: 'Повторить доставку' },
};

function mapsUrl(d: CourierDelivery) {
  return d.lat && d.lng
    ? `https://yandex.ru/maps/?rtext=~${d.lat},${d.lng}&rtt=auto`
    : `https://yandex.ru/maps/?text=${encodeURIComponent(d.address ?? '')}`;
}

function navigatorUrl(d: CourierDelivery) {
  return d.lat && d.lng ? `yandexnavi://build_route_on_map?lat_to=${d.lat}&lon_to=${d.lng}` : mapsUrl(d);
}

function telegramUrl(phone: string) {
  return `https://t.me/${phone.replace(/[^\d+]/g, '')}`;
}

async function readPhoto(file: File): Promise<string> {
  // Сжимаем до 1280 px, чтобы фото поместилось в офлайн-очередь
  const bitmap = await createImageBitmap(file);
  const scale = Math.min(1, 1280 / Math.max(bitmap.width, bitmap.height));
  const canvas = document.createElement('canvas');
  canvas.width = Math.round(bitmap.width * scale);
  canvas.height = Math.round(bitmap.height * scale);
  canvas.getContext('2d')!.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
  return canvas.toDataURL('image/jpeg', 0.75);
}

export default function Courier() {
  const qc = useQueryClient();
  const [online, setOnline] = useState(() => navigator.onLine);
  const [pending, setPending] = useState(() => readQueue().length);
  const [messages, setMessages] = useState<string[]>([]);
  const [open, setOpen] = useState<number | null>(null);
  const [summary, setSummary] = useState<Record<string, number | string> | null>(null);

  const today = useQuery({
    queryKey: ['courier-today'],
    queryFn: () => api.get<{ data: Today }>('/staff/courier/today').then((r) => r.data),
    refetchInterval: online ? 60_000 : false,
    networkMode: 'offlineFirst',
  });

  const sync = useCallback(async () => {
    const res = await flushQueue(async (a: QueuedAction) => {
      await api.post(`/staff/courier/deliveries/${a.deliveryId}/status`, toFormData(a));
    });
    setPending(readQueue().length);
    if (res.errors.length) setMessages((m) => [...m, ...res.errors]);
    if (res.sent) await qc.invalidateQueries({ queryKey: ['courier-today'] });
  }, [qc]);

  useEffect(() => {
    const on = () => { setOnline(true); void sync(); };
    const off = () => setOnline(false);
    window.addEventListener('online', on);
    window.addEventListener('offline', off);
    if (navigator.onLine) void sync();
    return () => {
      window.removeEventListener('online', on);
      window.removeEventListener('offline', off);
    };
  }, [sync]);

  async function act(d: CourierDelivery, to: DeliveryStatus, extra: Partial<QueuedAction> = {}) {
    const pos = await currentPosition();
    enqueue({ clientActionId: newActionId(), deliveryId: d.id, status: to, happenedAt: new Date().toISOString(), ...(pos ?? {}), ...extra });
    setPending(readQueue().length);
    // Оптимистично показываем новый статус, пока действие в очереди
    qc.setQueryData<Today>(['courier-today'], (old) => old && { ...old, deliveries: old.deliveries.map((x) => (x.id === d.id ? { ...x, status: to } : x)) });
    if (navigator.onLine) await sync();
  }

  async function shift(start: boolean) {
    const res = await api.post<{ data: Record<string, number | string> }>(start ? '/staff/courier/shift/start' : '/staff/courier/shift/end');
    if (!start) setSummary(res.data);
    await qc.invalidateQueries({ queryKey: ['courier-today'] });
  }

  const data = today.data;
  const active = data?.deliveries.filter((d) => !['delivered', 'returned'].includes(d.status)) ?? [];
  const done = data?.deliveries.filter((d) => ['delivered', 'returned'].includes(d.status)) ?? [];

  return (
    <main className="courier">
      <div className={`net ${online ? 'net--on' : 'net--off'}`}>
        {online ? 'В сети' : 'Нет сети — действия сохранятся и отправятся позже'}
        {pending > 0 && ` · в очереди: ${pending}`}
      </div>
      {messages.map((m, i) => <p key={i} className="error">{m}</p>)}

      <div className="shift">
        {data?.shift ? (
          <>
            <span>Смена с {new Date(data.shift.startedAt).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}{data.cashOnDelivery ? ` · наличные ${rub(data.shift.cash)} · карта ${rub(data.shift.card)}` : ''}</span>
            <button type="button" className="btn btn-sm btn-ghost" onClick={() => shift(false)}>Закончить смену</button>
          </>
        ) : <button type="button" className="btn" onClick={() => shift(true)}>Начать смену</button>}
      </div>
      {summary && <p className="ok">Итог смены: доставлено {summary.delivered}, не вручено {summary.failed}{data?.cashOnDelivery ? `, наличные ${rub(Number(summary.cash))}, карта ${rub(Number(summary.card))}` : ''}</p>}

      <PushToggle className="push" api={staffPushApi} description="Уведомления о новых доставках." />

      <h1>На сегодня · {active.length}</h1>
      {today.isLoading && <p className="muted">Загрузка…</p>}
      <ol className="stops">
        {active.map((d, i) => (
          <li key={d.id} className={`stop stop--${d.status}`}>
            <button type="button" className="stop__head" onClick={() => setOpen(open === d.id ? null : d.id)} aria-expanded={open === d.id}>
              <span className="stop__n">{i + 1}</span>
              <span>
                <strong>{d.address}</strong>
                <span className="muted">{d.interval ? `${d.interval.from}–${d.interval.to}` : ''} · № {d.orderNumber} · {deliveryStatusLabels[d.status]}</span>
              </span>
            </button>
            {open === d.id && <Stop d={d} cod={data?.cashOnDelivery ?? false} onAct={act} />}
          </li>
        ))}
      </ol>
      {done.length > 0 && (
        <details>
          <summary>Завершённые · {done.length}</summary>
          <ul className="list">{done.map((d) => <li key={d.id}>№ {d.orderNumber} — {deliveryStatusLabels[d.status]}</li>)}</ul>
        </details>
      )}
    </main>
  );
}

function Stop({ d, cod, onAct }: { d: CourierDelivery; cod: boolean; onAct: (d: CourierDelivery, to: DeliveryStatus, extra?: Partial<QueuedAction>) => Promise<void> }) {
  const [busy, setBusy] = useState(false);
  const [photo, setPhoto] = useState<string | undefined>();
  const [codMethod, setCodMethod] = useState<'cash' | 'card'>('cash');
  const next = NEXT[d.status];
  const run = async (to: DeliveryStatus, extra: Partial<QueuedAction> = {}) => {
    setBusy(true);
    try {
      await onAct(d, to, extra);
    } finally {
      setBusy(false);
    }
  };
  return (
    <div className="stop__body">
      <dl>
        {d.clientName && (<><dt>Клиент</dt><dd>{d.clientName}</dd></>)}
        {(d.entrance || d.floor || d.intercom) && (<><dt>Подъезд / этаж / домофон</dt><dd>{[d.entrance, d.floor, d.intercom].map((x) => x || '—').join(' / ')}</dd></>)}
        {d.comment && (<><dt>Комментарий</dt><dd>{d.comment}</dd></>)}
        <dt>Оплата</dt><dd>{d.paid ? `Оплачен онлайн (${rub(d.total)})` : `К оплате при получении: ${rub(d.codAmount)}`}</dd>
        {d.failReason && (<><dt>Не вручено</dt><dd>{d.failReason}</dd></>)}
      </dl>
      <div className="actions">
        {d.phone && <a className="btn btn-ghost" href={`tel:${d.phone}`}>Позвонить</a>}
        <a className="btn btn-ghost" href={mapsUrl(d)} target="_blank" rel="noopener">Яндекс Карты</a>
        <a className="btn btn-ghost" href={navigatorUrl(d)}>Навигатор</a>
        {d.phone && <a className="btn btn-ghost" href={telegramUrl(d.phone)} target="_blank" rel="noopener">Telegram</a>}
      </div>
      {d.status === 'in_transit' && (
        <label className="photo">
          Фото вручения
          <input type="file" accept="image/*" capture="environment" onChange={async (e) => {
            const f = e.target.files?.[0];
            if (f) setPhoto(await readPhoto(f));
          }} />
          {photo && <img src={photo} alt="Фото вручения" width={120} height={90} />}
        </label>
      )}
      {d.status === 'in_transit' && !d.paid && cod && (
        <fieldset className="row">
          <legend>Получена оплата {rub(d.codAmount)}</legend>
          <label><input type="radio" checked={codMethod === 'cash'} onChange={() => setCodMethod('cash')} /> наличные</label>
          <label><input type="radio" checked={codMethod === 'card'} onChange={() => setCodMethod('card')} /> карта</label>
        </fieldset>
      )}
      <div className="actions">
        {next && (
          <button type="button" className="btn btn-big" disabled={busy || (next.to === 'delivered' && !d.paid && !cod)}
            onClick={() => run(next.to, next.to === 'delivered' ? { photo, ...(!d.paid ? { codMethod, codAmount: d.codAmount } : {}) } : {})}>
            {next.label}
          </button>
        )}
        {(d.status === 'picked_up' || d.status === 'in_transit') && (
          <button type="button" className="btn btn-ghost" disabled={busy} onClick={() => {
            const reason = window.prompt('Почему не удалось вручить? (обязательно)');
            if (reason?.trim()) void run('failed', { reason: reason.trim() });
          }}>Не удалось вручить</button>
        )}
      </div>
    </div>
  );
}
