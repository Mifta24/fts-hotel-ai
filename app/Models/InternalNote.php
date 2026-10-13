<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A note staff leave for each other on a booking or a handover; guests never see it.
 */
#[Fillable(['hotel_id', 'user_id', 'notable_type', 'notable_id', 'body'])]
class InternalNote extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
