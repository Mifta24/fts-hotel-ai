<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Hotel',
            'slug' => fake()->unique()->slug(2),
            'currency' => 'IDR',
            'default_locale' => 'id',
            'public_status' => 'published',
        ];
    }

    public function draft(): static
    {
        return $this->state(['public_status' => 'draft']);
    }

    public function withMembers(): static
    {
        return $this->state(['features' => [Hotel::FEATURE_MEMBER]]);
    }
}
