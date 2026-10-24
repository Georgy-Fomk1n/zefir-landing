import { ApiError, deliveryStatusLabels, formatPrice, fulfilmentLabels, orderStatusLabels, subscriptionStatusLabels,
  type LoyaltySummary, type Order, type Product, type Subscription, type SubscriptionPlan } from '@zefir/shared';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import QRCode from 'qrcode';
import { useEffect, useState, type FormEvent } from 'react';
import { Link, NavLink, Route, Routes, useNavigate, useParams } from 'react-router-dom';
import { pages } from '../../content/pages';
import { productBySlug } from '../../data/catalog';
import { PushToggle } from '@zefir/shared/ui';
import { api, customerPushApi } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { reachGoal } from '../../lib/metrika';
import { Seo } from '../../seo/Seo';
import { ProductCard } from '../../components/common';

// Личный кабинет покупателя: отдельный чанк (code splitting), не индексируется, работает только в браузере.

function formError(e: unknown): string | null {
  if (!e) return null;
  if (e instanceof ApiError) return Object.values(e.errors)[0]?.[0] ?? e.message;
  return 'Не удалось выполнить запрос. Проверьте соединение.';
}

function AuthForms() {
  const { login, register } = useAuth();
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const f = new FormData(e.currentTarget);
    setBusy(true);
    setError(null);
    try {
      if (mode === 'login') await login(String(f.get('email')), String(f.get('password')));
      else await register({
        name: String(f.get('name')), email: String(f.get('email')), phone: String(f.get('phone') || ''),
        password: String(f.get('password')), consent: f.get('consent') === 'on', marketingConsent: f.get('marketing') === 'on',
      });
    } catch (err) {
      setError(formError(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="auth">
      <div className="tabs" role="tablist">
        <button type="button" role="tab" aria-selected={mode === 'login'} onClick={() => setMode('login')}>Вход</button>
        <button type="button" role="tab" aria-selected={mode === 'register'} onClick={() => setMode('register')}>Регистрация</button>
      </div>
      <form onSubmit={onSubmit} className="form">
        {mode === 'register' && <label>Имя<input name="name" required autoComplete="name" /></label>}
        <label>Email<input name="email" type="email" required autoComplete="email" /></label>
        {mode === 'register' && <label>Телефон<input name="phone" type="tel" autoComplete="tel" /></label>}
        <label>Пароль<input name="password" type="password" required minLength={8} autoComplete={mode === 'login' ? 'current-password' : 'new-password'} /></label>
        {mode === 'register' && (
          <>
            <label className="checkbox"><input type="checkbox" name="consent" required /> Согласен с <Link to={pages.privacy.path}>политикой обработки персональных данных</Link></label>
            <label className="checkbox"><input type="checkbox" name="marketing" /> Получать новости и акции</label>
          </>
        )}
        {error && <p className="error">{error}</p>}
        <button type="submit" className="btn btn-primary" disabled={busy}>{mode === 'login' ? 'Войти' : 'Зарегистрироваться'}</button>
      </form>
    </div>
  );
}

function Qr({ value, label }: { value: string; label: string }) {
  const [svg, setSvg] = useState('');
  useEffect(() => {
    QRCode.toString(value, { type: 'svg', margin: 1, width: 240 }).then(setSvg).catch(() => setSvg(''));
  }, [value]);
  return <div className="qr" role="img" aria-label={label} dangerouslySetInnerHTML={{ __html: svg }} />;
}

function Orders() {
  const orders = useQuery({ queryKey: ['orders'], queryFn: () => api.get<{ data: Order[] }>('/account/orders').then((r) => r.data) });
  if (orders.isLoading) return <p className="muted">Загрузка…</p>;
  if (!orders.data?.length) return <p>Заказов пока нет. <Link to="/catalog/">В каталог</Link></p>;
  return (
    <ul className="orders">
      {orders.data.map((o) => (
        <li key={o.id}>
          <Link to={`/account/orders/${o.id}`}><strong>№ {o.number}</strong></Link> · {new Date(o.createdAt).toLocaleDateString('ru-RU')} · {orderStatusLabels[o.status]} · {formatPrice(o.total)}
        </li>
      ))}
    </ul>
  );
}

function ReviewForm({ orderId, productId }: { orderId: number; productId: number }) {
  const [sent, setSent] = useState(false);
  const m = useMutation({
    mutationFn: (body: { productId: number; rating: number; text: string }) => api.post(`/account/orders/${orderId}/reviews`, body),
    onSuccess: () => setSent(true),
  });
  if (sent) return <p className="muted">Спасибо! Отзыв появится после модерации.</p>;
  return (
    <form className="form form--inline" onSubmit={(e) => {
      e.preventDefault();
      const f = new FormData(e.currentTarget);
      m.mutate({ productId, rating: Number(f.get('rating')), text: String(f.get('text')) });
    }}>
      <select name="rating" defaultValue="5" aria-label="Оценка">{[5, 4, 3, 2, 1].map((n) => <option key={n} value={n}>{'★'.repeat(n)}</option>)}</select>
      <input name="text" required minLength={10} placeholder="Ваш отзыв" aria-label="Текст отзыва" />
      <button type="submit" className="btn btn-secondary btn-sm" disabled={m.isPending}>Отправить</button>
      {m.error && <p className="error">{formError(m.error)}</p>}
    </form>
  );
}

function OrderDetails() {
  const { id } = useParams();
  const order = useQuery({
    queryKey: ['order', id],
    queryFn: () => api.get<{ data: Order }>(`/account/orders/${id}`).then((r) => r.data),
    refetchInterval: 30_000,
  });
  const o = order.data;
  if (!o) return <p className="muted">Загрузка…</p>;
  return (
    <div>
      <h2>Заказ № {o.number}</h2>
      <p>{orderStatusLabels[o.status]} · {fulfilmentLabels[o.fulfilment]} · {formatPrice(o.total)}</p>
      {o.slot && <p>Время: {o.slot.date}, {o.slot.from}–{o.slot.to}</p>}
      {o.delivery && <p>Доставка: {deliveryStatusLabels[o.delivery.status]}{o.delivery.address ? ` · ${o.delivery.address}` : ''}</p>}
      <ul>
        {o.items.map((i) => (
          <li key={i.variantId}>
            {i.productName} ({i.variantName}) × {i.qty} — {formatPrice(i.price * i.qty)}
            {i.reviewable && <ReviewForm orderId={o.id} productId={i.productId} />}
          </li>
        ))}
      </ul>
      <h3>История</h3>
      <ol className="timeline">
        {[...o.history, ...(o.delivery?.history ?? [])].sort((a, b) => a.at.localeCompare(b.at)).map((h, i) => (
          <li key={i}>{new Date(h.at).toLocaleString('ru-RU')} — {orderStatusLabels[h.to as keyof typeof orderStatusLabels] ?? deliveryStatusLabels[h.to as keyof typeof deliveryStatusLabels] ?? h.to}{h.comment ? ` (${h.comment})` : ''}</li>
        ))}
      </ol>
      {o.status === 'pending_payment' && o.trackingToken && <RetryPayment token={o.trackingToken} />}
      {o.trackingUrl && <p>Ссылка для отслеживания без входа: <a href={o.trackingUrl}>{o.trackingUrl}</a></p>}
    </div>
  );
}

function RetryPayment({ token }: { token: string }) {
  const pay = useMutation({
    mutationFn: () => api.post<{ payment: { url: string } }>(`/track/${encodeURIComponent(token)}/pay`),
    onSuccess: (r) => window.location.assign(r.payment.url),
  });
  return (
    <p>
      <button type="button" className="btn btn-primary btn-sm" disabled={pay.isPending} onClick={() => pay.mutate()}>Оплатить заказ</button>
      {pay.error && <span className="error"> {formError(pay.error)}</span>}
    </p>
  );
}

function Favorites() {
  const favs = useQuery({ queryKey: ['favorites'], queryFn: () => api.get<{ data: Pick<Product, 'slug'>[] }>('/account/favorites').then((r) => r.data) });
  const products = (favs.data ?? []).map((f) => productBySlug(f.slug)).filter((p): p is Product => Boolean(p));
  if (!products.length) return <p>В избранном пока пусто.</p>;
  return <div className="grid grid--3">{products.map((p) => <ProductCard key={p.slug} product={p} />)}</div>;
}

function Loyalty() {
  const loyalty = useQuery({
    queryKey: ['loyalty'],
    queryFn: () => api.get<{ data: LoyaltySummary }>('/account/loyalty').then((r) => r.data),
    // QR-токен короткоживущий: обновляем заранее
    refetchInterval: 4 * 60_000,
  });
  const l = loyalty.data;
  if (!l) return <p className="muted">Загрузка…</p>;
  return (
    <div>
      <p className="price price--lg">{l.balance} баллов</p>
      <p className="muted">1 балл = 1 ₽. Баллами можно оплатить до {Math.round(l.maxSpendShare * 100)}% заказа.</p>
      <Qr value={l.qrToken} label="QR-код карты лояльности для продавца" />
      <p className="muted">Покажите QR продавцу при покупке в точке.</p>
      <h3>Операции</h3>
      <ul>
        {l.transactions.map((t) => (
          <li key={t.id}>{new Date(t.createdAt).toLocaleDateString('ru-RU')} · {t.amount > 0 ? '+' : ''}{t.amount} · {t.comment ?? t.type}</li>
        ))}
      </ul>
    </div>
  );
}

function Subscriptions() {
  const qc = useQueryClient();
  const plans = useQuery({ queryKey: ['plans'], queryFn: () => api.get<{ data: SubscriptionPlan[] }>('/subscription-plans').then((r) => r.data) });
  const subs = useQuery({ queryKey: ['subscriptions'], queryFn: () => api.get<{ data: Subscription[] }>('/account/subscriptions').then((r) => r.data) });
  const buy = useMutation({
    mutationFn: (planId: number) => api.post<{ payment: { url: string } }>('/account/subscriptions', { planId }),
    onSuccess: (r) => { reachGoal('subscription_purchase'); window.location.assign(r.payment.url); },
  });
  const cancel = useMutation({
    mutationFn: (id: number) => api.post(`/account/subscriptions/${id}/cancel`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['subscriptions'] }),
  });
  return (
    <div>
      {(subs.data ?? []).map((s) => (
        <div key={s.id} className="panel">
          <h3>{s.plan.name}</h3>
          <p>{subscriptionStatusLabels[s.status]}{s.currentPeriodEnd ? ` · до ${new Date(s.currentPeriodEnd).toLocaleDateString('ru-RU')}` : ''}</p>
          <p className="muted">Сегодня использовано {s.usedToday} из {s.plan.dailyLimit}. {s.renewalMode === 'link' ? 'Продление по ссылке из напоминания.' : s.autoRenew ? 'Автопродление включено.' : 'Автопродление выключено.'}</p>
          {s.qrToken && s.status === 'active' && <Qr value={s.qrToken} label="QR абонемента" />}
          {['active', 'grace', 'past_due'].includes(s.status) && s.autoRenew && (
            <button type="button" className="btn btn-secondary btn-sm" onClick={() => cancel.mutate(s.id)}>Отключить автопродление</button>
          )}
        </div>
      ))}
      <h3>Тарифы</h3>
      {(plans.data ?? []).length === 0 && <p className="muted">Абонементы скоро появятся.</p>}
      <div className="grid grid--2">
        {(plans.data ?? []).map((p) => (
          <div key={p.id} className="panel">
            <h4>{p.name}</h4>
            <p>{p.description}</p>
            <p className="price">{formatPrice(p.price)} / {p.periodDays} дн.</p>
            <button type="button" className="btn btn-primary btn-sm" disabled={buy.isPending} onClick={() => buy.mutate(p.id)}>Оформить</button>
          </div>
        ))}
      </div>
      {buy.error && <p className="error">{formError(buy.error)}</p>}
    </div>
  );
}

function Settings() {
  const { user, logout } = useAuth();
  const qc = useQueryClient();
  const navigate = useNavigate();
  const [msg, setMsg] = useState<string | null>(null);
  const methods = useQuery({ queryKey: ['payment-methods'], queryFn: () => api.get<{ data: { id: number; title: string }[] }>('/account/payment-methods').then((r) => r.data) });
  const save = useMutation({
    mutationFn: (body: Record<string, unknown>) => api.put('/account/profile', body),
    onSuccess: () => { setMsg('Сохранено'); qc.invalidateQueries({ queryKey: ['me'] }); },
  });
  const password = useMutation({ mutationFn: (body: Record<string, unknown>) => api.put('/account/password', body), onSuccess: () => setMsg('Пароль изменён') });
  const unlink = useMutation({ mutationFn: (id: number) => api.delete(`/account/payment-methods/${id}`), onSuccess: () => qc.invalidateQueries({ queryKey: ['payment-methods'] }) });
  const remove = useMutation({
    mutationFn: (pwd: string) => api.post('/account/delete', { password: pwd }),
    onSuccess: async () => { await logout().catch(() => undefined); navigate('/'); },
  });
  if (!user) return null;
  return (
    <div className="settings">
      <form className="form" onSubmit={(e) => {
        e.preventDefault();
        const f = new FormData(e.currentTarget);
        save.mutate({ name: f.get('name'), phone: f.get('phone'), marketingConsent: f.get('marketing') === 'on' });
      }}>
        <h3>Профиль</h3>
        <label>Имя<input name="name" defaultValue={user.name} required /></label>
        <label>Телефон<input name="phone" type="tel" defaultValue={user.phone ?? ''} /></label>
        <label className="checkbox"><input type="checkbox" name="marketing" defaultChecked={user.marketingConsent} /> Получать новости и акции</label>
        <button type="submit" className="btn btn-primary btn-sm">Сохранить</button>
        {save.error && <p className="error">{formError(save.error)}</p>}
      </form>
      <form className="form" onSubmit={(e) => {
        e.preventDefault();
        const f = new FormData(e.currentTarget);
        password.mutate({ current_password: f.get('current'), password: f.get('password'), password_confirmation: f.get('confirm') });
        e.currentTarget.reset();
      }}>
        <h3>Смена пароля</h3>
        <label>Текущий пароль<input name="current" type="password" required autoComplete="current-password" /></label>
        <label>Новый пароль<input name="password" type="password" required minLength={8} autoComplete="new-password" /></label>
        <label>Повторите<input name="confirm" type="password" required minLength={8} autoComplete="new-password" /></label>
        <button type="submit" className="btn btn-primary btn-sm">Изменить</button>
        {password.error && <p className="error">{formError(password.error)}</p>}
      </form>
      <PushToggle className="form" api={customerPushApi}
        description="Push-уведомления о статусе заказов: оплачен, готов, курьер в пути, доставлен." />
      <div>
        <h3>Сохранённые карты</h3>
        {(methods.data ?? []).length === 0 && <p className="muted">Нет сохранённых карт.</p>}
        <ul>{(methods.data ?? []).map((m) => <li key={m.id}>{m.title} <button type="button" className="link" onClick={() => unlink.mutate(m.id)}>Отвязать</button></li>)}</ul>
      </div>
      {msg && <p className="muted" role="status">{msg}</p>}
      <form className="form danger" onSubmit={(e) => {
        e.preventDefault();
        if (window.confirm('Удалить аккаунт? Персональные данные будут обезличены, баллы и абонементы сгорят.')) {
          remove.mutate(String(new FormData(e.currentTarget).get('password')));
        }
      }}>
        <h3>Удаление аккаунта</h3>
        <label>Пароль<input name="password" type="password" required autoComplete="current-password" /></label>
        <button type="submit" className="btn btn-secondary btn-sm">Удалить аккаунт</button>
        {remove.error && <p className="error">{formError(remove.error)}</p>}
      </form>
    </div>
  );
}

export default function Account() {
  const { user, loading, logout } = useAuth();
  return (
    <>
      <Seo title="Личный кабинет — Зефир и пряник" description="Личный кабинет покупателя" path="/account/" noindex />
      <div className="container page">
        <h1>Личный кабинет</h1>
        {loading ? <p className="muted">Загрузка…</p> : !user ? <AuthForms /> : (
          <div className="account">
            <nav className="account__nav" aria-label="Разделы кабинета">
              <NavLink to="/account/" end>Заказы</NavLink>
              <NavLink to="/account/favorites">Избранное</NavLink>
              <NavLink to="/account/loyalty">Карта лояльности</NavLink>
              <NavLink to="/account/subscriptions">Абонементы</NavLink>
              <NavLink to="/account/settings">Настройки</NavLink>
              <button type="button" className="link" onClick={() => logout()}>Выйти</button>
            </nav>
            <div className="account__body">
              <Routes>
                <Route index element={<Orders />} />
                <Route path="orders" element={<Orders />} />
                <Route path="orders/:id" element={<OrderDetails />} />
                <Route path="favorites" element={<Favorites />} />
                <Route path="loyalty" element={<Loyalty />} />
                <Route path="subscriptions" element={<Subscriptions />} />
                <Route path="settings" element={<Settings />} />
                <Route path="*" element={<Orders />} />
              </Routes>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
