<?php

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidTransition;

it('разрешает только описанные переходы заказа', function () {
    expect(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Ready))->toBeFalse()
        ->and(OrderStatus::Ready->canTransitionTo(OrderStatus::HandedToCourier))->toBeTrue()
        ->and(OrderStatus::Completed->isFinal())->toBeTrue()
        ->and(OrderStatus::Cancelled->isFinal())->toBeTrue();
});

it('бросает исключение на недопустимый переход', function () {
    OrderStatus::Completed->assertTransitionTo(OrderStatus::Preparing);
})->throws(InvalidTransition::class);

it('описывает конечный автомат доставки', function (DeliveryStatus $from, DeliveryStatus $to, bool $ok) {
    expect($from->canTransitionTo($to))->toBe($ok);
})->with([
    [DeliveryStatus::Assigned, DeliveryStatus::Accepted, true],
    [DeliveryStatus::Assigned, DeliveryStatus::Delivered, false],
    [DeliveryStatus::Accepted, DeliveryStatus::PickedUp, true],
    [DeliveryStatus::PickedUp, DeliveryStatus::InTransit, true],
    [DeliveryStatus::InTransit, DeliveryStatus::Delivered, true],
    [DeliveryStatus::InTransit, DeliveryStatus::Failed, true],
    [DeliveryStatus::Failed, DeliveryStatus::Returned, true],
    [DeliveryStatus::Delivered, DeliveryStatus::Failed, false],
    [DeliveryStatus::Returned, DeliveryStatus::InTransit, false],
]);

it('показывает телефон курьеру только в активной доставке', function () {
    expect(DeliveryStatus::Assigned->isActive())->toBeFalse()
        ->and(DeliveryStatus::InTransit->isActive())->toBeTrue()
        ->and(DeliveryStatus::Delivered->isActive())->toBeFalse();
});

it('описывает конечный автомат абонемента', function () {
    expect(SubscriptionStatus::Pending->canTransitionTo(SubscriptionStatus::Active))->toBeTrue()
        ->and(SubscriptionStatus::Active->canTransitionTo(SubscriptionStatus::PastDue))->toBeTrue()
        ->and(SubscriptionStatus::PastDue->canTransitionTo(SubscriptionStatus::Grace))->toBeTrue()
        ->and(SubscriptionStatus::Grace->canTransitionTo(SubscriptionStatus::Expired))->toBeTrue()
        ->and(SubscriptionStatus::Expired->canTransitionTo(SubscriptionStatus::Active))->toBeFalse()
        ->and(SubscriptionStatus::Pending->canTransitionTo(SubscriptionStatus::Grace))->toBeFalse()
        ->and(SubscriptionStatus::Cancelled->isUsable())->toBeTrue();
});
