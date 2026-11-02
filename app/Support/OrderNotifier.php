<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderNotifier
{
    public static function text(Order $o): string
    {
        $lines = ['Новый заказ № ' . $o->number(), ''];
        foreach ($o->items as $it) {
            $lines[] = '• ' . $it->name . ' × ' . $it->qty . ' = ' . $it->sum . ' ₽';
        }
        if ($o->card_text) {
            $lines[] = 'Открытка: ' . $o->card_text;
        }
        $lines[] = '';
        $lines[] = ($o->shipping === 'courier' ? 'Курьер, ' : 'Самовывоз, ') . $o->address;
        $lines[] = 'Когда: ' . $o->slotLabel();
        $lines[] = 'Кто: ' . $o->name . ', ' . $o->phone;
        if ($o->comment) {
            $lines[] = 'Комментарий: ' . $o->comment;
        }
        $lines[] = 'Итого: ' . $o->total . ' ₽' . ($o->fee ? ' (с доставкой ' . $o->fee . ' ₽)' : '');

        return implode("\n", $lines);
    }

    /** Заказ принят: Telegram, почта и push владельцу. Всё после ответа покупателю, сбой не отменяет заказ. */
    public static function placed(Order $order): void
    {
        $text = self::text($order);

        dispatch(function () use ($text, $order) {
            Telegram::send($text);
            if ($to = config('shop.notify.mail_to')) {
                try {
                    Mail::raw($text, fn ($m) => $m->to($to)->subject('Новый заказ № ' . $order->number()));
                } catch (\Throwable $e) {
                    Log::warning('Mail: ' . $e->getMessage());
                }
            }
        })->afterResponse();

        Push::toOwners('Новый заказ № ' . $order->number(), $order->name . ' · ' . rub($order->total) . ' · ' . $order->slotLabel(), url('/admin/orders/' . $order->id));
    }
}
