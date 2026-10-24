<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = ['name', 'polygon', 'price', 'min_order', 'free_from', 'eta_minutes', 'max_transit_minutes', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['polygon' => 'array', 'is_active' => 'boolean', 'price' => 'integer', 'min_order' => 'integer', 'free_from' => 'integer'];
    }

    /** Точка внутри многоугольника (ray casting). Координаты [lat, lng]. */
    public function contains(float $lat, float $lng): bool
    {
        $poly = $this->polygon ?? [];
        $inside = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$yi, $xi] = $poly[$i];
            [$yj, $xj] = $poly[$j];
            if ((($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    public function costFor(int $subtotal): int
    {
        return $this->free_from !== null && $subtotal >= $this->free_from ? 0 : $this->price;
    }

    public function etaText(): string
    {
        $h = intdiv($this->eta_minutes, 60);
        $m = $this->eta_minutes % 60;

        return 'от '.($h ? "{$h} ч" : '').($h && $m ? ' ' : '').($m ? "{$m} мин" : '');
    }
}
