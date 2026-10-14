<?php

namespace App\Core\Member\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of a member's points history. The balance is the sum of the lines,
 * so nothing is ever overwritten and every change can be explained.
 */
#[Fillable(['member_id', 'type', 'points', 'source_type', 'source_id', 'note', 'actor_name'])]
class MemberPoint extends Model
{
    public const TYPE_EARN = 'earn';

    public const TYPE_REVERSE = 'reverse';

    public const TYPE_ADJUST = 'adjust';

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
