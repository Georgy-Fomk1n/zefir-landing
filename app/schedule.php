<?php
declare(strict_types=1);

/* Выдача по часовым слотам. Правила: график работы и запас времени из config.php. */

function slot_parts(): array
{
    return [['Утро', [10, 11]], ['День', [12, 13, 15]], ['Вечер', [16, 17, 18, 19]]];
}

function is_open_day(DateTimeInterface $d): bool
{
    return !in_array((int) $d->format('w'), (array) cfg('hours.closed_days'), true);
}

function open_now(?DateTimeImmutable $now = null): bool
{
    $now ??= new DateTimeImmutable('now');
    $t = (int) $now->format('G') + (int) $now->format('i') / 60;
    return is_open_day($now) && $t >= (int) cfg('hours.open') && $t < (int) cfg('hours.close');
}

/** Можно ли выбрать этот час в этот день */
function slot_valid(string $date, int $hour): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return false;
    }
    $now = new DateTimeImmutable('now');
    $today = $now->setTime(0, 0);
    $diff = (int) $today->diff($d)->format('%r%a');
    if ($diff < 0 || $diff >= (int) cfg('schedule.days') || !is_open_day($d)) {
        return false;
    }
    $allowed = array_merge(...array_column(slot_parts(), 1));
    if (!in_array($hour, $allowed, true)) {
        return false;
    }
    return !($diff === 0 && $hour < (int) $now->format('G') + (int) cfg('schedule.lead_hours'));
}

/** Данные для JS: на каждый день список доступных часов */
function schedule_data(): array
{
    $today = (new DateTimeImmutable('now'))->setTime(0, 0);
    $hours = array_merge(...array_column(slot_parts(), 1));
    $days = [];
    for ($i = 0; $i < (int) cfg('schedule.days'); $i++) {
        $d = $today->modify("+$i day");
        $ds = $d->format('Y-m-d');
        $days[] = [
            'date' => $ds, 'i' => $i, 'dow' => (int) $d->format('w'), 'd' => (int) $d->format('j'), 'm' => (int) $d->format('n') - 1, 'y' => (int) $d->format('Y'),
            'closed' => !is_open_day($d),
            'ok' => array_values(array_filter($hours, fn ($h) => slot_valid($ds, $h))),
        ];
    }
    return ['days' => $days, 'parts' => slot_parts()];
}

const MONTHS_GEN = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
const WEEKDAYS = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

function day_label(string $date): string
{
    $d = new DateTimeImmutable($date);
    $diff = (int) (new DateTimeImmutable('today'))->diff($d)->format('%r%a');
    $name = $diff === 0 ? 'Сегодня' : ($diff === 1 ? 'Завтра' : WEEKDAYS[(int) $d->format('w')]);
    return $name . ', ' . (int) $d->format('j') . ' ' . MONTHS_GEN[(int) $d->format('n') - 1];
}
