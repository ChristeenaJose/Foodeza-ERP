<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'image_path', 'people_count',
        'price_per_person', 'fixed_price', 'min_quantity', 'max_quantity',
        'valid_from', 'valid_until', 'status', 'notes',
    ];

    protected $casts = [
        'price_per_person' => 'decimal:2',
        'fixed_price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class)->orderBy('sort_order');
    }

    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(MenuServiceType::class, 'package_service_type');
    }
}
