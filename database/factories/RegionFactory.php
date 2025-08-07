<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PlinCode\IstatGeography\Models\Geography\Region;

class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->city(),
            'istat_code' => (string) $this->faker->unique()->numberBetween(1, 20),
        ];
    }
}
