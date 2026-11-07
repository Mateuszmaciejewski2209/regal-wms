<?php

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('TW-#####')),
            'ean' => fake()->unique()->ean13(),
            'name' => ucfirst(fake()->words(3, true)),
            'unit' => Unit::Piece,
            'min_stock' => 0,
            'default_price' => fake()->randomFloat(2, 5, 500),
            'vat_rate' => 23,
            'is_active' => true,
        ];
    }
}
