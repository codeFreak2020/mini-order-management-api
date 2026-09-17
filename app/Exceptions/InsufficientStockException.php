<?php

namespace App\Exceptions;

use App\Models\Product;
use App\Support\ApiMessages;
use Exception;

class InsufficientStockException extends Exception
{
    // custom exception to handle insufficient stock for a product
    public function __construct(public readonly Product $product, public readonly int $requested)
    {
        parent::__construct(sprintf(
            ApiMessages::INSUFFICIENT_STOCK,
            $this->product->name,
            $this->requested,
            $this->product->stock
        ));
    }
}
