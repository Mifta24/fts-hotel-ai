<?php

namespace App\Core\Member\Services;

use App\Core\Member\Models\Member;
use App\Core\Member\Models\MemberPoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The only way points change. Earnings and reversals are tied to what caused
 * them, so running the same award twice never pays a member twice.
 */
class PointsLedger
{
    /**
     * Credits points for a source. Returns null if that source already paid out.
     */
    public function earn(Member $member, int $points, Model $source, ?string $note = null): ?MemberPoint
    {
        return $this->recordOnce($member, MemberPoint::TYPE_EARN, abs($points), $source, $note);
    }

    /**
     * Takes back what a source paid out. Returns null if there is nothing to take back or it was already taken.
     */
    public function reverse(Member $member, Model $source, ?string $note = null): ?MemberPoint
    {
        $earned = MemberPoint::where('member_id', $member->id)
            ->where('type', MemberPoint::TYPE_EARN)
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->first();

        return $earned ? $this->recordOnce($member, MemberPoint::TYPE_REVERSE, -$earned->points, $source, $note) : null;
    }

    /**
     * A manual correction or goodwill gift by a staff member; the note says why.
     */
    public function adjust(Member $member, int $points, string $note, string $actorName): MemberPoint
    {
        return $member->points()->create([
            'type' => MemberPoint::TYPE_ADJUST,
            'points' => $points,
            'note' => $note,
            'actor_name' => $actorName,
        ]);
    }

    private function recordOnce(Member $member, string $type, int $points, Model $source, ?string $note): ?MemberPoint
    {
        $exists = MemberPoint::where('type', $type)
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->exists();

        if ($exists) {
            return null;
        }

        try {
            return $member->points()->create([
                'type' => $type,
                'points' => $points,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'note' => $note,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
