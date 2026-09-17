<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use App\Support\HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float)$request->query('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float)$request->query('max_price'));
        }
        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }
        $sortable = ['name', 'price', 'stock', 'created_at'];
        $sortBy = $request->query('sort_by', 'created_at');
        $sortDir = strtolower((string)$request->query('sort_dir', 'desc'));
        if (in_array($sortBy, $sortable, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $products = $query->paginate($perPage)->withQueryString();
        return ApiResponse::paginated($products, ProductResource::collection($products->items()));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        return ApiResponse::success(new ProductResource($product), ApiMessages::PRODUCT_CREATED, HttpStatus::CREATED);
    }
    
    public function show(string $product): JsonResponse
    {
        $product = Cache::remember("products:{$product}", $this->cacheTtl(), function () use ($product) {
            return Product::findOrFail($product);
        });
        return ApiResponse::success(new ProductResource($product));
    }
   
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());
        Cache::forget("products:{$product->id}");
        return ApiResponse::success(new ProductResource($product->fresh()), ApiMessages::PRODUCT_UPDATED);
    }
    
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        Cache::forget("products:{$product->id}");
        return ApiResponse::success(message: ApiMessages::PRODUCT_DELETED);
    }
    
    //helper function to get cache ttl from config or default to 300 seconds
    protected function cacheTtl(): int
    {
        return (int) config('products.cache_ttl', 300);
    }
}
