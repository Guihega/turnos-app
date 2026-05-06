<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\FeatureType;
use App\Models\Billing\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'feature_'.Str::lower(Str::random(8));

        return [
            'code' => $code,
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'type' => FeatureType::Boolean,
            'metadata' => null,
        ];
    }

    public function boolean(): self
    {
        return $this->state(fn () => ['type' => FeatureType::Boolean]);
    }

    public function quota(): self
    {
        return $this->state(fn () => ['type' => FeatureType::Quota]);
    }

    public function metered(): self
    {
        return $this->state(fn () => ['type' => FeatureType::Metered]);
    }

    public function stringValue(): self
    {
        return $this->state(fn () => ['type' => FeatureType::StringValue]);
    }
}
