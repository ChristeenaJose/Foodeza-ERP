<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'menu_group',
        'service_types',
        'price',
        'available',
        'allergens',
        'notes',
    ];

    protected $casts = [
        'service_types' => 'array',
        'price' => 'decimal:2',
        'available' => 'boolean',
    ];

    protected $appends = ['cost'];

    public function ingredients(): HasMany
    {
        return $this->hasMany(MenuItemIngredient::class);
    }

    public function getCostAttribute(): float
    {
        if (! $this->relationLoaded('ingredients')) {
            return 0.0;
        }

        return round($this->ingredients->sum(function (MenuItemIngredient $line) {
            $unitCost = (float) ($line->inventoryItem?->unit_cost ?? 0);

            return (float) $line->quantity * $unitCost;
        }), 2);
    }
}
