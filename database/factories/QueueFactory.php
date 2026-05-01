<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Queue;
use Illuminate\Database\Eloquent\Factories\Factory;

class QueueFactory extends Factory
{
    protected $model = Queue::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Cola '.fake()->word(),
            'prefix' => strtoupper(fake()->randomLetter()),
            'priority_algorithm' => 'fifo',
            'max_capacity' => 100,
            'is_active' => true,
        ];
    }
}
