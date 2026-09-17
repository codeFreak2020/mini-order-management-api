<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\ProcessOrderJob;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use App\Support\HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
        //
        
    }  

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->placeOrder($request->user(), $request->validated('items'));
        ProcessOrderJob::dispatch($order)->afterCommit();
        $order->load('items');
        return ApiResponse::success(new OrderResource($order), ApiMessages::ORDER_PLACED, HttpStatus::CREATED);
    }
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $orders = $request->user()
            ->orders()
            ->with('items')
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated($orders, OrderResource::collection($orders->items()));
    }   
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return ApiResponse::error(ApiMessages::ORDER_NOT_FOUND, HttpStatus::NOT_FOUND);
        }

        return ApiResponse::success(new OrderResource($order->load('items')));
    }
}
