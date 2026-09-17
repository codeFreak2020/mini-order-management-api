<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Place an order for the given user.
     *
     * Runs inside a DB transaction so stock checks, stock decrements and the
     * order rows are all committed (or rolled back) atomically.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function placeOrder(User $user, array $items): Order
    {
        return DB::transaction(function () use ($user, $items) {
            // Merge duplicate product ids into a single quantity per product.
            $quantities = collect($items)
                ->groupBy('product_id')
                ->map(fn ($group) => $group->sum('quantity'));

            // Lock the rows to prevent race conditions on stock.
            $products = Product::query()
                ->whereIn('id', $quantities->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $missing = $quantities->keys()->diff($products->keys());
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [ApiMessages::PRODUCT_UNAVAILABLE],
                ]);
            }

            $orderItems = [];
            $totalPrice = 0;

            foreach ($quantities as $productId => $quantity) {
                /** @var Product $product */
                $product = $products->get($productId);

                if ($product->stock < $quantity) {
                    throw new InsufficientStockException($product, (int) $quantity);
                }

                $product->decrement('stock', $quantity);

                $subtotal = $quantity * (float) $product->price;
                $totalPrice += $subtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ];
            }

            $order = $user->orders()->create([
                'order_number' => $this->generateOrderNumber(),
                'status' => Order::STATUS_PROCESSING,
                'total_price' => $totalPrice,
            ]);

            $order->items()->createMany($orderItems);

            return $order;
        });
    }

    /**
     * Generate a unique, human-readable order number.
     */
    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.strtoupper(Str::random(10));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
