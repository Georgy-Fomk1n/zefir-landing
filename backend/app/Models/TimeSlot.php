<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TimeSlot extends Model
{
    protected $fillable = ['type', 'date', 'starts_at', 'ends_at', 'capacity', 'booked', 'is_active'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'is_active' => 'boolean', 'capacity' => 'integer', 'booked' => 'integer'];
    }

    public function available(): int
    {
        return max(0, $this->capacity - $this->booked);
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->starts_at, config('shop.timezone'));
    }

    /** @return array{date: string, from: string, to: string} */
    public function toWindow(): array
    {
        return ['date' => $this->date->format('Y-m-d'), 'from' => substr($this->starts_at, 0, 5), 'to' => substr($this->ends_at, 0, 5)];
    }
}
