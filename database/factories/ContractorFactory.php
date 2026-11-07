<?php

namespace Database\Factories;

use App\Enums\ContractorType;
use App\Models\Contractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contractor> */
class ContractorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'nip' => fake()->unique()->numerify('##########'),
            'type' => ContractorType::Both,
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
