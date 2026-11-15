<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit',
        'quantity',
        'reorder_level',
        'unit_cost',
        'supplier_name',
        'notes',
        'active',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'reorder_level' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'active' => 'boolean',
    ];

    protected $appends = ['low_stock', 'stock_value'];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function menuIngredients(): HasMany
    {
        return $this->hasMany(MenuItemIngredient::class);
    }

    public function getLowStockAttribute(): bool
    {
        return (float) $this->reorder_level > 0 && (float) $this->quantity <= (float) $this->reorder_level;
    }

    public function getStockValueAttribute(): float
    {
        return round((float) $this->quantity * (float) ($this->unit_cost ?? 0), 2);
    }
}
