<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order', 'active', 'archived_at'];

    protected $casts = [
        'active' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MenuSection::class, 'category_id');
    }
}
