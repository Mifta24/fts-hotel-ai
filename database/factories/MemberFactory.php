<?php

namespace Database\Factories;

use App\Core\Member\Models\Member;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_type' => (new Hotel)->getMorphClass(),
            'tenant_id' => Hotel::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'locale' => null,
            'email_verified_at' => now(),
        ];
    }

    public function forHotel(Hotel $hotel): static
    {
        return $this->state(['tenant_type' => $hotel->getMorphClass(), 'tenant_id' => $hotel->id]);
    }

    public function withoutProfile(): static
    {
        return $this->state(['name' => null]);
    }
}
