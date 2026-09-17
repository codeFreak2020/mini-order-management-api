<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'sku', 'description', 'price', 'stock'];
    protected $casts = ['price' => 'decimal:2', 'stock' => 'integer'];
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
