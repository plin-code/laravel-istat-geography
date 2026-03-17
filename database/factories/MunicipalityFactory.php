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
            'bel_code' => strtoupper($this->faker->unique()->lexify('????')),
            'postal_code' => null,
            'postal_codes' => null,
        ];
    }

    /**
     * Configure the municipality with postal codes.
     */
    public function withPostalCodes(string $postalCode, ?string $postalCodes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'postal_code' => $postalCode,
            'postal_codes' => $postalCodes ?? $postalCode,
        ]);
    }
}
