<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => $this->faker->unique()->city(),
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'istat_code' => (string) $this->faker->unique()->numberBetween(1, 999),
        ];
    }
}
