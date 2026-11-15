<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'name', 'sku', 'portion_size', 'unit',
        'price', 'recipe_multiplier', 'available', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'recipe_multiplier' => 'decimal:3',
        'available' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
