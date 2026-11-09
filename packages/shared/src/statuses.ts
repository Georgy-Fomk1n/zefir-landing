import type { DeliveryStatus, FulfilmentType, OrderStatus, SubscriptionStatus } from './types';

// Подписи статусов для интерфейсов. Сами переходы проверяет бэкенд (app/Domain/*/StateMachine).

export const orderStatusLabels: Record<OrderStatus, string> = {
  pending_payment: 'Ожидает оплаты',
  paid: 'Оплачен',
  accepted: 'Принят',
  preparing: 'Готовится',
  ready: 'Готов',
  handed_to_courier: 'Передан курьеру',
  shipped: 'Отправлен',
  completed: 'Выдан',
  cancelled: 'Отменён',
};

export const deliveryStatusLabels: Record<DeliveryStatus, string> = {
  assigned: 'Назначена',
  accepted: 'Принята курьером',
  picked_up: 'Курьер забрал заказ',
  in_transit: 'В пути',
  delivered: 'Доставлена',
  failed: 'Не удалось вручить',
  returned: 'Возврат',
};

export const fulfilmentLabels: Record<FulfilmentType, string> = {
  pickup: 'Самовывоз',
  courier: 'Курьером по Москве',
  cdek: 'СДЭК',
  russian_post: 'Почта России',
};

export const subscriptionStatusLabels: Record<SubscriptionStatus, string> = {
  pending: 'Ожидает оплаты',
  active: 'Активен',
  past_due: 'Ошибка продления',
  grace: 'Льготный период',
  cancelled: 'Отменён',
  expired: 'Закончился',
};
