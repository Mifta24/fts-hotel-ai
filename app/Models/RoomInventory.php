<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only source of truth for availability and price. Stands in for a real
 * PMS/booking-engine adapter for V1 — the AI must reach this table (or its
 * future adapter) through a tool call, never answer from its own memory.
 */
#[Fillable([
    'room_type_id',
    'stay_date',
    'total_units',
    'booked_units',
    'price',
])]
class RoomInventory extends Model
{
    protected $table = 'room_inventory';

    protected function casts(): array
    {
        return [
            'stay_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function availableUnits(): int
    {
        return max(0, $this->total_units - $this->booked_units);
    }

    public function isAvailable(): bool
    {
        return $this->availableUnits() > 0;
    }
}
