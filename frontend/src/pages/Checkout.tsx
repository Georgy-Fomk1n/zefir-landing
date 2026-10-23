import { ApiError, formatPrice, fulfilmentLabels, type DeliveryQuote, type FulfilmentType, type Slot } from '@zefir/shared';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router-dom';
import { z } from 'zod';
import { pages } from '../content/pages';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useCart } from '../lib/cart';
import { reachGoal } from '../lib/metrika';
import { Seo } from '../seo/Seo';

const schema = z.object({
  name: z.string().trim().min(2, 'Укажите имя'),
  phone: z.string().trim().regex(/^\+?[78][\d\s()-]{9,}$/, 'Телефон в формате +7…'),
  email: z.string().trim().email('Проверьте email').or(z.literal('')),
  fulfilment: z.enum(['pickup', 'courier', 'cdek', 'russian_post']),
  slotId: z.string().optional(),
  address: z.string().optional(),
  entrance: z.string().optional(),
  floor: z.string().optional(),
  intercom: z.string().optional(),
  comment: z.string().max(1000).optional(),
  promoCode: z.string().optional(),
  points: z.coerce.number().int().min(0).optional(),
  consent: z.literal(true, { message: 'Нужно согласие на обработку персональных данных' }),
}).superRefine((v, ctx) => {
  if ((v.fulfilment === 'pickup' || v.fulfilment === 'courier') && !v.slotId) {
    ctx.addIssue({ code: 'custom', path: ['slotId'], message: 'Выберите время' });
  }
  if (v.fulfilment !== 'pickup' && !v.address?.trim()) {
    ctx.addIssue({ code: 'custom', path: ['address'], message: 'Укажите адрес' });
  }
});

export type CheckoutForm = z.infer<typeof schema>;

interface Quote {
  subtotal: number;
  discount: number;
  pointsSpent: number;
  deliveryCost: number;
  total: number;
  promoError: string | null;
  delivery: DeliveryQuote | null;
  maxPoints: number;
}

interface Suggestion {
  text: string;
  lat: number;
  lng: number;
}

