<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $name = 'Sucursal ' . fake()->city();
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => strtoupper(Str::random(4)),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'phone' => fake()->phoneNumber(),
            'timezone' => 'America/Mexico_City',
            'is_active' => true,
            'accepts_walkins' => true,
            'accepts_appointments' => true,
            'max_daily_tickets' => 500,
            'max_concurrent_waiting' => 50,
        ];
    }
}
