<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'hotel_id',
    'room_type_id',
    'conversation_id',
    'guest_name',
    'guest_email',
    'guest_phone',
    'contact_type',
    'check_in',
    'check_out',
    'adults',
    'children',
    'room_count',
    'extra_bed',
    'total_price',
    'status',
    'notes',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'extra_bed' => 'boolean',
            'total_price' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A short, unambiguous, non-sequential code guests can quote to staff.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'BK-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function nights(): int
    {
        return $this->check_in->diffInDays($this->check_out);
    }
}