export default function Checkout() {
  const cart = useCart();
  const { user } = useAuth();
  const [coords, setCoords] = useState<{ lat: number; lng: number } | null>(null);
  const form = useForm<z.input<typeof schema>, unknown, CheckoutForm>({
    resolver: zodResolver(schema),
    defaultValues: { fulfilment: 'pickup', email: '', points: 0 },
  });
  const { register, handleSubmit, watch, formState: { errors }, setValue } = form;
  const fulfilment = watch('fulfilment') as FulfilmentType;
  const address = watch('address') ?? '';
  const promoCode = watch('promoCode') ?? '';
  const points = Number(watch('points') ?? 0);

  useEffect(() => {
    if (user) {
      setValue('name', user.name);
      setValue('email', user.email);
      if (user.phone) setValue('phone', user.phone);
    }
  }, [user, setValue]);

  const slotType = fulfilment === 'courier' ? 'courier' : 'pickup';
  const slots = useQuery({
    queryKey: ['slots', slotType],
    queryFn: () => api.get<{ data: Slot[] }>(`/slots?type=${slotType}`).then((r) => r.data),
    enabled: fulfilment === 'pickup' || fulfilment === 'courier',
  });

  const suggestions = useQuery({
    queryKey: ['geo', address],
    queryFn: () => api.get<{ data: Suggestion[] }>(`/geo/suggest?q=${encodeURIComponent(address)}`).then((r) => r.data),
    enabled: fulfilment !== 'pickup' && address.length >= 5 && !coords,
    staleTime: 60_000,
  });

  const quote = useQuery({
    queryKey: ['quote', cart.lines, fulfilment, coords, promoCode, points],
    queryFn: () => api.post<{ data: Quote }>('/cart/quote', {
      lines: cart.lines, fulfilment, promoCode: promoCode || null, points,
      address: coords ? { text: address, ...coords } : null,
    }).then((r) => r.data),
    enabled: cart.lines.length > 0,
  });

  const submit = useMutation({
    mutationFn: (v: CheckoutForm) => api.post<{ data: { number: string }; payment: { url: string } }>('/orders', {
      ...v,
      slotId: v.slotId ? Number(v.slotId) : null,
      lines: cart.lines,
      address: v.fulfilment === 'pickup' ? null : { text: v.address, entrance: v.entrance, floor: v.floor, intercom: v.intercom, ...(coords ?? {}) },
    }),
    onSuccess: (res) => {
      reachGoal('checkout_complete', { order: res.data.number });
      cart.clear();
      window.location.assign(res.payment.url);
    },
  });

  if (cart.lines.length === 0) {
    return (
      <div className="container page">
        <Seo title="Оформление заказа — Зефир и пряник" description="Оформление заказа" path="/checkout/" noindex />
        <h1>Оформление заказа</h1>
        <p className="lead">Корзина пуста. <Link to="/catalog/">Перейти в каталог</Link></p>
      </div>
    );
  }

  const q = quote.data;
  const deliveryBlocked = fulfilment !== 'pickup' && q?.delivery && !q.delivery.available;
  const serverErrors = submit.error instanceof ApiError ? submit.error.errors : {};

  return (
    <>
      <Seo title="Оформление заказа — Зефир и пряник" description="Оформление заказа" path="/checkout/" noindex />
      <div className="container page">
        <h1>Оформление заказа</h1>
        <form className="checkout" onSubmit={handleSubmit((v) => submit.mutate(v))} noValidate>
          <fieldset>
            <legend>Контакты</legend>
            <label>Имя<input {...register('name')} autoComplete="name" /></label>
            {errors.name && <p className="error">{errors.name.message}</p>}
            <label>Телефон<input {...register('phone')} type="tel" autoComplete="tel" /></label>
            {errors.phone && <p className="error">{errors.phone.message}</p>}
            <label>Email<input {...register('email')} type="email" autoComplete="email" /></label>
            {errors.email && <p className="error">{errors.email.message}</p>}
          </fieldset>

          <fieldset>
            <legend>Способ получения</legend>
            {(Object.keys(fulfilmentLabels) as FulfilmentType[]).map((f) => (
              <label key={f} className="radio">
                <input type="radio" value={f} {...register('fulfilment')} onChange={(e) => { register('fulfilment').onChange(e); setValue('slotId', undefined); }} />
                {fulfilmentLabels[f]}
              </label>
            ))}
          </fieldset>

          {fulfilment !== 'pickup' && (
            <fieldset>
              <legend>Адрес</legend>
              <label>Адрес<input {...register('address')} autoComplete="street-address" onChange={(e) => { register('address').onChange(e); setCoords(null); }} /></label>
              {suggestions.data && suggestions.data.length > 0 && !coords && (
                <ul className="suggest" role="listbox">
                  {suggestions.data.map((s) => (
                    <li key={s.text}><button type="button" onClick={() => { setValue('address', s.text); setCoords({ lat: s.lat, lng: s.lng }); }}>{s.text}</button></li>
                  ))}
                </ul>
              )}
              {errors.address && <p className="error">{errors.address.message}</p>}
              {fulfilment === 'courier' && (
                <div className="row">
                  <label>Подъезд<input {...register('entrance')} /></label>
                  <label>Этаж<input {...register('floor')} /></label>
                  <label>Домофон<input {...register('intercom')} /></label>
                </div>
              )}
              {q?.delivery && <p className={q.delivery.available ? 'muted' : 'error'}>{q.delivery.message ?? `${formatPrice(q.delivery.cost)} · ${q.delivery.etaText}`}</p>}
            </fieldset>
          )}

          {(fulfilment === 'pickup' || fulfilment === 'courier') && (
            <fieldset>
              <legend>{fulfilment === 'pickup' ? 'Когда заберёте' : 'Интервал доставки'}</legend>
              {slots.isLoading && <p className="muted">Загружаем свободное время…</p>}
              <select {...register('slotId')} defaultValue="">
                <option value="" disabled>Выберите время</option>
                {(slots.data ?? []).map((s) => (
                  <option key={s.id} value={s.id} disabled={s.available <= 0}>
                    {new Date(`${s.date}T00:00:00`).toLocaleDateString('ru-RU', { weekday: 'short', day: 'numeric', month: 'long' })}, {s.from}–{s.to}{s.available <= 0 ? ' — занято' : ''}
                  </option>
                ))}
              </select>
              {(errors.slotId || serverErrors.slotId) && <p className="error">{errors.slotId?.message ?? serverErrors.slotId?.[0]}</p>}
            </fieldset>
          )}

          <fieldset>
            <legend>Скидки</legend>
            <label>Промокод<input {...register('promoCode')} autoComplete="off" /></label>
            {q?.promoError && <p className="error">{q.promoError}</p>}
            {user && q && q.maxPoints > 0 && (
              <label>Списать баллы (до {q.maxPoints})<input type="number" min={0} max={q.maxPoints} {...register('points')} /></label>
            )}
          </fieldset>

          <label>Комментарий<textarea {...register('comment')} rows={3} /></label>

          <label className="checkbox">
            <input type="checkbox" {...register('consent')} />
            Согласен с <Link to={pages.privacy.path}>политикой обработки персональных данных</Link> и <Link to={pages.offer.path}>офертой</Link>
          </label>
          {errors.consent && <p className="error">{errors.consent.message}</p>}

          <aside className="summary">
            {q ? (
              <dl>
                <dt>Товары</dt><dd>{formatPrice(q.subtotal)}</dd>
                {q.discount > 0 && (<><dt>Скидка</dt><dd>−{formatPrice(q.discount)}</dd></>)}
                {q.pointsSpent > 0 && (<><dt>Баллы</dt><dd>−{formatPrice(q.pointsSpent)}</dd></>)}
                <dt>Доставка</dt><dd>{formatPrice(q.deliveryCost)}</dd>
                <dt>К оплате</dt><dd><strong>{formatPrice(q.total)}</strong></dd>
              </dl>
            ) : <p className="muted">Считаем сумму…</p>}
            {submit.error && <p className="error">{submit.error.message}</p>}
            <button type="submit" className="btn btn-primary" disabled={submit.isPending || Boolean(deliveryBlocked)}>
              {submit.isPending ? 'Оформляем…' : 'Перейти к оплате'}
            </button>
          </aside>
        </form>
      </div>
    </>
  );
}
