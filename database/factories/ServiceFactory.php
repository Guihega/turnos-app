<?php

// database/factories/ServiceFactory.php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $services = ['Consulta General', 'Pagos', 'Trámites', 'Reclamaciones', 'Asesoría', 'Caja'];
        $name = fake()->randomElement($services).' '.fake()->randomNumber(2);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => strtoupper(substr(Str::random(3), 0, 3)),
            'color' => fake()->hexColor(),
            'estimated_duration_minutes' => fake()->randomElement([5, 10, 15, 20, 30]),
            'is_active' => true,
        ];
    }
}
