import { deliveryStatusLabels, orderStatusLabels, type Order } from '@zefir/shared';
import { useQuery } from '@tanstack/react-query';
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
    queryFn: () => api.get<{ data: Pick<Order, 'number' | 'status' | 'delivery' | 'slot' | 'history'> }>(`/track/${encodeURIComponent(token)}`).then((r) => r.data),
    refetchInterval: 30_000,
    retry: false,
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
          </>
        )}
      </div>
    </>
  );
}
