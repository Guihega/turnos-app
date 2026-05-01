<?php

namespace Database\Factories;

use App\Models\DisplayAnnouncement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisplayAnnouncementFactory extends Factory
{
    protected $model = DisplayAnnouncement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => null,
            'type' => $this->faker->randomElement(['announcement', 'news', 'promo']),
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->optional()->paragraph(),
            'priority' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function announcement(): static
    {
        return $this->state(['type' => 'announcement']);
    }

    public function news(): static
    {
        return $this->state(['type' => 'news']);
    }

    public function promo(): static
    {
        return $this->state(['type' => 'promo']);
    }

    public function scheduled(): static
    {
        return $this->state([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function future(): static
    {
        return $this->state([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);
    }
}
