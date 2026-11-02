<?php

namespace Tests\Unit;

use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // вторник, 3 ноября 2026, 09:30
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:30', config('app.timezone')));
    }

    public function test_monday_is_closed(): void
    {
        $this->assertFalse(Schedule::valid('2026-11-09', 12));
        $this->assertTrue(Schedule::valid('2026-11-10', 12));
    }

    public function test_same_day_needs_lead_time(): void
    {
        // сейчас 09:30, запас 2 часа: 10 и 11 уже нельзя, 12 можно
        $this->assertFalse(Schedule::valid('2026-11-03', 10));
        $this->assertFalse(Schedule::valid('2026-11-03', 10 + 0));
        $this->assertTrue(Schedule::valid('2026-11-03', 12));
    }

    public function test_past_and_far_dates_and_unknown_hours_are_rejected(): void
    {
        $this->assertFalse(Schedule::valid('2026-11-02', 12));
        $this->assertFalse(Schedule::valid('2027-03-01', 12));
        $this->assertFalse(Schedule::valid('2026-11-04', 14));   // 14:00 в слотах нет
        $this->assertFalse(Schedule::valid('not-a-date', 12));
        $this->assertFalse(Schedule::valid('2026-02-31', 12));
    }

    public function test_data_matches_validation(): void
    {
        foreach (Schedule::data()['days'] as $d) {
            foreach (Schedule::hours() as $h) {
                $this->assertSame(in_array($h, $d['ok'], true), Schedule::valid($d['date'], $h), $d['date'] . ' ' . $h);
            }
        }
    }

    public function test_open_now(): void
    {
        $this->assertFalse(Schedule::openNow(CarbonImmutable::parse('2026-11-03 09:30')));
        $this->assertTrue(Schedule::openNow(CarbonImmutable::parse('2026-11-03 10:00')));
        $this->assertFalse(Schedule::openNow(CarbonImmutable::parse('2026-11-03 21:00')));
        $this->assertFalse(Schedule::openNow(CarbonImmutable::parse('2026-11-09 12:00')));   // понедельник
    }
}
