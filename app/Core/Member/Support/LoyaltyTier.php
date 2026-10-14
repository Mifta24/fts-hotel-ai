<?php

namespace App\Core\Member\Support;

final readonly class LoyaltyTier
{
    public function __construct(
        public string $name,
        public int $minPoints,
        public float $discountPercent,
    ) {}
}
