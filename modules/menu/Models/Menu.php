<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'menu_type', 'status',
        'valid_from', 'valid_until', 'available_days',
        'available_time_start', 'available_time_end',
        'currency', 'seasonal', 'notes',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'available_days' => 'array',
        'seasonal' => 'boolean',
    ];

    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(MenuServiceType::class, 'menu_service_type');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(MenuLocation::class, 'menu_location');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MenuEntry::class)->orderBy('sort_order');
    }
}
