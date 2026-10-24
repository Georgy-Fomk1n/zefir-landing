import { deliveryStatusLabels, orderStatusLabels, type Order } from '@zefir/shared';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { api } from '../lib/api';
import { Seo } from '../seo/Seo';

/** Отслеживание заказа без входа по одноразовой ссылке /track/?t=… (токен из письма/уведомления). */
export default function Track() {
  const [params] = useSearchParams();
  const token = params.get('t') ?? '';
  const order = useQuery({
    queryKey: ['track', token],
    enabled: token.length > 0,
    queryFn: () => api.get<{ data: Pick<Order, 'number' | 'status' | 'paymentStatus' | 'delivery' | 'slot' | 'history'> }>(`/track/${encodeURIComponent(token)}`).then((r) => r.data),
    refetchInterval: 30_000,
    retry: false,
  });
  const pay = useMutation({
    mutationFn: () => api.post<{ payment: { url: string } }>(`/track/${encodeURIComponent(token)}/pay`),
    onSuccess: (r) => window.location.assign(r.payment.url),
  });
  return (
    <>
      <Seo title="Отслеживание заказа — Зефир и пряник" description="Статус заказа" path="/track/" noindex />
      <div className="container page">
        <h1>Отслеживание заказа</h1>
        {!token && <p>Откройте ссылку из уведомления о заказе.</p>}
        {order.isError && <p className="error">Ссылка недействительна или устарела.</p>}
        {order.data && (
          <>
            <p className="lead">Заказ № {order.data.number}: {orderStatusLabels[order.data.status]}</p>
            {order.data.delivery && <p>Доставка: {deliveryStatusLabels[order.data.delivery.status]}</p>}
            {order.data.slot && <p>Интервал: {order.data.slot.date}, {order.data.slot.from}–{order.data.slot.to}</p>}
            {order.data.status === 'pending_payment' && (
              <>
                {order.data.paymentStatus === 'failed' && <p className="error">Оплата не прошла. Попробуйте ещё раз.</p>}
                <button type="button" className="btn btn-primary" disabled={pay.isPending} onClick={() => pay.mutate()}>Оплатить заказ</button>
                {pay.error && <p className="error">{pay.error.message}</p>}
              </>
            )}
          </>
        )}
      </div>
    </>
  );
}
