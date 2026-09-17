<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;


class ProductFactory extends Factory
{
    
    protected $model = Product::class;
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('??####')),
            'description' => fake()->sentence(12),
            'price' => fake()->randomFloat(2, 5, 500),
            'stock' => fake()->numberBetween(0, 200),
        ];
    }
}
