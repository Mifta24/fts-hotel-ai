<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Who changed what, and when, in a hotel's admin or through a guest's own request.
 */
#[Fillable(['hotel_id', 'user_id', 'actor', 'action', 'summary', 'subject_type', 'subject_id', 'meta'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTOR_STAFF = 'staff';

    public const ACTOR_GUEST = 'guest';

    public const ACTOR_SYSTEM = 'system';

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(int $hotelId, string $action, string $summary, ?Model $subject = null, ?User $user = null, array $meta = [], ?string $actor = null): self
    {
        return static::create([
            'hotel_id' => $hotelId,
            'user_id' => $user?->id,
            'actor' => $actor ?? ($user ? self::ACTOR_STAFF : self::ACTOR_SYSTEM),
            'action' => $action,
            'summary' => $summary,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
