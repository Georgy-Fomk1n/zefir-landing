import { deliveryStatusLabels, fulfilmentLabels, orderStatusLabels, type DeliveryStatus, type FulfilmentType, type Order, type OrderStatus } from '@zefir/shared';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { NavLink, Route, Routes } from 'react-router-dom';
import { api } from '../lib/api';
import { errorText, rub, Section } from '../lib/ui';

type StaffOrder = Order & {
  contact: { name: string; phone: string; email: string | null };
  address: { text?: string } | null;
  comment: string | null;
  courier: { id: number; name: string } | null;
  zone: { id: number; name: string } | null;
};

const today = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Moscow' }).format(new Date());

/** Следующие шаги для продавца по текущему статусу и способу получения */
function nextSteps(o: StaffOrder): OrderStatus[] {
  switch (o.status) {
    case 'paid': return ['accepted'];
    case 'accepted': return ['preparing'];
    case 'preparing': return ['ready'];
    case 'ready':
      if (o.fulfilment === 'pickup') return ['completed'];
      if (o.fulfilment === 'cdek' || o.fulfilment === 'russian_post') return ['shipped'];
      return [];
    default: return [];
  }
}

function Queue() {
  const [date, setDate] = useState(today);
  const qc = useQueryClient();
  const queue = useQuery({
    queryKey: ['queue', date],
    queryFn: () => api.get<{ data: StaffOrder[] }>(`/staff/seller/queue?date=${date}`).then((r) => r.data),
    refetchInterval: 30_000,
  });
  const move = useMutation({
    mutationFn: ({ id, status }: { id: number; status: OrderStatus }) => api.post(`/staff/seller/orders/${id}/status`, { status }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['queue'] }),
  });
  const cancel = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/staff/seller/orders/${id}/cancel`, { reason }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['queue'] }),
  });

  const groups = new Map<string, StaffOrder[]>();
  for (const o of queue.data ?? []) {
    const key = o.slot ? `${o.slot.from}–${o.slot.to}` : 'Без времени';
    groups.set(key, [...(groups.get(key) ?? []), o]);
  }

  return (
    <Section title="Очередь заказов ко времени" actions={<input type="date" value={date} onChange={(e) => setDate(e.target.value)} />}>
      {queue.isLoading && <p className="muted">Загрузка…</p>}
      {queue.data?.length === 0 && <p className="muted">На эту дату заказов нет.</p>}
      {(move.error || cancel.error) ? <p className="error">{errorText(move.error ?? cancel.error)}</p> : null}
      {[...groups.entries()].map(([slot, orders]) => (
        <div key={slot} className="slot">
          <h3>{slot} <span className="muted">· {orders.length}</span></h3>
          {orders.map((o) => (
            <article key={o.id} className={`order order--${o.status}`}>
              <header>
                <strong>№ {o.number}</strong>
                <span className="badge">{orderStatusLabels[o.status]}</span>
                <span className="muted">{fulfilmentLabels[o.fulfilment as FulfilmentType]}</span>
              </header>
              <ul>{o.items.map((i) => <li key={i.variantId}>{i.productName} · {i.variantName} × {i.qty}</li>)}</ul>
              <p className="muted">{o.contact.name}, <a href={`tel:${o.contact.phone}`}>{o.contact.phone}</a>{o.comment ? ` · «${o.comment}»` : ''}</p>
              {o.delivery && <p>Доставка: {deliveryStatusLabels[o.delivery.status as DeliveryStatus]}{o.courier ? ` · ${o.courier.name}` : ''}</p>}
              <div className="row">
                {nextSteps(o).map((s) => (
                  <button key={s} type="button" className="btn btn-sm" disabled={move.isPending} onClick={() => move.mutate({ id: o.id, status: s })}>
                    → {orderStatusLabels[s]}
                  </button>
                ))}
                {!['completed', 'cancelled'].includes(o.status) && (
                  <button type="button" className="btn btn-sm btn-ghost" onClick={() => {
                    const reason = window.prompt('Причина отмены');
                    if (reason) cancel.mutate({ id: o.id, reason });
                  }}>Отменить</button>
                )}
              </div>
            </article>
          ))}
        </div>
      ))}
    </Section>
  );
}

/** Камера через BarcodeDetector (Chrome/Android); на других устройствах — ввод кода вручную. */
function QrInput({ onToken }: { onToken: (token: string) => void }) {
  const video = useRef<HTMLVideoElement>(null);
  const [active, setActive] = useState(false);
  const supported = typeof window !== 'undefined' && 'BarcodeDetector' in window;

  useEffect(() => {
    if (!active || !video.current) return;
    let stream: MediaStream | null = null;
    let raf = 0;
    const Detector = (window as unknown as { BarcodeDetector: new (o: { formats: string[] }) => { detect: (v: HTMLVideoElement) => Promise<{ rawValue: string }[]> } }).BarcodeDetector;
    const detector = new Detector({ formats: ['qr_code'] });
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then((s) => {
      stream = s;
      video.current!.srcObject = s;
      void video.current!.play();
      const tick = async () => {
        const codes = await detector.detect(video.current!).catch(() => []);
        if (codes[0]) {
          onToken(codes[0].rawValue);
          setActive(false);
          return;
        }
        raf = requestAnimationFrame(tick);
      };
      raf = requestAnimationFrame(tick);
    }).catch(() => setActive(false));
    return () => {
      cancelAnimationFrame(raf);
      stream?.getTracks().forEach((t) => t.stop());
    };
  }, [active, onToken]);

  return (
    <div className="stack">
      {supported && <button type="button" className="btn" onClick={() => setActive(!active)}>{active ? 'Остановить камеру' : 'Сканировать камерой'}</button>}
      {active && <video ref={video} className="scanner" muted playsInline />}
      <form className="row" onSubmit={(e) => {
        e.preventDefault();
        const value = String(new FormData(e.currentTarget).get('token') ?? '').trim();
        if (value) onToken(value);
      }}>
        <input name="token" placeholder="Код из QR (вставить)" aria-label="Код из QR" />
        <button type="submit" className="btn btn-sm">Проверить</button>
      </form>
    </div>
  );
}

function Scan() {
  const [mode, setMode] = useState<'loyalty' | 'subscription'>('loyalty');
  const [token, setToken] = useState<string | null>(null);
  const [customer, setCustomer] = useState<{ name: string; balance: number } | null>(null);
  const [result, setResult] = useState<string | null>(null);
  // Идентификатор операции создаётся при открытии формы: двойное нажатие «Провести» не проведёт покупку дважды
  const [operationId, setOperationId] = useState('');
  const check = useMutation({
    mutationFn: (t: string) => api.post<{ data: { name: string; balance: number } }>('/staff/seller/scan/loyalty', { token: t }),
    onSuccess: (r) => {
      setCustomer(r.data);
      setOperationId(crypto.randomUUID());
    },
  });
  const pos = useMutation({
    mutationFn: (b: { purchase: number; spend: number }) => api.post<{ data: { earned: number; spent: number; balance: number; duplicate: boolean } }>('/staff/seller/scan/loyalty', {
      token, ...b, operationId,
    }),
    onSuccess: (r) => {
      setResult(r.data.duplicate ? 'Эта операция уже проведена' : `Начислено ${r.data.earned}, списано ${r.data.spent}. Баланс: ${r.data.balance}`);
      setCustomer(null);
      setToken(null);
    },
  });
  const redeem = useMutation({
    mutationFn: (t: string) => api.post<{ data: { plan: string; usedToday: number; dailyLimit: number; customer: string } }>('/staff/seller/scan/subscription', { token: t }),
    onSuccess: (r) => setResult(`${r.data.customer}: «${r.data.plan}» погашен, сегодня ${r.data.usedToday} из ${r.data.dailyLimit}`),
  });

  function onToken(t: string) {
    setResult(null);
    setToken(t);
    if (mode === 'loyalty') check.mutate(t);
    else redeem.mutate(t);
  }

  const error = check.error ?? pos.error ?? redeem.error;
  return (
    <Section title="Сканирование QR">
      <div className="tabs">
        <button type="button" aria-pressed={mode === 'loyalty'} onClick={() => setMode('loyalty')}>Карта лояльности</button>
        <button type="button" aria-pressed={mode === 'subscription'} onClick={() => setMode('subscription')}>Абонемент</button>
      </div>
      <QrInput onToken={onToken} />
      {error ? <p className="error">{errorText(error)}</p> : null}
      {result && <p className="ok">{result}</p>}
      {mode === 'loyalty' && customer && (
        <form className="stack" onSubmit={(e) => {
          e.preventDefault();
          const f = new FormData(e.currentTarget);
          pos.mutate({ purchase: Math.round(Number(f.get('purchase')) * 100), spend: Number(f.get('spend') || 0) });
        }}>
          <p><strong>{customer.name}</strong> · баланс {customer.balance} баллов</p>
          <label>Сумма покупки, ₽<input name="purchase" type="number" min={1} step="1" required /></label>
          <label>Списать баллов<input name="spend" type="number" min={0} max={customer.balance} defaultValue={0} /></label>
          <button type="submit" className="btn" disabled={pos.isPending}>Провести</button>
        </form>
      )}
    </Section>
  );
}

interface Board {
  orders: StaffOrder[];
  couriers: { id: number; name: string; onShift: boolean; active: number }[];
}

export function DeliveryBoard() {
  const [date, setDate] = useState(today);
  const qc = useQueryClient();
  const board = useQuery({
    queryKey: ['board', date],
    queryFn: () => api.get<{ data: Board }>(`/staff/seller/deliveries?date=${date}`).then((r) => r.data),
    refetchInterval: 30_000,
  });
  const assign = useMutation({
    mutationFn: ({ id, courierId, auto }: { id: number; courierId?: number; auto?: boolean }) => api.post(`/staff/seller/orders/${id}/assign`, { courierId, auto }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['board'] }),
  });
  const returned = useMutation({
    mutationFn: (id: number) => api.post(`/staff/seller/orders/${id}/returned`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['board'] }),
  });

  const byZone = new Map<string, StaffOrder[]>();
  for (const o of board.data?.orders ?? []) {
    const key = `${o.zone?.name ?? 'Без зоны'} · ${o.slot ? `${o.slot.from}–${o.slot.to}` : ''}`;
    byZone.set(key, [...(byZone.get(key) ?? []), o]);
  }

  return (
    <Section title="Доставки по зонам и интервалам" actions={<input type="date" value={date} onChange={(e) => setDate(e.target.value)} />}>
      <p className="muted">Курьеры: {(board.data?.couriers ?? []).map((c) => `${c.name}${c.onShift ? ' (на смене)' : ''} — ${c.active}`).join(' · ') || 'нет'}</p>
      {(assign.error || returned.error) ? <p className="error">{errorText(assign.error ?? returned.error)}</p> : null}
      {[...byZone.entries()].sort().map(([group, orders]) => (
        <div key={group} className="slot">
          <h3>{group}</h3>
          {orders.map((o) => (
            <article key={o.id} className="order">
              <header>
                <strong>№ {o.number}</strong>
                <span className="badge">{orderStatusLabels[o.status]}</span>
                {o.delivery && <span className="badge">{deliveryStatusLabels[o.delivery.status as DeliveryStatus]}</span>}
              </header>
              <p>{o.address?.text} · {rub(o.total)}</p>
              <div className="row">
                {(o.status === 'ready' || o.delivery?.status === 'assigned' || o.delivery?.status === 'accepted') && (
                  <>
                    <select defaultValue={o.courier?.id ?? ''} onChange={(e) => e.target.value && assign.mutate({ id: o.id, courierId: Number(e.target.value) })}>
                      <option value="">{o.courier ? `Курьер: ${o.courier.name}` : 'Назначить курьера'}</option>
                      {(board.data?.couriers ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}{c.onShift ? '' : ' (не на смене)'}</option>)}
                    </select>
                    {!o.courier && <button type="button" className="btn btn-sm btn-ghost" onClick={() => assign.mutate({ id: o.id, auto: true })}>Автоназначение</button>}
                  </>
                )}
                {o.delivery?.status === 'failed' && <button type="button" className="btn btn-sm" onClick={() => returned.mutate(o.id)}>Заказ вернулся в точку</button>}
              </div>
            </article>
          ))}
        </div>
      ))}
    </Section>
  );
}

export default function Seller() {
  return (
    <div className="layout">
      <nav className="sidenav">
        <NavLink to="/seller" end>Очередь</NavLink>
        <NavLink to="/seller/scan">Сканер QR</NavLink>
        <NavLink to="/seller/deliveries">Доставки</NavLink>
      </nav>
      <main className="content">
        <Routes>
          <Route index element={<Queue />} />
          <Route path="scan" element={<Scan />} />
          <Route path="deliveries" element={<DeliveryBoard />} />
        </Routes>
      </main>
    </div>
  );
}
