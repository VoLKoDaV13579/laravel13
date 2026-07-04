<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'price' => fake()->numberBetween(100, 10000),
            'stock' => fake()->numberBetween(1, 100),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }
}
