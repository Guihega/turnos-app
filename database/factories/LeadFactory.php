<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'organization' => fake()->company(),
            'sector' => fake()->randomElement(Lead::SECTORS),
            'size' => fake()->randomElement(Lead::SIZES),
            'message' => fake()->optional(0.6)->sentence(12),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'referrer' => fake()->optional()->url(),
            'status' => 'new',
        ];
    }
}
