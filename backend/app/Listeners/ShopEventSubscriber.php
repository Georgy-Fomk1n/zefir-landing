<?php

namespace App\Listeners;

use App\Enums\DeliveryStatus;
use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Events\CatalogChanged;
use App\Events\DeliveryStatusChanged;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Notifications\OrderStatusNotification;
use App\Services\Catalog\StaticRebuild;
use App\Services\Delivery\DeliveryProviders;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

class ShopEventSubscriber
{
    public function __construct(
        private LoyaltyService $loyalty,
        private StaticRebuild $rebuild,
        private DeliveryProviders $providers,
    ) {}

    public function onOrderStatus(OrderStatusChanged $e): void
    {
        $order = $e->order;
        if ($e->status === OrderStatus::Completed) {
            $this->loyalty->awardForOrder($order);
        }
        // Отправление СДЭК/Почтой создаётся, когда заказ готов
        if ($e->status === OrderStatus::Shipped && in_array($order->fulfilment, [Fulfilment::Cdek, Fulfilment::RussianPost], true) && ! $order->external_tracking) {
            $order->forceFill(['external_tracking' => $this->providers->for($order->fulfilment)->createShipment($order)])->save();
        }
        $headline = match ($e->status) {
            OrderStatus::Paid => 'оплачен, мы приняли его в работу',
            OrderStatus::Ready => $order->fulfilment === Fulfilment::Pickup ? 'готов, ждём вас на ул. Базовская, 17' : 'готов',
            OrderStatus::HandedToCourier => 'передан курьеру',
            OrderStatus::Shipped => 'отправлен'.($order->external_tracking ? ", трек-номер {$order->external_tracking}" : ''),
            OrderStatus::Completed => $order->fulfilment === Fulfilment::Pickup ? 'выдан. Спасибо!' : 'доставлен. Спасибо!',
            OrderStatus::Cancelled => 'отменён',
            default => null,
        };
        if ($headline) {
            $this->notify($order, $headline);
        }
    }

    public function onDeliveryStatus(DeliveryStatusChanged $e): void
    {
        $headline = match ($e->status) {
            DeliveryStatus::InTransit => 'курьер в пути',
            DeliveryStatus::Failed => 'курьеру не удалось вручить заказ, мы свяжемся с вами',
            default => null,
        };
        if ($headline) {
            $this->notify($e->delivery->order, $headline);
        }
    }

    public function onCatalogChanged(CatalogChanged $e): void
    {
        $this->rebuild->request($e->paths, immediate: $e->priceChanged || $e->deleted);
    }

    private function notify(Order $order, string $headline): void
    {
        $n = new OrderStatusNotification($order, $headline);
        if ($order->user) {
            $order->user->notify($n);

            return;
        }
        $route = Notification::route('sms', $order->contact_phone);
        if ($order->contact_email) {
            $route->route('mail', $order->contact_email);
        }
        $route->notify($n);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderStatusChanged::class => 'onOrderStatus',
            DeliveryStatusChanged::class => 'onDeliveryStatus',
            CatalogChanged::class => 'onCatalogChanged',
        ];
    }
}
