<?php

namespace App\Services\Orders;

use App\Models\TimeSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Слоты «заказ ко времени» и интервалы курьерской доставки. Генерируются по часам работы
 * (Пн — выходной, Вт–Вс 10:00–21:00), лимит заказов на слот, минимальное время на подготовку.
 */
class SlotService
{
    /** Создаёт недостающие слоты на N дней вперёд (идемпотентно; запускается планировщиком). */
    public function generate(?int $days = null, ?Carbon $from = null): int
    {
        $tz = config('shop.timezone');
        $from = ($from ?? now($tz))->copy()->setTimezone($tz)->startOfDay();
        $created = 0;
        foreach (['pickup' => ['opens', 'closes', 'pickup_capacity'], 'courier' => ['courier_opens', 'courier_closes', 'courier_capacity']] as $type => [$o, $c, $cap]) {
            for ($d = 0; $d < ($days ?? config('shop.slots.days_ahead')); $d++) {
                $date = $from->copy()->addDays($d);
                if (in_array($date->isoWeekday(), config('shop.slots.closed_weekdays'), true)) {
                    continue;
                }
                $start = Carbon::parse($date->format('Y-m-d').' '.config("shop.slots.$o"), $tz);
                $end = Carbon::parse($date->format('Y-m-d').' '.config("shop.slots.$c"), $tz);
                $len = config('shop.slots.length_minutes');
                for ($t = $start->copy(); $t->copy()->addMinutes($len)->lte($end); $t->addMinutes($len)) {
                    $slot = TimeSlot::query()->firstOrCreate(
                        ['type' => $type, 'date' => $date->format('Y-m-d'), 'starts_at' => $t->format('H:i:s')],
                        ['ends_at' => $t->copy()->addMinutes($len)->format('H:i:s'), 'capacity' => config("shop.slots.$cap")],
                    );
                    $created += $slot->wasRecentlyCreated ? 1 : 0;
                }
            }
        }

        return $created;
    }

    /** Доступные для выбора слоты: активные, не раньше «сейчас + время подготовки». */
    public function available(string $type): Collection
    {
        $earliest = now(config('shop.timezone'))->addMinutes(config('shop.slots.prep_minutes'));

        return TimeSlot::query()->where('type', $type)->where('is_active', true)
            ->whereDate('date', '>=', $earliest->format('Y-m-d'))
            ->orderBy('date')->orderBy('starts_at')->limit(200)->get()
            ->filter(fn (TimeSlot $s) => $s->startsAt()->gte($earliest))
            ->values();
    }

    /** Бронирование места в слоте внутри транзакции оформления. Лимит проверяется под блокировкой. */
    public function book(int $slotId, string $type): TimeSlot
    {
        /** @var TimeSlot|null $slot */
        $slot = TimeSlot::query()->lockForUpdate()->find($slotId);
        $earliest = now(config('shop.timezone'))->addMinutes(config('shop.slots.prep_minutes'));
        if (! $slot || $slot->type !== $type || ! $slot->is_active || $slot->startsAt()->lt($earliest)) {
            throw ValidationException::withMessages(['slotId' => 'Это время недоступно, выберите другое']);
        }
        if ($slot->booked >= $slot->capacity) {
            throw ValidationException::withMessages(['slotId' => 'На это время заказов уже достаточно, выберите другое']);
        }
        $slot->increment('booked');

        return $slot;
    }

    public function release(?int $slotId): void
    {
        if ($slotId) {
            DB::table('time_slots')->where('id', $slotId)->where('booked', '>', 0)->decrement('booked');
        }
    }
}
