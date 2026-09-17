<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed the products table with 50 sample products.
     */
    public function run(): void
    {
        Product::factory()->count(50)->create();
    }
}
