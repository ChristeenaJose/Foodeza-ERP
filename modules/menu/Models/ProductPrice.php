<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id', 'price_type',
        'service_type_id', 'location_id', 'price', 'currency',
        'valid_from', 'valid_until', 'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(MenuServiceType::class, 'service_type_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MenuLocation::class, 'location_id');
    }
}
