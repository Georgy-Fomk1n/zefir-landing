<?php

namespace Database\Factories;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => 'ZP'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
            'status' => OrderStatus::PendingPayment,
            'fulfilment' => Fulfilment::Pickup,
            'contact_name' => 'Ольга',
            'contact_phone' => '+79991112233',
            'contact_email' => fake()->safeEmail(),
            'subtotal' => 90000,
            'total' => 90000,
            'payment_status' => PaymentStatus::Pending,
            'tracking_token' => Str::random(40),
        ];
    }
}
