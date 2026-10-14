<?php

namespace App\Core\Member\Support;

/**
 * How a tenant rewards its members: how many points a spend earns, and which
 * tiers those points unlock. Points are status points — they are never spent,
 * so a member only ever climbs.
 */
final class LoyaltyProgram
{
    /**
     * @var list<LoyaltyTier> lowest tier first
     */
    public readonly array $tiers;

    /**
     * @param  float  $spendPerPoint  how much money earns one point
     * @param  list<LoyaltyTier>  $tiers
     */
    public function __construct(public readonly float $spendPerPoint, array $tiers)
    {
        usort($tiers, fn (LoyaltyTier $a, LoyaltyTier $b) => $a->minPoints <=> $b->minPoints);

        $this->tiers = $tiers;
    }

    /**
     * @param  array{spend_per_point?: mixed, tiers?: mixed}  $config
     */
    public static function fromArray(array $config, float $defaultSpendPerPoint): self
    {
        $tiers = collect($config['tiers'] ?? [])
            ->filter(fn ($tier) => is_array($tier) && filled($tier['name'] ?? null))
            ->map(fn (array $tier) => new LoyaltyTier((string) $tier['name'], max(0, (int) ($tier['min_points'] ?? 0)), max(0.0, min(100.0, (float) ($tier['discount_percent'] ?? 0)))))
            ->values()
            ->all();

        $spend = (float) ($config['spend_per_point'] ?? 0);

        return new self($spend > 0 ? $spend : $defaultSpendPerPoint, $tiers);
    }

    /**
     * @return array{spend_per_point: float, tiers: list<array{name: string, min_points: int, discount_percent: float}>}
     */
    public function toArray(): array
    {
        return [
            'spend_per_point' => $this->spendPerPoint,
            'tiers' => array_map(fn (LoyaltyTier $tier) => ['name' => $tier->name, 'min_points' => $tier->minPoints, 'discount_percent' => $tier->discountPercent], $this->tiers),
        ];
    }

    public function pointsFor(float $spend): int
    {
        return $spend > 0 ? (int) floor($spend / $this->spendPerPoint) : 0;
    }

    /**
     * The highest tier the points reach, or null while below the first one.
     */
    public function tierFor(int $points): ?LoyaltyTier
    {
        $reached = null;

        foreach ($this->tiers as $tier) {
            if ($points >= $tier->minPoints) {
                $reached = $tier;
            }
        }

        return $reached;
    }

    /**
     * The next tier up, or null at the top.
     */
    public function nextTierAfter(int $points): ?LoyaltyTier
    {
        foreach ($this->tiers as $tier) {
            if ($points < $tier->minPoints) {
                return $tier;
            }
        }

        return null;
    }

    public function discountFor(int $points): float
    {
        return $this->tierFor($points)?->discountPercent ?? 0.0;
    }
}
