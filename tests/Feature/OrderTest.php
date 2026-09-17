<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'api');

        Notification::fake();
    }

    public function test_user_can_place_order(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 5]);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_price', '20.00')
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->assertDatabaseHas('orders', ['user_id' => $this->user->id, 'status' => 'completed']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 2]);

        Notification::assertSentTo($this->user, OrderPlaced::class);
    }

    public function test_order_calculates_total_price_correctly(): void
    {
        $a = Product::factory()->create(['price' => 10.50, 'stock' => 10]);
        $b = Product::factory()->create(['price' => 5.25, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $a->id, 'quantity' => 2],
                ['product_id' => $b->id, 'quantity' => 4],
            ],
        ]);

        $response->assertStatus(201)->assertJsonPath('data.total_price', '42.00');

        $this->assertDatabaseHas('products', ['id' => $a->id, 'stock' => 8]);
        $this->assertDatabaseHas('products', ['id' => $b->id, 'stock' => 6]);
    }

    public function test_order_fails_with_insufficient_stock(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 2]);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_validates_items(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [],
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_user_can_list_own_orders(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 10]);
        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'order_number', 'status', 'total_price', 'items']]]);
    }

    public function test_user_can_view_order_details(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 10]);
        $created = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $orderId = $created->json('data.id');

        $response = $this->getJson("/api/orders/{$orderId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $other = User::factory()->create();
        $order = $other->orders()->create([
            'order_number' => 'ORD-OTHER-1',
            'status' => 'completed',
            'total_price' => 10,
        ]);

        $this->getJson("/api/orders/{$order->id}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
