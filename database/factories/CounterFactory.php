<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Database\Eloquent\Factories\Factory;

class CounterFactory extends Factory
{
    protected $model = Counter::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Ventanilla '.fake()->numberBetween(1, 20),
            'number' => (string) fake()->unique()->numberBetween(1, 50),
            'status' => 'closed',
        ];
    }
}
