<?php

namespace App\Core\Member\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A six-digit code mailed to a guest. Only a keyed hash of the code is kept,
 * it works once, expires quickly and dies after too many wrong tries.
 */
#[Fillable(['email', 'code_hash', 'attempts', 'expires_at', 'consumed_at'])]
class MemberLoginCode extends Model
{
    use Prunable;

    public const MAX_ATTEMPTS = 5;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Codes are worthless long after they expire; a day later they are cleared away.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < self::MAX_ATTEMPTS;
    }
}
