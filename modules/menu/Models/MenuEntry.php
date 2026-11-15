<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuEntry extends Model
{
    protected $fillable = [
        'menu_id', 'menu_section_id', 'product_id', 'product_variant_id',
        'sort_order', 'price', 'description', 'featured', 'available',
        'min_quantity', 'max_quantity',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'featured' => 'boolean',
        'available' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'menu_entry_addons');
    }
}
