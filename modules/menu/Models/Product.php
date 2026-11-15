<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'short_name', 'description', 'category_id', 'subcategory',
        'image_path', 'food_type', 'is_vegetarian', 'is_vegan', 'is_halal',
        'active', 'seasonal', 'kitchen_station', 'prep_time_minutes',
        'default_price', 'vat_rate', 'availability_status', 'notes',
    ];

    protected $casts = [
        'is_vegetarian' => 'boolean',
        'is_vegan' => 'boolean',
        'is_halal' => 'boolean',
        'active' => 'boolean',
        'seasonal' => 'boolean',
        'default_price' => 'decimal:2',
        'vat_rate' => 'decimal:4',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'product_allergens');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->orderByDesc('created_at');
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'product_addons');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function menuEntries(): HasMany
    {
        return $this->hasMany(MenuEntry::class);
    }

    public function getFoodCostAttribute(): float
    {
        $recipe = $this->relationLoaded('recipes')
            ? $this->recipes->first()
            : $this->recipes()->with('items.inventoryItem')->first();

        if (! $recipe) {
            return 0.0;
        }

        if (! $recipe->relationLoaded('items')) {
            $recipe->load('items.inventoryItem');
        }

        return round($recipe->items->sum(function ($line) {
            return (float) $line->quantity * (float) ($line->inventoryItem?->unit_cost ?? 0);
        }), 2);
    }

    public function getGrossMarginAttribute(): float
    {
        return round((float) $this->default_price - $this->food_cost, 2);
    }

    public function getFoodCostPercentAttribute(): ?float
    {
        $price = (float) $this->default_price;
        if ($price <= 0) {
            return null;
        }

        return round(($this->food_cost / $price) * 100, 1);
    }
}
