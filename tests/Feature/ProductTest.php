<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(), 'api');
    }

    public function test_user_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'price', 'stock']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_user_can_create_product(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Wireless Mouse',
            'sku' => 'SKU-MOUSE-1',
            'description' => 'A nice mouse',
            'price' => 29.99,
            'stock' => 50,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Wireless Mouse');

        $this->assertDatabaseHas('products', ['sku' => 'SKU-MOUSE-1', 'stock' => 50]);
    }

    public function test_product_creation_validates_input(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => '',
            'price' => -5,
            'stock' => -1,
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_validation_reports_only_failing_fields(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Wireless Mouse',
            'stock' => 50,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.price.0', 'The price field is required.')
            ->assertJsonMissingPath('errors.name')
            ->assertJsonMissingPath('errors.stock');
    }

    public function test_user_can_get_single_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', $product->id);
    }

    public function test_getting_missing_product_returns_404(): void
    {
        $this->getJson('/api/products/999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_update_product(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->putJson("/api/products/{$product->id}", [
            'name' => 'Updated name',
            'stock' => 25,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Updated name');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 25]);
    }

    public function test_user_can_soft_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Still stored in the database, but hidden from normal queries.
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // It no longer appears in listings or direct lookups.
        $this->getJson('/api/products')->assertJsonCount(0, 'data');
        $this->getJson("/api/products/{$product->id}")->assertStatus(404);
    }

    public function test_product_search_filters(): void
    {
        Product::factory()->create(['name' => 'Apple Watch', 'price' => 400, 'stock' => 10]);
        Product::factory()->create(['name' => 'Banana Stand', 'price' => 25, 'stock' => 5]);
        Product::factory()->create(['name' => 'Cherry Pie', 'price' => 15, 'stock' => 0]);

        $response = $this->getJson('/api/products?search=apple&min_price=100&in_stock=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Apple Watch');
    }
}
