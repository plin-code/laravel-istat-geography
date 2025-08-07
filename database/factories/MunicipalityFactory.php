<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;

class MunicipalityFactory extends Factory
{
    protected $model = Municipality::class;

    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            'name' => $this->faker->unique()->city(),
            'istat_code' => (string) $this->faker->unique()->numberBetween(1, 999999),
        ];
    }
}
